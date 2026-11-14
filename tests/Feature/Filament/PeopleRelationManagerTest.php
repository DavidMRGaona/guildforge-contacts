<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Filament;

use App\Filament\Actions\RowDetailsAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\RelationManagers\PeopleRelationManager;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class PeopleRelationManagerTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private OrganizationModel $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([OrganizationResource::class]);
        $this->actingAsPanelAdmin();
        $this->organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
    }

    /**
     * @return Testable<Component>
     */
    private function manager(): Testable
    {
        return Livewire::test(PeopleRelationManager::class, ['ownerRecord' => $this->organization, 'pageClass' => EditOrganization::class]);
    }

    private function managerComponent(): PeopleRelationManager
    {
        $component = $this->manager()->instance();
        $this->assertInstanceOf(PeopleRelationManager::class, $component);

        return $component;
    }

    public function test_the_organization_page_has_the_people_tab(): void
    {
        $this->assertContains(PeopleRelationManager::class, OrganizationResource::getRelations());
    }

    public function test_it_lists_only_the_people_of_this_organization(): void
    {
        $mine = $this->organization->people()->create(['name' => 'Ana']);
        $other = OrganizationModel::create(['name' => 'Otra', 'type' => OrganizationType::Other])->people()->create(['name' => 'Luis']);

        $this->manager()->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_a_person_is_created_inside_the_organization(): void
    {
        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: ['name' => 'Ana Pérez', 'role' => 'Gerente', 'email' => 'ana@tienda.example', 'phone' => '600000000'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('contacts_people', ['organization_id' => $this->organization->id, 'name' => 'Ana Pérez', 'role' => 'Gerente']);
    }

    public function test_the_name_is_required_and_the_email_must_be_valid(): void
    {
        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: ['name' => '', 'email' => 'nope'])
            ->assertHasActionErrors(['name' => 'required', 'email']);
    }

    public function test_a_person_can_be_edited(): void
    {
        $person = $this->organization->people()->create(['name' => 'Ana']);

        $this->manager()
            ->callAction(TestAction::make('edit')->table($person), data: ['name' => 'Ana María'])
            ->assertHasNoActionErrors();

        $this->assertSame('Ana María', $person->refresh()->name);
    }

    public function test_deleting_a_person_needs_the_checkbox_and_warns_about_the_history(): void
    {
        $person = $this->organization->people()->create(['name' => 'Ana']);

        $manager = $this->manager();
        $manager->callAction(TestAction::make('delete')->table($person), data: ['confirmation' => false])
            ->assertHasActionErrors(['confirmation']);
        $this->assertDatabaseHas('contacts_people', ['id' => $person->id]);

        $action = $this->managerComponent()->getTable()->getAction('delete');
        $this->assertNotNull($action);
        $description = $action->record($person)->getModalDescription();
        $this->assertIsString($description);
        $this->assertStringContainsString('historial', $description);

        $this->manager()
            ->callAction(TestAction::make('delete')->table($person), data: ['confirmation' => true])
            ->assertHasNoActionErrors();
        $this->assertDatabaseMissing('contacts_people', ['id' => $person->id]);
    }

    public function test_the_row_menu_is_one_group_starting_with_row_details(): void
    {
        $actions = $this->managerComponent()->getTable()->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
        $this->assertSame(RowDetailsAction::NAME, array_key_first($actions[0]->getFlatActions()));
    }

    public function test_secondary_columns_show_only_from_the_width_where_they_fit(): void
    {
        $table = $this->managerComponent()->getTable();

        foreach (['name' => null, 'role' => 'sm', 'email' => 'xl', 'phone' => '2xl'] as $name => $breakpoint) {
            $column = $table->getColumn($name);
            $this->assertNotNull($column, $name);
            $this->assertSame($breakpoint, $column->getVisibleFrom(), $name);
        }
    }

    public function test_someone_who_only_views_cannot_change_people(): void
    {
        $this->actingAs($this->editorWithPermissions(['contacts:contacts.view_any']));
        $person = $this->organization->people()->create(['name' => 'Ana']);

        $this->assertTrue($this->managerComponent()->isReadOnly());
        $this->manager()->assertTableActionHidden('delete', $person);
    }
}
