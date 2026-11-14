<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Console;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Illuminate\Testing\PendingCommand;
use Modules\Contacts\Console\Commands\SendAgreementReviewsCommand;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\AgreementStatus;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Notifications\AgreementReviewDueNotification;
use RuntimeException;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

final class SendAgreementReviewsCommandTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use UsesUtcStorage;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private OrganizationModel $organization;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendAgreementReviewsCommand::class));
        Notification::fake();
        $this->today = app(TimezoneServiceInterface::class)->today()->format('Y-m-d');
        $this->organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agreement(array $attributes = [], ?OrganizationModel $organization = null): AgreementModel
    {
        return ($organization ?? $this->organization)->agreements()->create($attributes + [
            'title' => 'Descuento',
            'kind' => AgreementKind::Discount,
            'review_on' => $this->today,
        ]);
    }

    private function manager(): UserModel
    {
        return $this->editorWithPermissions(['contacts:contacts.manage']);
    }

    private function run_(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('contacts:send-agreement-reviews');
        $command->assertSuccessful();
    }

    private function day(int $offset): string
    {
        return app(TimezoneServiceInterface::class)->today()->modify(sprintf('%+d days', $offset))->format('Y-m-d');
    }

    public function test_it_notifies_a_due_agreement_and_marks_the_date(): void
    {
        $manager = $this->manager();
        $agreement = $this->agreement();

        $this->run_();

        Notification::assertSentTo($manager, AgreementReviewDueNotification::class);
        $this->assertSame($this->today, $agreement->refresh()->last_reminded_for?->format('Y-m-d'));
    }

    public function test_it_notifies_overdue_agreements_and_paused_ones(): void
    {
        $manager = $this->manager();
        $this->agreement(['review_on' => $this->day(-10)]);
        $this->agreement(['status' => AgreementStatus::Paused]);

        $this->run_();

        Notification::assertSentToTimes($manager, AgreementReviewDueNotification::class, 2);
    }

    public function test_it_skips_ended_future_and_undated_agreements(): void
    {
        $this->manager();
        $this->agreement(['status' => AgreementStatus::Ended]);
        $this->agreement(['review_on' => $this->day(1)]);
        $this->agreement(['review_on' => null]);

        $this->run_();

        Notification::assertNothingSent();
    }

    public function test_it_skips_agreements_of_archived_organizations_until_unarchived(): void
    {
        $manager = $this->manager();
        $archived = OrganizationModel::create(['name' => 'Vieja', 'type' => OrganizationType::Shop, 'status' => OrganizationStatus::Archived]);
        $agreement = $this->agreement([], $archived);

        $this->run_();

        Notification::assertNothingSent();
        $this->assertNull($agreement->refresh()->last_reminded_for);

        $archived->update(['status' => OrganizationStatus::Active]);
        $this->run_();

        Notification::assertSentTo($manager, AgreementReviewDueNotification::class);
    }

    public function test_it_reminds_once_per_review_date(): void
    {
        $manager = $this->manager();
        $agreement = $this->agreement();

        $this->run_();
        $this->run_();

        Notification::assertSentToTimes($manager, AgreementReviewDueNotification::class, 1);

        // A new date is the gesture of «I have reviewed it»; when it comes, it is reminded again
        $agreement->update(['review_on' => $this->day(-1)]);
        $this->run_();

        Notification::assertSentToTimes($manager, AgreementReviewDueNotification::class, 2);
    }

    public function test_it_sends_to_the_responsible_person_only(): void
    {
        $responsible = $this->manager();
        $other = $this->manager();
        $this->agreement(['responsible_id' => $responsible->id]);

        $this->run_();

        Notification::assertSentTo($responsible, AgreementReviewDueNotification::class);
        Notification::assertNotSentTo($other, AgreementReviewDueNotification::class);
    }

    public function test_it_falls_back_to_every_manager_when_there_is_no_responsible_person(): void
    {
        $first = $this->manager();
        $second = $this->manager();
        $viewer = $this->editorWithPermissions(['contacts:contacts.view_any']);
        $this->agreement();

        $this->run_();

        Notification::assertSentTo($first, AgreementReviewDueNotification::class);
        Notification::assertSentTo($second, AgreementReviewDueNotification::class);
        Notification::assertNotSentTo($viewer, AgreementReviewDueNotification::class);
    }

    public function test_it_falls_back_to_managers_when_the_responsible_person_lost_the_permission(): void
    {
        $responsible = $this->editorWithPermissions(['contacts:contacts.view_any']);
        $manager = $this->manager();
        $this->agreement(['responsible_id' => $responsible->id]);

        $this->run_();

        Notification::assertNotSentTo($responsible, AgreementReviewDueNotification::class);
        Notification::assertSentTo($manager, AgreementReviewDueNotification::class);
    }

    public function test_it_never_notifies_anonymized_accounts(): void
    {
        $anonymized = $this->manager();
        $anonymized->forceFill(['anonymized_at' => now()])->save();
        $manager = $this->manager();
        $this->agreement();

        $this->run_();

        Notification::assertNotSentTo($anonymized, AgreementReviewDueNotification::class);
        Notification::assertSentTo($manager, AgreementReviewDueNotification::class);
    }

    public function test_it_leaves_the_date_unclaimed_when_nobody_can_be_notified(): void
    {
        $agreement = $this->agreement();

        $this->run_();

        Notification::assertNothingSent();
        $this->assertNull($agreement->refresh()->last_reminded_for);

        // Once someone holds the permission it is sent, and only then marked
        $manager = $this->manager();
        $this->run_();

        Notification::assertSentTo($manager, AgreementReviewDueNotification::class);
        $this->assertSame($this->today, $agreement->fresh()->last_reminded_for?->format('Y-m-d'));
    }

    public function test_it_falls_back_to_managers_when_the_responsible_person_was_deleted(): void
    {
        $responsible = $this->manager();
        $manager = $this->manager();
        $this->agreement(['responsible_id' => $responsible->id]);
        $responsible->delete();

        $this->run_();

        Notification::assertSentTo($manager, AgreementReviewDueNotification::class);
        Notification::assertNotSentTo($responsible, AgreementReviewDueNotification::class);
    }

    public function test_a_failed_sending_gives_the_date_back_and_does_not_stop_the_other_agreements(): void
    {
        $this->manager();
        $failing = $this->agreement(['title' => 'Falla', 'review_on' => $this->day(-2), 'last_reminded_for' => $this->day(-9)]);
        $fine = $this->agreement(['title' => 'Bien']);
        $fake = new class extends NotificationFake
        {
            public static bool $throwing = true;

            /** @var list<string> */
            public static array $delivered = [];

            public function send($notifiables, $notification): void
            {
                if (self::$throwing && str_contains(serialize($notification), 'Falla')) {
                    throw new RuntimeException('Queue down');
                }

                self::$delivered[] = serialize($notification);
            }
        };
        $this->app->instance(Dispatcher::class, $fake);
        $this->app->instance(ChannelManager::class, $fake);

        /** @var PendingCommand $command */
        $command = $this->artisan('contacts:send-agreement-reviews');
        $this->assertSame(1, $command->run());

        $this->assertCount(1, $fake::$delivered);
        $this->assertStringContainsString('Bien', $fake::$delivered[0]);
        $this->assertSame($this->today, $fine->refresh()->last_reminded_for?->format('Y-m-d'));
        // The previous date is back, so the next run tries again
        $this->assertSame($this->day(-9), $failing->refresh()->last_reminded_for?->format('Y-m-d'));

        $fake::$throwing = false;
        $this->run_();

        $this->assertSame($this->day(-2), $failing->refresh()->last_reminded_for->format('Y-m-d'));
    }
}
