<?php

declare(strict_types=1);

namespace Modules\Contacts\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\LinkModel;

/**
 * Seeing the links of an organization or agreement is `contacts.view_any`; linking and removing,
 * `contacts.manage`. Whether each linked record shows is up to its own source, for each viewer.
 */
final class LinkPolicy
{
    use AuthorizesWithPermissions;

    private const string VIEW = 'contacts:contacts.view_any';

    private const string MANAGE = 'contacts:contacts.manage';

    public function viewAny(UserModel $user): bool
    {
        return $this->authorizeAny($user, [self::VIEW, self::MANAGE]);
    }

    public function view(UserModel $user, LinkModel $link): bool
    {
        return $this->viewAny($user);
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function delete(UserModel $user, LinkModel $link): bool
    {
        return $this->authorize($user, self::MANAGE);
    }
}
