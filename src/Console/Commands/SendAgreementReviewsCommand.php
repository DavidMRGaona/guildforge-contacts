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
use Illuminate\Support\Facades\Log;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Notifications\AgreementReviewDueNotification;
use Throwable;

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
        $failed = 0;

        foreach ($agreements as $agreement) {
            // One agreement failing never stops the others
            try {
                $reminded += $this->remind($agreement) ? 1 : 0;
            } catch (Throwable $exception) {
                $failed++;
                Log::error('Could not send the review reminder of an agreement.', [
                    'agreement_id' => $agreement->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Reminded {$reminded} agreement(s).");

        if ($failed > 0) {
            $this->error("{$failed} agreement(s) could not be reminded; they are tried again on the next run.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Claims the review date, sends and keeps the claim only if it went out. The claim is given
     * back when nobody can be told or sending fails, so the next run tries again; while it holds,
     * a run by hand next to the scheduled one never doubles the reminder.
     */
    private function remind(AgreementModel $agreement): bool
    {
        $reviewOn = $agreement->review_on?->format('Y-m-d');
        $previous = $agreement->last_reminded_for?->format('Y-m-d');

        $claimed = AgreementModel::query()
            ->whereKey($agreement->getKey())
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('last_reminded_for')
                ->orWhereColumn('last_reminded_for', '!=', 'review_on'))
            ->update(['last_reminded_for' => $reviewOn]) === 1;

        if (! $claimed) {
            return false;
        }

        try {
            $recipients = $this->recipients($agreement);

            if ($recipients->isEmpty()) {
                Log::warning('Nobody can be told that an agreement is due for review.', ['agreement_id' => $agreement->id]);
                $this->giveBack($agreement, $reviewOn, $previous);

                return false;
            }

            $notification = AgreementReviewDueNotification::forAgreement($agreement);

            foreach ($recipients as $user) {
                $user->notify($notification);
            }
        } catch (Throwable $exception) {
            $this->giveBack($agreement, $reviewOn, $previous);

            throw $exception;
        }

        return true;
    }

    private function giveBack(AgreementModel $agreement, ?string $reviewOn, ?string $previous): void
    {
        AgreementModel::query()
            ->whereKey($agreement->getKey())
            ->where('last_reminded_for', $reviewOn)
            ->update(['last_reminded_for' => $previous]);
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
