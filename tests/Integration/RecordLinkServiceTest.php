<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Integration;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Modules\Contacts\Application\Services\RecordLinkServiceInterface;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Domain\Enums\RecordLinkOutcome;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Tests\Support\CountingLinkableRecordLookup;
use Modules\Contacts\Tests\Support\RestrictedLinkableRecordSource;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Linking\FakeLinkableRecordSource;
use Tests\Support\Linking\ThrowingLinkableRecordSource;
use Tests\Support\Modules\ModuleTestCase;

final class RecordLinkServiceTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private OrganizationModel $organization;

    private UserModel $manager;

    protected function setUp(): void
    {
        parent::setUp();

        FakeLinkableRecordSource::$records = [];
        RestrictedLinkableRecordSource::reset();

        app(LinkableRecordSourceRegistryInterface::class)->registerMany([
            FakeLinkableRecordSource::class,
            RestrictedLinkableRecordSource::class,
            ThrowingLinkableRecordSource::class,
        ], 'testing');

        $this->organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
        $this->manager = $this->editorWithPermissions(['contacts:contacts.manage', 'contacts:contacts.view_any']);
    }

    protected function tearDown(): void
    {
        FakeLinkableRecordSource::$records = [];
        RestrictedLinkableRecordSource::reset();

        parent::tearDown();
    }

    private function service(): RecordLinkServiceInterface
    {
        return app(RecordLinkServiceInterface::class);
    }

    private function fakeRecord(string $id, string $title, ?string $date = null, bool $dateIsDay = false): LinkableRecordDTO
    {
        $record = new LinkableRecordDTO(
            type: 'fake',
            id: $id,
            title: $title,
            date: $date === null ? null : new DateTimeImmutable($date),
            dateIsDay: $dateIsDay,
            panelUrl: 'https://example.test/admin/fake/'.$id,
        );
        FakeLinkableRecordSource::$records[] = $record;

        return $record;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storedLink(array $attributes): LinkModel
    {
        return LinkModel::create($attributes + ['organization_id' => $this->organization->id, 'created_by' => $this->manager->id]);
    }

    public function test_types_lists_the_sources_that_can_be_linked_now(): void
    {
        $types = $this->service()->types();

        $this->assertSame('Fake record', $types['fake'] ?? null);
        $this->assertSame('Restricted record', $types['restricted'] ?? null);
        // Its label cannot be read: it cannot be offered
        $this->assertArrayNotHasKey('throwing', $types);
    }

    public function test_search_asks_for_what_the_viewer_may_see(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');

        $this->assertSame(['t-1'], array_map(fn (LinkableRecordDTO $record): string => $record->id, $this->service()->search('fake', '', $this->manager)));
        $this->assertSame([], $this->service()->search('restricted', '', $this->manager));
        $this->assertSame([], $this->service()->search('throwing', '', $this->manager));
        $this->assertSame([], $this->service()->search('tournament', '', $this->manager));
    }

    public function test_a_record_the_source_confirms_is_linked_to_the_organization(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');

        $outcome = $this->service()->link($this->organization, 'fake', 't-1', $this->manager);

        $this->assertSame(RecordLinkOutcome::Linked, $outcome);
        $this->assertDatabaseHas('contacts_links', [
            'organization_id' => $this->organization->id,
            'agreement_id' => null,
            'record_type' => 'fake',
            'record_id' => 't-1',
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_a_record_linked_to_an_agreement_keeps_its_organization(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $agreement = $this->organization->agreements()->create(['title' => 'Sala', 'kind' => AgreementKind::Space]);

        $this->assertSame(RecordLinkOutcome::Linked, $this->service()->link($agreement, 'fake', 't-1', $this->manager));

        $this->assertDatabaseHas('contacts_links', ['organization_id' => $this->organization->id, 'agreement_id' => $agreement->id, 'record_id' => 't-1']);
    }

    public function test_nothing_is_saved_unless_the_source_confirms_it_for_this_viewer(): void
    {
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');

        $this->assertSame(RecordLinkOutcome::Unavailable, $this->service()->link($this->organization, 'fake', 'forged', $this->manager));
        $this->assertSame(RecordLinkOutcome::Unavailable, $this->service()->link($this->organization, 'restricted', 'r-1', $this->manager));
        $this->assertSame(RecordLinkOutcome::Unavailable, $this->service()->link($this->organization, 'tournament', 't-1', $this->manager));
        $this->assertSame(RecordLinkOutcome::Unavailable, $this->service()->link($this->organization, 'throwing', 'x-1', $this->manager));
        $this->assertSame(RecordLinkOutcome::Unavailable, $this->service()->link($this->organization, str_repeat('a', 101), 'x-1', $this->manager));

        $this->assertDatabaseCount('contacts_links', 0);
    }

    public function test_linking_needs_the_manage_permission(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $viewer = $this->editorWithPermissions(['contacts:contacts.view_any']);

        $this->assertSame(RecordLinkOutcome::NotAllowed, $this->service()->link($this->organization, 'fake', 't-1', $viewer));
        $this->assertDatabaseCount('contacts_links', 0);
    }

    public function test_the_same_record_is_linked_only_once_per_organization_or_agreement(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $agreement = $this->organization->agreements()->create(['title' => 'Sala', 'kind' => AgreementKind::Space]);

        $outcomes = [
            $this->service()->link($this->organization, 'fake', 't-1', $this->manager),
            $this->service()->link($this->organization, 'fake', 't-1', $this->manager),
            $this->service()->link($agreement, 'fake', 't-1', $this->manager),
            $this->service()->link($agreement, 'fake', 't-1', $this->manager),
        ];

        $this->assertSame([RecordLinkOutcome::Linked, RecordLinkOutcome::AlreadyLinked, RecordLinkOutcome::Linked, RecordLinkOutcome::AlreadyLinked], $outcomes);

        $this->assertDatabaseCount('contacts_links', 2);
    }

    public function test_only_visible_links_are_resolved_and_the_rest_are_counted(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');
        $log = Log::spy();

        $visible = $this->storedLink(['record_type' => 'fake', 'record_id' => 't-1']);
        // Deleted record, record the viewer may not see, module not active, source that throws
        $this->storedLink(['record_type' => 'fake', 'record_id' => 'gone']);
        $this->storedLink(['record_type' => 'restricted', 'record_id' => 'r-1']);
        $this->storedLink(['record_type' => 'tournament', 'record_id' => 't-9']);
        $this->storedLink(['record_type' => 'throwing', 'record_id' => 'x-1']);

        $resolved = $this->service()->resolve($this->organization, $this->manager);

        $this->assertSame([$visible->id], $resolved->visibleIds());
        $this->assertSame('Torneo de primavera', $resolved->visible[$visible->id]->record->title);
        $this->assertSame('Fake record', $resolved->visible[$visible->id]->typeLabel);
        $this->assertSame(4, $resolved->unavailable);
        $log->shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_a_viewer_allowed_by_the_source_sees_its_link(): void
    {
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');
        RestrictedLinkableRecordSource::$allowedViewers = [$this->manager->id];
        $link = $this->storedLink(['record_type' => 'restricted', 'record_id' => 'r-1']);

        $resolved = $this->service()->resolve($this->organization, $this->manager);

        $this->assertSame([$link->id], $resolved->visibleIds());
        $this->assertSame(0, $resolved->unavailable);
    }

    public function test_the_organization_resolves_its_own_links_and_the_agreement_its_own(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $agreement = $this->organization->agreements()->create(['title' => 'Sala', 'kind' => AgreementKind::Space]);
        $own = $this->storedLink(['record_type' => 'fake', 'record_id' => 't-1']);
        $ofAgreement = $this->storedLink(['agreement_id' => $agreement->id, 'record_type' => 'fake', 'record_id' => 't-1']);

        $this->assertSame([$own->id], $this->service()->resolve($this->organization, $this->manager)->visibleIds());
        $this->assertSame([$ofAgreement->id], $this->service()->resolve($agreement, $this->manager)->visibleIds());
    }

    public function test_visible_links_come_newest_record_first_and_undated_last(): void
    {
        $this->fakeRecord('old', 'Antiguo', '2026-01-10 18:00:00');
        $this->fakeRecord('undated', 'Sin fecha');
        $this->fakeRecord('new', 'Nuevo', '2026-12-01', dateIsDay: true);

        $undated = $this->storedLink(['record_type' => 'fake', 'record_id' => 'undated']);
        $old = $this->storedLink(['record_type' => 'fake', 'record_id' => 'old']);
        $new = $this->storedLink(['record_type' => 'fake', 'record_id' => 'new']);

        $this->assertSame([$new->id, $old->id, $undated->id], $this->service()->resolve($this->organization, $this->manager)->visibleIds());
    }

    public function test_each_type_is_asked_once_with_all_its_ids_and_never_one_by_one(): void
    {
        $this->fakeRecord('t-1', 'Uno');
        $this->fakeRecord('t-2', 'Dos');
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');
        RestrictedLinkableRecordSource::$allowedViewers = [$this->manager->id];
        $this->storedLink(['record_type' => 'fake', 'record_id' => 't-1']);
        $this->storedLink(['record_type' => 'fake', 'record_id' => 't-2']);
        $this->storedLink(['record_type' => 'fake', 'record_id' => 'gone']);
        $this->storedLink(['record_type' => 'restricted', 'record_id' => 'r-1']);
        $counting = $this->countingLookup();

        $resolved = $this->service()->resolve($this->organization, $this->manager);

        $this->assertCount(3, $resolved->visible);
        $this->assertSame(1, $resolved->unavailable);
        $this->assertSame([], $counting->findCalls);
        $this->assertCount(2, $counting->findManyCalls);
        $byType = array_column($counting->findManyCalls, 'ids', 'type');
        $this->assertEqualsCanonicalizing(['t-1', 't-2', 'gone'], $byType['fake']);
        $this->assertSame(['r-1'], $byType['restricted']);
    }

    public function test_a_type_without_a_source_is_not_asked_at_all(): void
    {
        $this->storedLink(['record_type' => 'tournament', 'record_id' => 't-9']);
        $counting = $this->countingLookup();

        $resolved = $this->service()->resolve($this->organization, $this->manager);

        $this->assertSame([], $resolved->visible);
        $this->assertSame(1, $resolved->unavailable);
        $this->assertSame([], $counting->findManyCalls);
        $this->assertSame([], $counting->findCalls);
    }

    public function test_the_same_record_linked_twice_is_asked_once(): void
    {
        $this->fakeRecord('t-1', 'Uno');
        $agreement = $this->organization->agreements()->create(['title' => 'Sala', 'kind' => AgreementKind::Space]);
        $this->storedLink(['record_type' => 'fake', 'record_id' => 't-1']);
        $this->storedLink(['agreement_id' => $agreement->id, 'record_type' => 'fake', 'record_id' => 't-1']);
        $counting = $this->countingLookup();

        $this->service()->resolve($this->organization, $this->manager);

        $this->assertSame([['type' => 'fake', 'ids' => ['t-1']]], $counting->findManyCalls);
    }

    public function test_resolving_needs_the_view_permission_itself(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $this->storedLink(['record_type' => 'fake', 'record_id' => 't-1']);
        $managerOnly = $this->editorWithPermissions(['contacts:contacts.manage']);
        $nobody = $this->editorWithPermissions([]);
        $counting = $this->countingLookup();

        foreach ([$managerOnly, $nobody] as $viewer) {
            $resolved = $this->service()->resolve($this->organization, $viewer);

            $this->assertSame([], $resolved->visible);
            $this->assertSame(0, $resolved->unavailable);
        }

        $this->assertSame([], $counting->findManyCalls);
    }

    private function countingLookup(): CountingLinkableRecordLookup
    {
        $counting = new CountingLinkableRecordLookup(app(LinkableRecordLookupInterface::class));
        $this->app->instance(LinkableRecordLookupInterface::class, $counting);
        $this->app->forgetInstance(RecordLinkServiceInterface::class);

        return $counting;
    }
}
