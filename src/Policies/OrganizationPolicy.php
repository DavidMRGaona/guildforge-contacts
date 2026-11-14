<?php

declare(strict_types=1);

namespace Modules\Contacts\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

/**
 * Seeing is `contacts.view_any`; changing anything is `contacts.manage`, which also lets its holder
 * see what they change. Decided through the authorization service, never by role name.
 */
final class OrganizationPolicy
{
    use AuthorizesWithPermissions;

    private const string VIEW = 'contacts:contacts.view_any';

    private const string MANAGE = 'contacts:contacts.manage';

    public function viewAny(UserModel $user): bool
    {
        return $this->authorizeAny($user, [self::VIEW, self::MANAGE]);
    }

    public function view(UserModel $user, OrganizationModel $organization): bool
    {
        return $this->viewAny($user);
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function update(UserModel $user, OrganizationModel $organization): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function archive(UserModel $user, OrganizationModel $organization): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function unarchive(UserModel $user, OrganizationModel $organization): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function delete(UserModel $user, OrganizationModel $organization): bool
    {
        return $this->authorize($user, self::MANAGE);
    }
}
