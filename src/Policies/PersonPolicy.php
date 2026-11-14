<?php

declare(strict_types=1);

namespace Modules\Contacts\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\PersonModel;

final class PersonPolicy
{
    use AuthorizesWithPermissions;

    private const string VIEW = 'contacts:contacts.view_any';

    private const string MANAGE = 'contacts:contacts.manage';

    public function viewAny(UserModel $user): bool
    {
        return $this->authorizeAny($user, [self::VIEW, self::MANAGE]);
    }

    public function view(UserModel $user, PersonModel $person): bool
    {
        return $this->viewAny($user);
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function update(UserModel $user, PersonModel $person): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function delete(UserModel $user, PersonModel $person): bool
    {
        return $this->authorize($user, self::MANAGE);
    }
}
