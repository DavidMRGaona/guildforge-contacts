<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Support;

use App\Application\Authorization\Services\AuthorizationServiceInterface;

/**
 * Wraps the core's authorization and remembers which permission sets were asked for with canAll(),
 * which is how PermissionHolderFinder (a final class, so it cannot be doubled) checks each
 * candidate: one holder lookup means one such call per candidate account.
 */
final class CountingAuthorization implements AuthorizationServiceInterface
{
    /** @var list<array{user: mixed, keys: array<string>}> */
    public array $canAllCalls = [];

    /**
     * How many times this account was checked for exactly these permissions with canAll(): one per
     * holder lookup, as long as nothing else in the request asks about that account.
     *
     * @param  array<string>  $keys
     */
    public function canAllCallsFor(object $user, array $keys): int
    {
        $id = method_exists($user, 'getKey') ? $user->getKey() : null;

        return count(array_filter($this->canAllCalls, fn (array $call): bool => $call['user'] === $id && $call['keys'] === $keys));
    }

    public function __construct(private readonly AuthorizationServiceInterface $inner) {}

    public function can(object $user, string $permissionKey): bool
    {
        return $this->inner->can($user, $permissionKey);
    }

    public function canAny(object $user, array $permissionKeys): bool
    {
        return $this->inner->canAny($user, $permissionKeys);
    }

    public function canAll(object $user, array $permissionKeys): bool
    {
        $this->canAllCalls[] = ['user' => method_exists($user, 'getKey') ? $user->getKey() : null, 'keys' => $permissionKeys];

        return $this->inner->canAll($user, $permissionKeys);
    }

    public function hasRole(object $user, string $roleName): bool
    {
        return $this->inner->hasRole($user, $roleName);
    }

    public function hasAnyRole(object $user, array $roleNames): bool
    {
        return $this->inner->hasAnyRole($user, $roleNames);
    }

    public function getPermissions(object $user): array
    {
        return $this->inner->getPermissions($user);
    }

    public function getRoles(object $user): array
    {
        return $this->inner->getRoles($user);
    }

    public function assignRole(object $user, string $roleName): void
    {
        $this->inner->assignRole($user, $roleName);
    }

    public function removeRole(object $user, string $roleName): void
    {
        $this->inner->removeRole($user, $roleName);
    }

    public function syncRoles(object $user, array $roleNames): void
    {
        $this->inner->syncRoles($user, $roleNames);
    }

    public function invalidateCache(): void
    {
        $this->inner->invalidateCache();
    }

    public function invalidateCacheFor(object $user): void
    {
        $this->inner->invalidateCacheFor($user);
    }
}
