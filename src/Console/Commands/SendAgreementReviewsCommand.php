<?php

declare(strict_types=1);

namespace Modules\Contacts\Console\Commands;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Notifications\AgreementReviewDueNotification;

/**
 * Reminds, once per review date, that an open agreement of an active organization is due for a
 * look. Putting a new review date is the gesture of «I have reviewed it»: it is reminded again
 * when that one comes.
 */
final class SendAgreementReviewsCommand extends Command
{
    private const string MANAGE = 'contacts:contacts.manage';

    protected $signature = 'contacts:send-agreement-reviews';

    protected $description = 'Remind that agreements have reached their review date';

    public function handle(TimezoneServiceInterface $timezone): int
    {
        $agreements = AgreementModel::query()
            ->reviewDue($timezone->today())
            ->whereHas('organization', fn (Builder $organization): Builder => $organization->where('status', OrganizationStatus::Active->value))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('last_reminded_for')
                ->orWhereColumn('last_reminded_for', '!=', 'review_on'))
            ->with(['organization', 'responsible'])
            ->orderBy('review_on')
            ->get();

        $reminded = 0;

        foreach ($agreements as $agreement) {
            // Claimed before sending, so a run by hand next to the scheduled one never doubles it
            $claimed = AgreementModel::query()
                ->whereKey($agreement->getKey())
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('last_reminded_for')
                    ->orWhereColumn('last_reminded_for', '!=', 'review_on'))
                ->update(['last_reminded_for' => $agreement->review_on?->format('Y-m-d')]) === 1;

            if (! $claimed) {
                continue;
            }

            $recipients = $this->recipients($agreement);

            if ($recipients->isNotEmpty()) {
                $notification = AgreementReviewDueNotification::forAgreement($agreement);

                foreach ($recipients as $user) {
                    $user->notify($notification);
                }
            }

            $reminded++;
        }

        $this->info("Reminded {$reminded} agreement(s).");

        return self::SUCCESS;
    }

    /**
     * The responsible person while they still manage contacts, otherwise everyone who does.
     *
     * @return Collection<int, UserModel>
     */
    private function recipients(AgreementModel $agreement): Collection
    {
        $responsible = $agreement->responsible;

        if ($responsible instanceof UserModel
            && $responsible->anonymized_at === null
            && app(AuthorizationServiceInterface::class)->can($responsible, self::MANAGE)) {
            return collect([$responsible]);
        }

        return app(PermissionHolderFinder::class)->usersWhoCan(self::MANAGE);
    }
}
