<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Filament;

use App\Filament\Actions\RowDetailsAction;
use App\Filament\Support\PanelNavigationGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Contracts\HasTable;
use Livewire\Livewire;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\CreateOrganization;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\ListOrganizations;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class ContactsPanelTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([OrganizationResource::class]);
        $this->actingAsPanelAdmin();
    }

    private function organization(string $name, OrganizationType $type = OrganizationType::Other, OrganizationStatus $status = OrganizationStatus::Active, ?string $email = null): OrganizationModel
    {
        return OrganizationModel::create(['name' => $name, 'type' => $type, 'status' => $status, 'email' => $email]);
    }

    public function test_the_entry_sits_in_people_in_slot_40(): void
    {
        $this->assertSame(PanelNavigationGroup::People->getLabel(), OrganizationResource::getNavigationGroup());
        $this->assertSame(40, OrganizationResource::getNavigationSort());
        $this->assertSame('Contactos', OrganizationResource::getNavigationLabel());
    }

    public function test_the_list_hides_archived_organizations_by_default(): void
    {
        $active = $this->organization('Activa');
        $archived = $this->organization('Archivada', status: OrganizationStatus::Archived);

        Livewire::test(ListOrganizations::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$archived]);
    }

    public function test_the_status_filter_can_show_archived_ones(): void
    {
        $active = $this->organization('Activa');
        $archived = $this->organization('Archivada', status: OrganizationStatus::Archived);

        Livewire::test(ListOrganizations::class)
            ->filterTable('status', OrganizationStatus::Archived->value)
            ->assertCanSeeTableRecords([$archived])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_the_type_filter(): void
    {
        $shop = $this->organization('Tienda', OrganizationType::Shop);
        $sponsor = $this->organization('Patrocinador', OrganizationType::Sponsor);

        Livewire::test(ListOrganizations::class)
            ->filterTable('type', OrganizationType::Shop->value)
            ->assertCanSeeTableRecords([$shop])
            ->assertCanNotSeeTableRecords([$sponsor]);
    }

    public function test_search_finds_by_organization_name_person_name_and_email(): void
    {
        $byName = $this->organization('Distribuciones Dado');
        $byPerson = $this->organization('Ludoteca Norte');
        $byPerson->people()->create(['name' => 'Marta Quintana']);
        $byOrgEmail = $this->organization('Editorial Sur', email: 'pedidos@sur.example');
        $byPersonEmail = $this->organization('Radio Local');
        $byPersonEmail->people()->create(['name' => 'Pablo', 'email' => 'pablo@radio.example']);
        $other = $this->organization('Sin relación');

        $search = fn (string $term) => Livewire::test(ListOrganizations::class)->searchTable($term);

        $search('Dado')->assertCanSeeTableRecords([$byName])->assertCanNotSeeTableRecords([$other, $byPerson]);
        $search('Quintana')->assertCanSeeTableRecords([$byPerson])->assertCanNotSeeTableRecords([$byName, $other]);
        $search('pedidos@sur')->assertCanSeeTableRecords([$byOrgEmail])->assertCanNotSeeTableRecords([$other]);
        $search('pablo@radio')->assertCanSeeTableRecords([$byPersonEmail])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_secondary_columns_show_only_from_the_width_where_they_fit(): void
    {
        /** @var HasTable $component */
        $component = Livewire::test(ListOrganizations::class)->instance();
        $table = $component->getTable();

        foreach (['name' => null, 'type' => null, 'status' => 'sm', 'email' => 'xl', 'phone' => '2xl'] as $name => $breakpoint) {
            $column = $table->getColumn($name);
            $this->assertNotNull($column, $name);
            $this->assertSame($breakpoint, $column->getVisibleFrom(), $name);
        }
    }

    public function test_the_row_menu_is_one_group_starting_with_row_details(): void
    {
        /** @var HasTable $component */
        $component = Livewire::test(ListOrganizations::class)->instance();
        $actions = $component->getTable()->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
        $this->assertSame(RowDetailsAction::NAME, array_key_first($actions[0]->getFlatActions()));
    }

    public function test_an_organization_is_created_from_the_panel(): void
    {
        Livewire::test(CreateOrganization::class)
            ->fillForm(['name' => 'Club de rol', 'type' => OrganizationType::Association->value, 'website' => 'https://club.example'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contacts_organizations', ['name' => 'Club de rol', 'type' => 'association', 'status' => 'active']);
    }

    public function test_the_name_is_required_and_the_website_must_be_a_url(): void
    {
        Livewire::test(CreateOrganization::class)
            ->fillForm(['name' => '', 'type' => OrganizationType::Other->value, 'website' => 'no es una url'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'website']);
    }

    public function test_archiving_and_unarchiving_from_the_row_menu(): void
    {
        $organization = $this->organization('Tienda');

        Livewire::test(ListOrganizations::class)
            ->callAction(TestAction::make('archive')->table($organization))
            ->assertNotified();
        $this->assertSame(OrganizationStatus::Archived, $organization->refresh()->status);

        Livewire::test(ListOrganizations::class)
            ->filterTable('status', OrganizationStatus::Archived->value)
            ->callAction(TestAction::make('unarchive')->table($organization))
            ->assertNotified();
        $this->assertSame(OrganizationStatus::Active, $organization->refresh()->status);
    }

    public function test_archiving_from_the_organization_page(): void
    {
        $organization = $this->organization('Tienda');

        Livewire::test(EditOrganization::class, ['record' => $organization->getRouteKey()])
            ->callAction('archive');

        $this->assertSame(OrganizationStatus::Archived, $organization->refresh()->status);
    }

    public function test_deleting_needs_the_checkbox_and_cascades_to_the_people(): void
    {
        $organization = $this->organization('Tienda');
        $organization->people()->create(['name' => 'Ana']);

        Livewire::test(ListOrganizations::class)
            ->callAction(TestAction::make('delete')->table($organization), data: ['confirmation' => false])
            ->assertHasActionErrors(['confirmation']);
        $this->assertDatabaseHas('contacts_organizations', ['id' => $organization->id]);

        Livewire::test(ListOrganizations::class)
            ->callAction(TestAction::make('delete')->table($organization), data: ['confirmation' => true])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('contacts_organizations', ['id' => $organization->id]);
        $this->assertDatabaseCount('contacts_people', 0);
    }

    public function test_the_delete_dialog_offers_archiving_first(): void
    {
        $organization = $this->organization('Tienda');

        $page = Livewire::test(EditOrganization::class, ['record' => $organization->getRouteKey()])->instance();
        $this->assertInstanceOf(EditOrganization::class, $page);
        $action = $page->getAction('delete');

        $this->assertNotNull($action);
        $description = $action->getModalDescription();
        $this->assertIsString($description);
        $this->assertStringContainsString('archív', $description);
    }

    public function test_someone_who_only_views_sees_no_changing_actions(): void
    {
        $viewer = $this->editorWithPermissions(['contacts:contacts.view_any']);
        $this->actingAs($viewer);
        $organization = $this->organization('Tienda');

        $list = Livewire::test(ListOrganizations::class)->instance();
        $this->assertInstanceOf(HasTable::class, $list);
        $table = $list->getTable();

        foreach (['archive', 'delete', 'edit'] as $name) {
            $action = $table->getAction($name);
            $this->assertNotNull($action, $name);
            $this->assertTrue($action->record($organization)->isHidden(), $name);
        }
        $this->assertFalse(OrganizationResource::canCreate());
    }

    public function test_someone_without_permission_cannot_open_the_list(): void
    {
        $this->actingAs($this->editorWithPermissions([]));

        $this->get(OrganizationResource::getUrl())->assertForbidden();
    }

    public function test_the_panel_menu_shows_contacts_under_people(): void
    {
        $this->get(OrganizationResource::getUrl())
            ->assertOk()
            ->assertSeeInOrder([PanelNavigationGroup::People->getLabel(), 'Contactos']);
    }
}
