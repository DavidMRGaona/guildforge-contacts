<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Filament;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Contacts\Domain\Enums\InteractionKind;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\RelationManagers\InteractionsRelationManager;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\InteractionModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class InteractionsRelationManagerTest extends ModuleTestCase
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
        return Livewire::test(InteractionsRelationManager::class, ['ownerRecord' => $this->organization, 'pageClass' => EditOrganization::class]);
    }

    private function managerComponent(): InteractionsRelationManager
    {
        $component = $this->manager()->instance();
        $this->assertInstanceOf(InteractionsRelationManager::class, $component);

        return $component;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(array $attributes = []): InteractionModel
    {
        return $this->organization->interactions()->create($attributes + [
            'occurred_on' => '2026-11-10',
            'kind' => InteractionKind::Meeting,
            'body' => 'Hablamos del descuento.',
        ]);
    }

    public function test_the_organization_page_has_the_history_tab(): void
    {
        $this->assertContains(InteractionsRelationManager::class, OrganizationResource::getRelations());
    }

    public function test_it_lists_only_this_organizations_entries_newest_first(): void
    {
        $old = $this->entry(['occurred_on' => '2026-01-05']);
        $new = $this->entry(['occurred_on' => '2026-11-10']);
        $other = OrganizationModel::create(['name' => 'Otra', 'type' => OrganizationType::Other])
            ->interactions()->create(['occurred_on' => '2026-11-11', 'kind' => InteractionKind::Call, 'body' => 'Otra cosa']);

        $this->manager()
            ->assertCanSeeTableRecords([$new, $old], inOrder: true)
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_an_entry_is_created_with_the_signed_in_account_as_author(): void
    {
        $user = $this->actingAsPanelAdmin();
        $person = $this->organization->people()->create(['name' => 'Ana']);

        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: [
                'occurred_on' => '2026-11-12',
                'kind' => InteractionKind::Email->value,
                'person_id' => $person->id,
                'body' => 'Enviamos el presupuesto.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('contacts_interactions', [
            'organization_id' => $this->organization->id,
            'person_id' => $person->id,
            'author_id' => $user->id,
            'kind' => 'email',
            'body' => 'Enviamos el presupuesto.',
        ]);
        $this->assertSame('2026-11-12', InteractionModel::query()->firstOrFail()->occurred_on->format('Y-m-d'));
    }

    public function test_the_day_defaults_to_today_in_the_display_zone(): void
    {
        $today = app(TimezoneServiceInterface::class)->today()->format('Y-m-d');

        $this->manager()
            ->mountAction(TestAction::make('create')->table())
            ->assertSchemaStateSet(['occurred_on' => $today]);
    }

    public function test_day_kind_and_text_are_required(): void
    {
        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: ['occurred_on' => null, 'kind' => null, 'body' => ''])
            ->assertHasActionErrors(['occurred_on' => 'required', 'kind' => 'required', 'body' => 'required']);
    }

    public function test_the_person_must_belong_to_this_organization(): void
    {
        $stranger = OrganizationModel::create(['name' => 'Otra', 'type' => OrganizationType::Other])->people()->create(['name' => 'Luis']);

        $this->manager()
            ->callAction(TestAction::make('create')->table(), data: [
                'occurred_on' => '2026-11-12',
                'kind' => InteractionKind::Call->value,
                'person_id' => $stranger->id,
                'body' => 'Llamada',
            ])
            ->assertHasActionErrors(['person_id']);

        $this->assertDatabaseCount('contacts_interactions', 0);
    }

    public function test_editing_keeps_the_original_author(): void
    {
        $author = UserModel::factory()->create();
        $entry = $this->entry(['author_id' => $author->id]);

        $this->manager()
            ->callAction(TestAction::make('edit')->table($entry), data: ['body' => 'Texto corregido'])
            ->assertHasNoActionErrors();

        $entry->refresh();
        $this->assertSame('Texto corregido', $entry->body);
        $this->assertSame($author->id, $entry->author_id);
    }

    public function test_deleting_an_entry_asks_for_confirmation(): void
    {
        $entry = $this->entry();

        $action = $this->managerComponent()->getTable()->getAction('delete');
        $this->assertNotNull($action);
        $this->assertTrue($action->record($entry)->isConfirmationRequired());

        $this->manager()->callAction(TestAction::make('delete')->table($entry));
        $this->assertDatabaseMissing('contacts_interactions', ['id' => $entry->id]);
    }

    public function test_deleting_a_person_keeps_their_entries_without_person(): void
    {
        $person = $this->organization->people()->create(['name' => 'Ana']);
        $entry = $this->entry(['person_id' => $person->id]);

        $person->delete();

        $this->assertNull($entry->refresh()->person_id);
    }

    public function test_deleting_the_organization_deletes_its_history(): void
    {
        $entry = $this->entry();

        $this->organization->delete();

        $this->assertDatabaseMissing('contacts_interactions', ['id' => $entry->id]);
    }

    public function test_deleting_the_author_keeps_the_entry(): void
    {
        $author = UserModel::factory()->create();
        $entry = $this->entry(['author_id' => $author->id]);

        $author->forceDelete();

        $this->assertDatabaseHas('contacts_interactions', ['id' => $entry->id, 'author_id' => null]);
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

        foreach (['occurred_on' => null, 'body' => null, 'kind' => 'sm', 'person.name' => 'md', 'author.name' => 'xl'] as $name => $breakpoint) {
            $column = $table->getColumn($name);
            $this->assertNotNull($column, $name);
            $this->assertSame($breakpoint, $column->getVisibleFrom(), $name);
        }
    }

    public function test_someone_who_only_views_can_read_but_not_change(): void
    {
        $this->actingAs($this->editorWithPermissions(['contacts:contacts.view_any']));
        $entry = $this->entry();

        $this->assertTrue($this->managerComponent()->isReadOnly());
        $this->manager()->assertCanSeeTableRecords([$entry])->assertTableActionHidden('delete', $entry);
    }
}
