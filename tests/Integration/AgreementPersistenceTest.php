<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Integration;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\AgreementStatus;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Tests\Support\Modules\ModuleTestCase;

final class AgreementPersistenceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private OrganizationModel $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agreement(array $attributes = []): AgreementModel
    {
        return $this->organization->agreements()->create($attributes + ['title' => 'Descuento a socios', 'kind' => AgreementKind::Discount]);
    }

    public function test_a_new_agreement_is_active_with_a_uuid_key_and_no_dates(): void
    {
        $agreement = $this->agreement()->refresh();

        $this->assertTrue(Str::isUuid($agreement->id));
        $this->assertSame(AgreementStatus::Active, $agreement->status);
        $this->assertSame(AgreementKind::Discount, $agreement->kind);
        $this->assertNull($agreement->review_on);
        $this->assertNull($agreement->responsible_id);
        $this->assertNull($agreement->last_reminded_for);
    }

    public function test_dates_keep_their_calendar_day(): void
    {
        $agreement = $this->agreement(['starts_on' => '2026-03-01', 'review_on' => '2026-12-31'])->refresh();

        $this->assertSame('2026-03-01', $agreement->starts_on?->format('Y-m-d'));
        $this->assertSame('2026-12-31', $agreement->review_on?->format('Y-m-d'));
    }

    public function test_deleting_the_organization_deletes_its_agreements(): void
    {
        $agreement = $this->agreement();

        $this->organization->delete();

        $this->assertDatabaseMissing('contacts_agreements', ['id' => $agreement->id]);
    }

    public function test_review_due_takes_open_agreements_with_a_review_day_up_to_today(): void
    {
        $today = Carbon::parse('2026-06-15');
        $due = $this->agreement(['title' => 'Hoy', 'review_on' => '2026-06-15']);
        $past = $this->agreement(['title' => 'Pasado', 'review_on' => '2026-05-01', 'status' => AgreementStatus::Paused]);
        $future = $this->agreement(['title' => 'Futuro', 'review_on' => '2026-06-16']);
        $ended = $this->agreement(['title' => 'Terminado', 'review_on' => '2026-05-01', 'status' => AgreementStatus::Ended]);
        $none = $this->agreement(['title' => 'Sin fecha']);

        $ids = AgreementModel::query()->reviewDue($today)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$due->id, $past->id], $ids);
        $this->assertTrue($due->isReviewDue($today));
        $this->assertFalse($future->isReviewDue($today));
        $this->assertFalse($ended->isReviewDue($today));
        $this->assertFalse($none->isReviewDue($today));
    }
}
