<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Filament;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Filament\Actions\ActionGroup;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\RelationManagers\LinksRelationManager;
use Modules\Contacts\Filament\Resources\AgreementResource;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\EditAgreement;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Tests\Support\CountingLinkableRecordLookup;
use Modules\Contacts\Tests\Support\RestrictedLinkableRecordSource;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Linking\FakeLinkableRecordSource;
use Tests\Support\Linking\ThrowingLinkableRecordSource;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class LinksRelationManagerTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private OrganizationModel $organization;

    private UserModel $admin;

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

        $this->registerOnAdminPanel([OrganizationResource::class, AgreementResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        $this->organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
    }

    protected function tearDown(): void
    {
        FakeLinkableRecordSource::$records = [];
        RestrictedLinkableRecordSource::reset();

        parent::tearDown();
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

    private function storedLink(string $type, string $recordId, ?AgreementModel $agreement = null): LinkModel
    {
        return LinkModel::create([
            'organization_id' => $this->organization->id,
            'agreement_id' => $agreement?->id,
            'record_type' => $type,
            'record_id' => $recordId,
        ]);
    }

    /**
     * @return Testable<Component>
     */
    private function manager(?AgreementModel $agreement = null): Testable
    {
        return $agreement instanceof AgreementModel
            ? Livewire::test(LinksRelationManager::class, ['ownerRecord' => $agreement, 'pageClass' => EditAgreement::class])
            : Livewire::test(LinksRelationManager::class, ['ownerRecord' => $this->organization, 'pageClass' => EditOrganization::class]);
    }

    private function managerComponent(): LinksRelationManager
    {
        $component = $this->manager()->instance();
        $this->assertInstanceOf(LinksRelationManager::class, $component);

        return $component;
    }

    public function test_organizations_and_agreements_have_the_links_tab(): void
    {
        $this->assertContains(LinksRelationManager::class, OrganizationResource::getRelations());
        $this->assertContains(LinksRelationManager::class, AgreementResource::getRelations());
    }

    public function test_a_visible_link_shows_type_live_title_date_and_panel_link(): void
    {
        $record = $this->fakeRecord('t-1', 'Torneo de primavera', '2027-03-12', dateIsDay: true);
        $link = $this->storedLink('fake', 't-1');

        $this->manager()
            ->assertCanSeeTableRecords([$link])
            ->assertTableColumnStateSet('title', 'Torneo de primavera', $link)
            ->assertTableColumnStateSet('type', 'Fake record', $link)
            ->assertTableColumnStateSet('date', '12/03/2027', $link)
            ->assertSee($record->panelUrl ?? '');
    }

    public function test_the_title_is_read_live_from_the_source(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $link = $this->storedLink('fake', 't-1');
        FakeLinkableRecordSource::$records = [];
        $this->fakeRecord('t-1', 'Torneo de primavera (aplazado)');

        $this->manager()->assertTableColumnStateSet('title', 'Torneo de primavera (aplazado)', $link);
    }

    public function test_links_that_are_not_available_are_hidden_and_only_counted(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador secreto');
        $visible = $this->storedLink('fake', 't-1');
        $hidden = [
            $this->storedLink('fake', 'gone'),
            $this->storedLink('restricted', 'r-1'),
            $this->storedLink('tournament', 't-9'),
            $this->storedLink('throwing', 'x-1'),
        ];

        $this->manager()
            ->assertOk()
            ->assertCanSeeTableRecords([$visible])
            ->assertCanNotSeeTableRecords($hidden)
            ->assertSeeText('4 vínculos no disponibles')
            ->assertDontSeeText('Borrador secreto')
            ->assertDontSeeText('Restricted record');

        $this->assertDatabaseCount('contacts_links', 5);
    }

    public function test_with_every_link_unavailable_the_empty_state_counts_them_once(): void
    {
        $this->storedLink('tournament', 't-9');

        $html = $this->manager()->assertOk()->html();

        $this->assertSame(1, substr_count($html, '1 vínculo no disponible'));
    }

    public function test_nothing_unavailable_shows_no_count(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $this->storedLink('fake', 't-1');

        $this->manager()->assertDontSeeText('no disponible');
    }

    public function test_the_table_lists_the_newest_record_first(): void
    {
        $this->fakeRecord('old', 'Antiguo', '2026-01-10 18:00:00');
        $this->fakeRecord('new', 'Nuevo', '2026-12-01', dateIsDay: true);
        $old = $this->storedLink('fake', 'old');
        $new = $this->storedLink('fake', 'new');

        $this->manager()->assertCanSeeTableRecords([$new, $old], inOrder: true);
    }

    public function test_the_viewer_decides_what_is_visible(): void
    {
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');
        $link = $this->storedLink('restricted', 'r-1');

        $this->manager()->assertCanNotSeeTableRecords([$link]);

        RestrictedLinkableRecordSource::$allowedViewers = [$this->admin->id];

        $this->manager()->assertCanSeeTableRecords([$link])->assertTableColumnStateSet('title', 'Borrador', $link);
    }

    public function test_a_record_is_linked_after_picking_its_type(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');

        $this->manager()
            ->callAction(TestAction::make('linkRecord')->table(), data: ['record_type' => 'fake', 'record_id' => 't-1'])
            ->assertHasNoActionErrors()
            ->assertNotified(__('contacts::contacts.links.linked'));

        $this->assertDatabaseHas('contacts_links', [
            'organization_id' => $this->organization->id,
            'agreement_id' => null,
            'record_type' => 'fake',
            'record_id' => 't-1',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_the_type_picker_offers_only_the_sources_available_now(): void
    {
        $this->manager()
            ->mountAction(TestAction::make('linkRecord')->table())
            ->assertFormFieldExists('record_type', function ($field): bool {
                $options = $field->getOptions();

                return isset($options['fake'], $options['restricted'])
                    && ! isset($options['throwing'])
                    && ! isset($options['tournament']);
            });
    }

    public function test_a_forged_record_id_is_not_saved(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');

        $this->manager()->callAction(TestAction::make('linkRecord')->table(), data: ['record_type' => 'fake', 'record_id' => 'forged']);

        $this->assertDatabaseCount('contacts_links', 0);
    }

    public function test_a_record_the_viewer_may_not_see_is_not_saved(): void
    {
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');

        $this->manager()->callAction(TestAction::make('linkRecord')->table(), data: ['record_type' => 'restricted', 'record_id' => 'r-1']);

        $this->assertDatabaseCount('contacts_links', 0);
    }

    public function test_a_type_without_source_is_rejected(): void
    {
        $this->manager()
            ->callAction(TestAction::make('linkRecord')->table(), data: ['record_type' => 'tournament', 'record_id' => 't-9'])
            ->assertHasActionErrors(['record_type']);

        $this->assertDatabaseCount('contacts_links', 0);
    }

    public function test_linking_the_same_record_twice_keeps_one_link(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $this->storedLink('fake', 't-1');

        $this->manager()
            ->callAction(TestAction::make('linkRecord')->table(), data: ['record_type' => 'fake', 'record_id' => 't-1'])
            ->assertNotified(__('contacts::contacts.links.already_linked'));

        $this->assertDatabaseCount('contacts_links', 1);
    }

    public function test_the_agreement_tab_links_to_the_agreement_and_shows_only_its_links(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $this->fakeRecord('t-2', 'Jornada');
        $agreement = $this->organization->agreements()->create(['title' => 'Sala', 'kind' => AgreementKind::Space]);
        $ofOrganization = $this->storedLink('fake', 't-2');

        $this->manager($agreement)
            ->callAction(TestAction::make('linkRecord')->table(), data: ['record_type' => 'fake', 'record_id' => 't-1'])
            ->assertHasNoActionErrors();

        $ofAgreement = LinkModel::query()->where('agreement_id', $agreement->id)->sole();
        $this->assertSame($this->organization->id, $ofAgreement->organization_id);

        $this->manager($agreement)->assertCanSeeTableRecords([$ofAgreement])->assertCanNotSeeTableRecords([$ofOrganization]);
        $this->manager()->assertCanSeeTableRecords([$ofOrganization])->assertCanNotSeeTableRecords([$ofAgreement]);
    }

    public function test_a_visible_link_is_removed_after_confirming(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $link = $this->storedLink('fake', 't-1');

        $action = $this->managerComponent()->getTable()->getAction('delete');
        $this->assertNotNull($action);
        $this->assertTrue($action->record($link)->isConfirmationRequired());

        $this->manager()->callAction(TestAction::make('delete')->table($link));

        $this->assertDatabaseMissing('contacts_links', ['id' => $link->id]);
    }

    public function test_hidden_links_stay_stored(): void
    {
        $link = $this->storedLink('tournament', 't-9');

        $this->manager()
            ->assertCanNotSeeTableRecords([$link])
            ->assertSeeText('1 vínculo no disponible');

        $this->assertDatabaseHas('contacts_links', ['id' => $link->id]);
    }

    public function test_a_hidden_link_cannot_be_removed_with_a_forged_key(): void
    {
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');
        $hidden = $this->storedLink('restricted', 'r-1');

        try {
            $this->manager()->callAction(TestAction::make('delete')->table($hidden));
            $this->fail('The forged key must not resolve to a row of this table.');
        } catch (ActionNotResolvableException) {
            // Refused before anything runs
        }

        $this->assertDatabaseHas('contacts_links', ['id' => $hidden->id]);
    }

    public function test_a_link_of_another_organization_cannot_be_removed_with_a_forged_key(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $other = OrganizationModel::create(['name' => 'Otra', 'type' => OrganizationType::Other]);
        $foreign = LinkModel::create(['organization_id' => $other->id, 'record_type' => 'fake', 'record_id' => 't-1']);

        try {
            $this->manager()->callAction(TestAction::make('delete')->table($foreign));
            $this->fail('The forged key must not resolve to a row of this table.');
        } catch (ActionNotResolvableException) {
            // Refused before anything runs
        }

        $this->assertDatabaseHas('contacts_links', ['id' => $foreign->id]);
    }

    public function test_a_link_of_the_agreement_cannot_be_removed_from_the_organization_tab(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $agreement = $this->organization->agreements()->create(['title' => 'Sala', 'kind' => AgreementKind::Space]);
        $ofAgreement = $this->storedLink('fake', 't-1', $agreement);

        try {
            $this->manager()->callAction(TestAction::make('delete')->table($ofAgreement));
            $this->fail('The forged key must not resolve to a row of this table.');
        } catch (ActionNotResolvableException) {
            // Refused before anything runs
        }

        $this->assertDatabaseHas('contacts_links', ['id' => $ofAgreement->id]);
    }

    public function test_one_page_view_asks_each_type_once_and_never_record_by_record(): void
    {
        $this->fakeRecord('t-1', 'Uno');
        $this->fakeRecord('t-2', 'Dos');
        RestrictedLinkableRecordSource::$records[] = new LinkableRecordDTO('restricted', 'r-1', 'Borrador');
        $this->storedLink('fake', 't-1');
        $this->storedLink('fake', 't-2');
        $this->storedLink('fake', 'gone');
        $this->storedLink('restricted', 'r-1');
        $counting = new CountingLinkableRecordLookup(app(LinkableRecordLookupInterface::class));
        $this->app->instance(LinkableRecordLookupInterface::class, $counting);

        $this->manager()->assertOk();

        $this->assertSame([], $counting->findCalls);
        $this->assertEqualsCanonicalizing(['fake', 'restricted'], array_column($counting->findManyCalls, 'type'));
    }

    public function test_the_order_is_one_expression_whatever_the_number_of_links(): void
    {
        $queries = [];
        for ($i = 1; $i <= 6; $i++) {
            $this->fakeRecord("t-$i", "Torneo $i", sprintf('2026-0%d-10', $i));
            $this->storedLink('fake', "t-$i");
        }
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->manager()->assertOk();

        $orders = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'contacts_links') && str_contains($sql, 'order by'));
        $this->assertNotEmpty($orders);
        foreach ($orders as $sql) {
            $this->assertLessThanOrEqual(1, substr_count(strtolower($sql), 'case '));
        }
    }

    public function test_a_numeric_record_id_picked_in_the_form_is_still_linked(): void
    {
        $this->fakeRecord('12345', 'Torneo numérico');

        $this->manager()
            ->callAction(TestAction::make('linkRecord')->table(), data: ['record_type' => 'fake', 'record_id' => 12345])
            ->assertHasNoActionErrors()
            ->assertNotified(__('contacts::contacts.links.linked'));

        $this->assertDatabaseHas('contacts_links', ['record_type' => 'fake', 'record_id' => '12345']);
    }

    public function test_the_row_actions_fit_in_one_menu_with_the_details(): void
    {
        $table = $this->managerComponent()->getTable();
        $groups = array_values(array_filter($table->getRecordActions(), fn ($action): bool => $action instanceof ActionGroup));

        $this->assertCount(1, $groups);
        $this->assertSame([RowDetailsAction::NAME, 'delete'], array_keys($groups[0]->getFlatActions()));

        $title = $table->getColumn('title');
        $this->assertInstanceOf(TextColumn::class, $title);
        $this->assertTrue($title->canWrap());
        $this->assertSame('sm', $table->getColumn('type')?->getVisibleFrom());
        $this->assertSame('md', $table->getColumn('date')?->getVisibleFrom());
    }

    public function test_someone_who_only_views_reads_the_links_but_cannot_change_them(): void
    {
        $this->fakeRecord('t-1', 'Torneo de primavera');
        $link = $this->storedLink('fake', 't-1');
        $this->actingAs($this->editorWithPermissions(['contacts:contacts.view_any']));

        $this->assertTrue($this->managerComponent()->isReadOnly());
        $this->manager()
            ->assertCanSeeTableRecords([$link])
            ->assertActionHidden(TestAction::make('linkRecord')->table())
            ->assertTableActionHidden('delete', $link);
    }

    public function test_someone_without_contacts_permissions_does_not_see_the_tab(): void
    {
        $this->actingAs($this->editorWithPermissions([]));

        $this->assertFalse(LinksRelationManager::canViewForRecord($this->organization, EditOrganization::class));
    }
}
