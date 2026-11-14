<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Filament;

use Livewire\Livewire;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\CreateOrganization;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\PersonModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Contact people can be given while creating the organization; editing keeps the «Personas» tab as the only place.
 */
final class CreateOrganizationWithPeopleTest extends ModuleTestCase
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

    /**
     * @return array<string, mixed>
     */
    private function organizationData(): array
    {
        return ['name' => 'Club de rol', 'type' => OrganizationType::Association->value];
    }

    public function test_it_creates_an_organization_with_no_people(): void
    {
        Livewire::test(CreateOrganization::class)
            ->fillForm($this->organizationData())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contacts_organizations', ['name' => 'Club de rol']);
        $this->assertSame(0, PersonModel::query()->count());
    }

    public function test_it_creates_an_organization_with_one_person(): void
    {
        Livewire::test(CreateOrganization::class)
            ->fillForm($this->organizationData() + ['people' => [
                ['name' => 'Ana Pérez', 'role' => 'Gerente', 'email' => 'ana@club.example', 'phone' => '600000000', 'notes' => 'Por las mañanas'],
            ]])
            ->call('create')
            ->assertHasNoFormErrors();

        $organization = OrganizationModel::query()->where('name', 'Club de rol')->firstOrFail();
        $this->assertDatabaseHas('contacts_people', [
            'organization_id' => $organization->id,
            'name' => 'Ana Pérez',
            'role' => 'Gerente',
            'email' => 'ana@club.example',
            'phone' => '600000000',
            'notes' => 'Por las mañanas',
        ]);
    }

    public function test_it_creates_an_organization_with_several_people(): void
    {
        Livewire::test(CreateOrganization::class)
            ->fillForm($this->organizationData() + ['people' => [
                ['name' => 'Ana Pérez'],
                ['name' => 'Luis Gil', 'email' => 'luis@club.example'],
                ['name' => 'Marta Ríos'],
            ]])
            ->call('create')
            ->assertHasNoFormErrors();

        $organization = OrganizationModel::query()->where('name', 'Club de rol')->firstOrFail();
        $this->assertSame(3, $organization->people()->count());
        $this->assertSame(1, PersonModel::query()->where('name', 'Luis Gil')->where('organization_id', $organization->id)->count());
    }

    public function test_an_invalid_person_blocks_the_whole_creation(): void
    {
        Livewire::test(CreateOrganization::class)
            ->fillForm($this->organizationData() + ['people' => [
                ['name' => 'Ana Pérez'],
                ['name' => '', 'email' => 'no es un correo'],
            ]])
            ->call('create')
            ->assertHasFormErrors(['people.1.name' => 'required', 'people.1.email']);

        $this->assertSame(0, OrganizationModel::query()->count());
        $this->assertSame(0, PersonModel::query()->count());
    }

    public function test_a_failure_saving_a_person_leaves_nothing_behind(): void
    {
        PersonModel::creating(static function (): never {
            throw new \RuntimeException('boom');
        });

        try {
            Livewire::test(CreateOrganization::class)
                ->fillForm($this->organizationData() + ['people' => [['name' => 'Ana Pérez']]])
                ->call('create');
            $this->fail('The failure should have surfaced.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        } finally {
            PersonModel::flushEventListeners();
        }

        $this->assertSame(0, OrganizationModel::query()->count());
    }

    public function test_the_edit_page_has_no_people_repeater(): void
    {
        $organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);

        Livewire::test(CreateOrganization::class)->assertFormFieldExists('people');
        Livewire::test(EditOrganization::class, ['record' => $organization->getRouteKey()])->assertFormFieldDoesNotExist('people');
    }

    public function test_someone_who_cannot_manage_contacts_cannot_create(): void
    {
        $this->actingAs($this->editorWithPermissions(['contacts:contacts.view_any']));

        $this->assertFalse(OrganizationResource::canCreate());
        $this->get(OrganizationResource::getUrl('create'))->assertForbidden();
        $this->assertSame(0, PersonModel::query()->count());
    }
}
