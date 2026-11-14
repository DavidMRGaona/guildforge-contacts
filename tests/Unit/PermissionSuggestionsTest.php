<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Domain\Authorization\ValueObjects\PermissionKey;
use Modules\Contacts\ContactsServiceProvider;
use Tests\TestCase;

final class PermissionSuggestionsTest extends TestCase
{
    /**
     * @return array<PermissionDTO>
     */
    private function permissions(): array
    {
        return (new ContactsServiceProvider($this->app))->registerPermissions();
    }

    public function test_the_module_declares_viewing_and_managing(): void
    {
        $names = array_map(fn (PermissionDTO $permission): string => $permission->name, $this->permissions());

        $this->assertSame(['contacts.view_any', 'contacts.manage'], $names);
    }

    /**
     * Contacts hold personal data: no role gets them without an admin ticking them, so none is
     * suggested for any role, editor (the core's content role) included.
     */
    public function test_no_permission_is_suggested_to_any_role(): void
    {
        foreach ($this->permissions() as $permission) {
            $this->assertSame([], $permission->roles, $permission->name);
        }
    }

    /**
     * permissions:sync refuses a key that is not "module:resource.action" and then saves none.
     */
    public function test_the_permission_keys_have_the_form_the_core_syncs(): void
    {
        foreach ($this->permissions() as $permission) {
            $key = new PermissionKey('contacts:'.$permission->name);
            $this->assertSame('contacts', $key->module(), $permission->name);
        }
    }

    /**
     * The module's translation namespace only exists once the host has the module enabled, so the
     * Spanish file is read directly.
     */
    public function test_every_permission_has_a_spanish_label(): void
    {
        /** @var array{permissions: array<string, string>} $messages */
        $messages = require __DIR__.'/../../lang/es/messages.php';

        foreach ($this->permissions() as $permission) {
            $this->assertArrayHasKey(substr($permission->name, strlen('contacts.')), $messages['permissions']);
        }
    }
}
