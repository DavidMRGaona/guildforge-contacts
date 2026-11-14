<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Integration;

use Illuminate\Support\Str;
use Modules\Contacts\Application\Services\OrganizationArchiveServiceInterface;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\PersonModel;
use Tests\Support\Modules\ModuleTestCase;

final class OrganizationPersistenceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private function organization(string $name = 'Distribuciones Dado'): OrganizationModel
    {
        return OrganizationModel::create(['name' => $name, 'type' => OrganizationType::Supplier]);
    }

    public function test_a_new_organization_is_active_with_a_uuid_key(): void
    {
        $organization = $this->organization()->refresh();

        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertSame(OrganizationType::Supplier, $organization->type);
        $this->assertTrue(Str::isUuid($organization->id));
    }

    public function test_a_person_belongs_to_an_organization(): void
    {
        $organization = $this->organization();
        $person = $organization->people()->create(['name' => 'Ana Pérez', 'role' => 'Gerente']);

        $this->assertTrue($person->organization->is($organization));
        $this->assertSame(1, $organization->people()->count());
    }

    public function test_deleting_an_organization_deletes_its_people_only(): void
    {
        $kept = $this->organization('Otra');
        $kept->people()->create(['name' => 'Luis']);
        $gone = $this->organization();
        $gone->people()->create(['name' => 'Ana']);
        $gone->people()->create(['name' => 'Eva']);

        $gone->delete();

        $this->assertSame(1, PersonModel::query()->count());
        $this->assertSame('Luis', PersonModel::query()->firstOrFail()->name);
    }

    public function test_archiving_keeps_the_record_and_its_people(): void
    {
        $organization = $this->organization();
        $organization->people()->create(['name' => 'Ana']);

        app(OrganizationArchiveServiceInterface::class)->archive($organization);

        $this->assertSame(OrganizationStatus::Archived, $organization->refresh()->status);
        $this->assertSame(1, $organization->people()->count());
    }

    public function test_unarchiving_makes_it_active_again(): void
    {
        $organization = $this->organization();
        $service = app(OrganizationArchiveServiceInterface::class);
        $service->archive($organization);

        $service->unarchive($organization);

        $this->assertSame(OrganizationStatus::Active, $organization->refresh()->status);
    }

    public function test_active_scope_leaves_archived_ones_out(): void
    {
        $this->organization('Activa');
        $archived = $this->organization('Archivada');
        app(OrganizationArchiveServiceInterface::class)->archive($archived);

        $this->assertSame(['Activa'], OrganizationModel::query()->active()->pluck('name')->all());
    }
}
