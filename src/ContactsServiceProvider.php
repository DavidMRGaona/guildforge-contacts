<?php

declare(strict_types=1);

namespace Modules\Contacts;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Contacts\Application\Services\OrganizationArchiveServiceInterface;
use Modules\Contacts\Application\Services\RecordLinkServiceInterface;
use Modules\Contacts\Console\Commands\SendAgreementReviewsCommand;
use Modules\Contacts\Filament\Support\ResponsibleCandidates;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\InteractionModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\PersonModel;
use Modules\Contacts\Infrastructure\Services\OrganizationArchiveService;
use Modules\Contacts\Infrastructure\Services\RecordLinkService;
use Modules\Contacts\Policies\AgreementPolicy;
use Modules\Contacts\Policies\InteractionPolicy;
use Modules\Contacts\Policies\LinkPolicy;
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
    public const string MAIL_AGREEMENT_REVIEW = 'contacts.agreement-review';

    public function moduleName(): string
    {
        return 'contacts';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(OrganizationArchiveServiceInterface::class, OrganizationArchiveService::class);
        $this->app->scoped(ResponsibleCandidates::class);

        // Links to records of other modules, read through the core's linkable record lookup
        $this->app->bind(RecordLinkServiceInterface::class, RecordLinkService::class);
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
            LinkModel::class => LinkPolicy::class,
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

    public function boot(): void
    {
        parent::boot();

        $this->registerAgreementReviewReminder();
    }

    /**
     * @return list<MailCategoryDTO>
     */
    public function registerMailCategories(): array
    {
        return [
            MailCategoryDTO::optional(
                key: self::MAIL_AGREEMENT_REVIEW,
                section: 'contacts::contacts.agreement_review.section',
                label: 'contacts::contacts.agreement_review.label',
                description: 'contacts::contacts.agreement_review.description',
                digestible: true,
                site: true,
            ),
        ];
    }

    private function registerAgreementReviewReminder(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SendAgreementReviewsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // The scheduler runs in the display zone (app.schedule_timezone): early morning there
            $schedule->command('contacts:send-agreement-reviews')
                ->dailyAt('07:00')
                ->withoutOverlapping(10);
        });
    }
}
