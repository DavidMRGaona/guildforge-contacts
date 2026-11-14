<?php

declare(strict_types=1);

namespace Modules\Contacts\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\AgreementStatus;

/**
 * What the association agreed with an organization and when it is worth looking at again.
 * The three dates are calendar days (no time, no zone); «today» always comes from the
 * display zone, never from the server clock.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $title
 * @property AgreementKind $kind
 * @property AgreementStatus $status
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $review_on
 * @property string|null $conditions
 * @property string|null $document_url
 * @property string|null $responsible_id
 * @property Carbon|null $last_reminded_for
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OrganizationModel $organization
 * @property-read UserModel|null $responsible
 */
final class AgreementModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'contacts_agreements';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'title',
        'kind',
        'status',
        'starts_on',
        'ends_on',
        'review_on',
        'conditions',
        'document_url',
        'responsible_id',
        'last_reminded_for',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AgreementKind::class,
            'status' => AgreementStatus::class,
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'review_on' => 'date:Y-m-d',
            'last_reminded_for' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<OrganizationModel, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'responsible_id');
    }

    /**
     * Open agreements whose review day is today or already past.
     *
     * @param  Builder<AgreementModel>  $query
     */
    public function scopeReviewDue(Builder $query, DateTimeInterface $today): void
    {
        $query->whereIn('status', AgreementStatus::openValues())
            ->whereNotNull('review_on')
            ->where('review_on', '<=', $today->format('Y-m-d'));
    }

    /**
     * Whether the review day is today or past; an ended agreement is never due.
     */
    public function isReviewDue(DateTimeInterface $today): bool
    {
        return $this->status->isOpen()
            && $this->review_on !== null
            && $this->review_on->format('Y-m-d') <= $today->format('Y-m-d');
    }
}
