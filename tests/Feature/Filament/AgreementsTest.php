<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Filament;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\AgreementStatus;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\RelationManagers\AgreementsRelationManager;
use Modules\Contacts\Filament\Resources\AgreementResource;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\CreateAgreement;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\ListAgreements;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use Modules\Contacts\Filament\Support\AgreementTable;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class AgreementsTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private OrganizationModel $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([OrganizationResource::class, AgreementResource::class]);
        $this->actingAsPanelAdmin();
        $this->organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agreement(array $attributes = []): AgreementModel
    {
        return $this->organization->agreements()->create($attributes + ['title' => 'Descuento', 'kind' => AgreementKind::Discount]);
    }

    private function today(): string
    {
        return app(TimezoneServiceInterface::class)->today()->format('Y-m-d');
    }

    private function yesterday(): string
    {
        return app(TimezoneServiceInterface::class)->today()->modify('-1 day')->format('Y-m-d');
    }

    /**
     * @return Testable<Component>
     */
    private function manager(): Testable
    {
        return Livewire::test(AgreementsRelationManager::class, ['ownerRecord' => $this->organization, 'pageClass' => EditOrganization::class]);
    }

    private function managerComponent(): AgreementsRelationManager
    {
        $component = $this->manager()->instance();
        $this->assertInstanceOf(AgreementsRelationManager::class, $component);

        return $component;
    }

    public function test_agreements_is_a_child_entry_of_contacts(): void
    {
        $this->assertSame('Contactos', AgreementResource::getNavigationParentItem());
        $this->assertSame(OrganizationResource::getNavigationGroup(), AgreementResource::getNavigationGroup());
        $this->assertSame('Acuerdos', AgreementResource::getNavigationLabel());
    }

    public function test_the_organization_page_has_the_agreements_tab(): void
    {
        $this->assertContains(AgreementsRelationManager::class, OrganizationResource::getRelations());
    }

    public function test_the_tab_lists_only_the_agreements_of_this_organization(): void
    {
        $mine = $this->agreement();
        $other = OrganizationModel::create(['name' => 'Otra', 'type' => OrganizationType::Other])
            ->agreements()->create(['title' => 'Ajeno', 'kind' => AgreementKind::Other]);

        $this->manager()->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_an_agreement_is_created_from_the_tab_inside_the_organization(): void
    {
        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: ['title' => 'Sala los sábados', 'kind' => AgreementKind::Space->value, 'status' => AgreementStatus::Active->value, 'review_on' => '2027-01-15'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('contacts_agreements', ['organization_id' => $this->organization->id, 'title' => 'Sala los sábados', 'kind' => 'space']);
    }

    public function test_an_agreement_is_created_from_the_list_for_any_organization(): void
    {
        Livewire::test(CreateAgreement::class)
            ->fillForm(['organization_id' => $this->organization->id, 'title' => 'Patrocinio de torneo', 'kind' => AgreementKind::Sponsorship->value, 'status' => AgreementStatus::Active->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contacts_agreements', ['organization_id' => $this->organization->id, 'title' => 'Patrocinio de torneo']);
    }

    public function test_the_title_is_required_and_the_end_cannot_precede_the_start(): void
    {
        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: ['title' => '', 'kind' => 'other', 'status' => 'active', 'starts_on' => '2026-05-10', 'ends_on' => '2026-05-01'])
            ->assertHasActionErrors(['title' => 'required', 'ends_on']);
    }

    public function test_the_document_link_must_be_https(): void
    {
        foreach (['http://docs.example/a', 'javascript:alert(1)', 'ftp://docs.example/a'] as $url) {
            $this->manager()
                ->callAction(TestAction::make('create')->table(), data: ['title' => 'X', 'kind' => 'other', 'status' => 'active', 'document_url' => $url])
                ->assertHasActionErrors(['document_url']);
        }

        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: ['title' => 'X', 'kind' => 'other', 'status' => 'active', 'document_url' => 'https://docs.example/a'])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('contacts_agreements', ['title' => 'X', 'document_url' => 'https://docs.example/a']);
    }

    public function test_the_responsible_selector_offers_only_accounts_that_manage_contacts(): void
    {
        $manager = $this->editorWithPermissions(['contacts:contacts.manage']);
        $viewer = $this->editorWithPermissions(['contacts:contacts.view_any']);

        $options = AgreementTable::responsibleOptions();

        $this->assertArrayHasKey((string) $manager->getKey(), $options);
        $this->assertArrayNotHasKey((string) $viewer->getKey(), $options);
    }

    public function test_a_responsible_without_the_permission_is_refused_on_save(): void
    {
        $manager = $this->editorWithPermissions(['contacts:contacts.manage']);
        $viewer = $this->editorWithPermissions(['contacts:contacts.view_any']);
        $base = ['title' => 'X', 'kind' => 'other', 'status' => 'active'];

        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: $base + ['responsible_id' => $viewer->getKey()])
            ->assertHasActionErrors(['responsible_id']);
        $this->assertDatabaseMissing('contacts_agreements', ['title' => 'X']);

        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: $base + ['responsible_id' => $manager->getKey()])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('contacts_agreements', ['title' => 'X', 'responsible_id' => $manager->getKey()]);
    }

    public function test_the_list_filters_by_status_kind_and_pending_review(): void
    {
        $due = $this->agreement(['title' => 'Vencido', 'review_on' => $this->yesterday()]);
        $future = $this->agreement(['title' => 'Futuro', 'review_on' => '2099-01-01', 'kind' => AgreementKind::Space]);
        $ended = $this->agreement(['title' => 'Terminado', 'review_on' => $this->yesterday(), 'status' => AgreementStatus::Ended]);

        Livewire::test(ListAgreements::class)
            ->filterTable('review_due', true)
            ->assertCanSeeTableRecords([$due])
            ->assertCanNotSeeTableRecords([$future, $ended]);

        Livewire::test(ListAgreements::class)
            ->filterTable('status', AgreementStatus::Ended->value)
            ->assertCanSeeTableRecords([$ended])
            ->assertCanNotSeeTableRecords([$due, $future]);

        Livewire::test(ListAgreements::class)
            ->filterTable('kind', AgreementKind::Space->value)
            ->assertCanSeeTableRecords([$future])
            ->assertCanNotSeeTableRecords([$due, $ended]);
    }

    public function test_a_past_review_day_is_highlighted_only_while_the_agreement_is_open(): void
    {
        $due = $this->agreement(['review_on' => $this->yesterday()]);
        $today = $this->agreement(['review_on' => $this->today()]);
        $future = $this->agreement(['review_on' => '2099-01-01']);
        $ended = $this->agreement(['review_on' => $this->yesterday(), 'status' => AgreementStatus::Ended]);

        $column = Livewire::test(ListAgreements::class)->instance()->getTable()->getColumn('review_on');
        $this->assertNotNull($column);

        $this->assertSame('danger', $column->record($due)->getColor(null));
        $this->assertSame('danger', $column->record($today)->getColor(null));
        $this->assertSame('gray', $column->record($future)->getColor(null));
        $this->assertSame('gray', $column->record($ended)->getColor(null));
    }

    public function test_deleting_an_agreement_needs_the_checkbox(): void
    {
        $agreement = $this->agreement();

        $this->manager()
            ->callAction(TestAction::make('delete')->table($agreement), data: ['confirmation' => false])
            ->assertHasActionErrors(['confirmation']);
        $this->assertDatabaseHas('contacts_agreements', ['id' => $agreement->id]);

        $this->manager()
            ->callAction(TestAction::make('delete')->table($agreement), data: ['confirmation' => true])
            ->assertHasNoActionErrors();
        $this->assertDatabaseMissing('contacts_agreements', ['id' => $agreement->id]);
    }

    public function test_the_row_menu_is_one_group_starting_with_row_details(): void
    {
        foreach ([$this->managerComponent()->getTable(), Livewire::test(ListAgreements::class)->instance()->getTable()] as $table) {
            $actions = $table->getRecordActions();

            $this->assertCount(1, $actions);
            $this->assertInstanceOf(ActionGroup::class, $actions[0]);
            $this->assertSame(RowDetailsAction::NAME, array_key_first($actions[0]->getFlatActions()));
        }
    }

    public function test_secondary_columns_show_only_from_the_width_where_they_fit(): void
    {
        $table = Livewire::test(ListAgreements::class)->instance()->getTable();

        $expected = ['title' => null, 'status' => null, 'organization.name' => 'sm', 'review_on' => 'sm', 'kind' => 'md', 'responsible.name' => 'xl'];
        foreach ($expected as $name => $breakpoint) {
            $column = $table->getColumn($name);
            $this->assertNotNull($column, $name);
            $this->assertSame($breakpoint, $column->getVisibleFrom(), $name);
        }
        $this->assertTrue($table->getColumn('title')?->canWrap());
    }

    public function test_someone_who_only_views_cannot_change_agreements(): void
    {
        $this->actingAs($this->editorWithPermissions(['contacts:contacts.view_any']));
        $agreement = $this->agreement();

        $this->assertTrue($this->managerComponent()->isReadOnly());
        $this->manager()->assertTableActionHidden('delete', $agreement);
        $this->assertFalse(AgreementResource::canCreate());
        $this->assertTrue(AgreementResource::canViewAny());
    }

    public function test_someone_without_contact_permissions_does_not_see_the_entry(): void
    {
        $this->actingAs($this->editorWithPermissions([]));

        $this->assertFalse(AgreementResource::canViewAny());
    }
}
