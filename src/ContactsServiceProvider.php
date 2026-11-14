<?php

declare(strict_types=1);

namespace Modules\Contacts;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Modules\ModuleServiceProvider;
use Modules\Contacts\Application\Services\OrganizationArchiveServiceInterface;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\InteractionModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\PersonModel;
use Modules\Contacts\Infrastructure\Services\OrganizationArchiveService;
use Modules\Contacts\Policies\AgreementPolicy;
use Modules\Contacts\Policies\InteractionPolicy;
use Modules\Contacts\Policies\OrganizationPolicy;
use Modules\Contacts\Policies\PersonPolicy;

/**
 * Contacts of the association (organizations, people, history and agreements).
 *
 * The module is panel only: it has no public routes or pages. Its Filament resources are
 * discovered from src/Filament/Resources and each one puts itself in the core's «Personas»
 * navigation group (sort 40, see docs/modules/README.md → Navigation groups).
 */
final class ContactsServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'contacts';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(OrganizationArchiveServiceInterface::class, OrganizationArchiveService::class);
    }

    /**
     * @return array<class-string, class-string>
     */
    public function registerPolicies(): array
    {
        return [
            OrganizationModel::class => OrganizationPolicy::class,
            PersonModel::class => PersonPolicy::class,
            InteractionModel::class => InteractionPolicy::class,
            AgreementModel::class => AgreementPolicy::class,
        ];
    }

    /**
     * Stored as "contacts:contacts.view_any" and "contacts:contacts.manage". No role gets them
     * by default: an admin ticks them in the role editor.
     *
     * @return array<PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [
            new PermissionDTO(
                name: 'contacts.view_any',
                label: __('contacts::messages.permissions.view_any'),
                group: __('contacts::messages.title'),
                module: $this->moduleName(),
            ),
            new PermissionDTO(
                name: 'contacts.manage',
                label: __('contacts::messages.permissions.manage'),
                group: __('contacts::messages.title'),
                module: $this->moduleName(),
            ),
        ];
    }
}
