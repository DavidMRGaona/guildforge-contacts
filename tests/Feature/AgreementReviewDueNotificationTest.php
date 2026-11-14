<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature;

use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\Resources\AgreementResource;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Modules\Contacts\Notifications\AgreementReviewDueNotification;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class AgreementReviewDueNotificationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([OrganizationResource::class, AgreementResource::class]);
    }

    private function agreement(string $title = 'Descuento', string $organization = 'Tienda'): AgreementModel
    {
        return OrganizationModel::create(['name' => $organization, 'type' => OrganizationType::Shop])
            ->agreements()->create(['title' => $title, 'kind' => AgreementKind::Discount, 'review_on' => '2030-03-05']);
    }

    public function test_the_category_is_registered_for_mail_and_the_site(): void
    {
        $category = app(MailCategoryRegistryInterface::class)->find('contacts.agreement-review');

        $this->assertNotNull($category);
        $this->assertTrue($category->site);
        $this->assertTrue($category->digestible);
    }

    public function test_it_declares_its_category_channels_and_digest_line(): void
    {
        $notification = AgreementReviewDueNotification::forAgreement($this->agreement());

        $this->assertSame('contacts.agreement-review', $notification->mailCategory());
        $this->assertSame(['mail', HasSiteNotification::CHANNEL], $notification->via(new UserModel));
    }

    public function test_the_bell_notice_links_to_the_agreement_page_in_the_panel(): void
    {
        $agreement = $this->agreement();
        $notice = AgreementReviewDueNotification::forAgreement($agreement)->toSiteNotification(new UserModel);

        $this->assertNotNull($notice);
        $this->assertStringContainsString('Descuento', $notice->title);
        $this->assertStringContainsString('Tienda', $notice->title);
        $this->assertStringStartsWith('/', $notice->url);
        $this->assertStringContainsString($agreement->id, $notice->url);
    }

    public function test_the_bell_notice_is_dropped_when_the_agreement_is_gone_or_ended(): void
    {
        $agreement = $this->agreement();
        $notification = AgreementReviewDueNotification::forAgreement($agreement);

        $agreement->update(['status' => 'ended']);
        $this->assertNull($notification->toSiteNotification(new UserModel));

        $agreement->delete();
        $this->assertNull($notification->toSiteNotification(new UserModel));
    }

    public function test_the_mail_names_the_agreement_and_escapes_what_people_wrote(): void
    {
        $agreement = $this->agreement('[Oferta](http://evil.test) *grande*', 'Tienda_[x]');
        $user = new UserModel(['name' => 'Ana']);
        $mail = AgreementReviewDueNotification::forAgreement($agreement)->toMail($user);

        $text = $mail->subject.' '.implode(' ', array_map(strval(...), $mail->introLines));

        $this->assertStringContainsString('05/03/2030', $text);
        $this->assertStringContainsString('\[Oferta\]\(http://evil\.test\)', $text);
        $this->assertStringContainsString('\*grande\*', $text);
        $this->assertStringContainsString('Tienda\_\[x\]', $text);
        $this->assertStringContainsString($agreement->id, (string) $mail->actionUrl);
    }

    public function test_the_digest_line_has_the_plain_text_and_an_absolute_link(): void
    {
        $agreement = $this->agreement('[Oferta](x)');
        $line = AgreementReviewDueNotification::forAgreement($agreement)->mailDigestLine(new UserModel);

        $this->assertStringContainsString('[Oferta](x)', $line->text);
        $this->assertStringStartsWith('http', (string) $line->url);
    }
}
