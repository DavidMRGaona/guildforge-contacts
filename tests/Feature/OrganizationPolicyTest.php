<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature;

use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\PersonModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;

final class OrganizationPolicyTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private function organization(): OrganizationModel
    {
        return OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
    }

    public function test_someone_with_no_permission_can_do_nothing(): void
    {
        $user = $this->editorWithPermissions([]);
        $organization = $this->organization();

        $this->assertFalse($user->can('viewAny', OrganizationModel::class));
        $this->assertFalse($user->can('view', $organization));
        $this->assertFalse($user->can('create', OrganizationModel::class));
    }

    public function test_viewing_does_not_allow_changing(): void
    {
        $user = $this->editorWithPermissions(['contacts:contacts.view_any']);
        $organization = $this->organization();
        $person = $organization->people()->create(['name' => 'Ana']);

        $this->assertTrue($user->can('viewAny', OrganizationModel::class));
        $this->assertTrue($user->can('view', $organization));
        $this->assertTrue($user->can('viewAny', PersonModel::class));
        $this->assertFalse($user->can('create', OrganizationModel::class));
        $this->assertFalse($user->can('update', $organization));
        $this->assertFalse($user->can('archive', $organization));
        $this->assertFalse($user->can('delete', $organization));
        $this->assertFalse($user->can('create', PersonModel::class));
        $this->assertFalse($user->can('update', $person));
        $this->assertFalse($user->can('delete', $person));
    }

    public function test_managing_allows_everything_and_seeing_what_it_changes(): void
    {
        $user = $this->editorWithPermissions(['contacts:contacts.manage']);
        $organization = $this->organization();
        $person = $organization->people()->create(['name' => 'Ana']);

        $this->assertTrue($user->can('viewAny', OrganizationModel::class));
        $this->assertTrue($user->can('view', $organization));
        $this->assertTrue($user->can('create', OrganizationModel::class));
        $this->assertTrue($user->can('update', $organization));
        $this->assertTrue($user->can('archive', $organization));
        $this->assertTrue($user->can('unarchive', $organization));
        $this->assertTrue($user->can('delete', $organization));
        $this->assertTrue($user->can('create', PersonModel::class));
        $this->assertTrue($user->can('update', $person));
        $this->assertTrue($user->can('delete', $person));
    }

    public function test_the_role_name_never_decides(): void
    {
        $this->assertFalse($this->editorWithPermissions(['contacts.manage'])->can('create', OrganizationModel::class));
    }
}
