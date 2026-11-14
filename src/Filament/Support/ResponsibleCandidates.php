<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Support;

use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Accounts that may be responsible for an agreement: the holders of contacts.manage. Bound as
 * scoped, so one request (or queue job) asks the finder once however many times the selector
 * renders and validates.
 */
final class ResponsibleCandidates
{
    private const string MANAGE = 'contacts:contacts.manage';

    /** @var array<string, string>|null */
    private ?array $options = null;

    public function __construct(private readonly PermissionHolderFinder $finder) {}

    /**
     * @return array<string, string> id => name
     */
    public function options(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $options = [];

        foreach ($this->finder->usersWhoCan(self::MANAGE) as $user) {
            $options[(string) $user->getKey()] = self::userName($user);
        }

        return $this->options = $options;
    }

    public function holds(?string $id): bool
    {
        return $id !== null && array_key_exists($id, $this->options());
    }

    public static function userName(UserModel $user): string
    {
        $displayName = $user->display_name;

        return is_string($displayName) && $displayName !== '' ? $displayName : $user->name;
    }
}
