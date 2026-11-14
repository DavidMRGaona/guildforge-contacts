<?php

declare(strict_types=1);

namespace Modules\Contacts\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailDigestLine;
use App\Application\Mail\DTOs\MailDigestLineDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Contacts\ContactsServiceProvider;
use Modules\Contacts\Filament\Resources\AgreementResource;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;

/**
 * Tells whoever looks after the contacts that an agreement reaches its review day. It carries
 * plain values (the notification is queued) and checks, when the bell notice is delivered,
 * that the agreement still exists and is still open.
 */
final class AgreementReviewDueNotification extends Notification implements HasMailCategory, HasMailDigestLine, HasSiteNotification, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $agreementId,
        private readonly string $title,
        private readonly string $organizationName,
        private readonly string $reviewDate,
    ) {}

    public static function forAgreement(AgreementModel $agreement): self
    {
        $agreement->loadMissing('organization');

        return new self(
            $agreement->id,
            $agreement->title,
            $agreement->organization->name,
            $agreement->review_on?->format('d/m/Y') ?? '',
        );
    }

    public function mailCategory(): string
    {
        return ContactsServiceProvider::MAIL_AGREEMENT_REVIEW;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = is_string($notifiable->name ?? null) ? $notifiable->name : '';

        return (new MailMessage)
            ->subject(__('contacts::contacts.agreement_review.mail.subject', ['agreement' => $this->title]))
            ->greeting(__('contacts::contacts.agreement_review.mail.greeting', ['name' => EscapesMarkdown::escape($name)]))
            ->line(__('contacts::contacts.agreement_review.mail.body', [
                'agreement' => EscapesMarkdown::escape($this->title),
                'organization' => EscapesMarkdown::escape($this->organizationName),
                'date' => $this->reviewDate,
            ]))
            ->line(__('contacts::contacts.agreement_review.mail.hint'))
            ->action(__('contacts::contacts.agreement_review.mail.action'), $this->url(absolute: true));
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        // Checked when delivered: it may have ended or been deleted while it waited
        $agreement = AgreementModel::query()->find($this->agreementId);

        if (! $agreement instanceof AgreementModel || ! $agreement->status->isOpen()) {
            return null;
        }

        return new SiteNotificationDTO(
            title: $this->text(),
            url: $this->url(absolute: false),
            icon: 'file-text',
        );
    }

    public function mailDigestLine(object $notifiable): MailDigestLineDTO
    {
        return new MailDigestLineDTO($this->text(), $this->url(absolute: true));
    }

    private function text(): string
    {
        return __('contacts::contacts.agreement_review.notice', [
            'agreement' => $this->title,
            'organization' => $this->organizationName,
            'date' => $this->reviewDate,
        ]);
    }

    private function url(bool $absolute): string
    {
        return AgreementResource::getUrl('view', ['record' => $this->agreementId], isAbsolute: $absolute, panel: 'admin');
    }
}
