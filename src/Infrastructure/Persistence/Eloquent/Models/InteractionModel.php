<?php

declare(strict_types=1);

namespace Modules\Contacts\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Contacts\Domain\Enums\InteractionKind;

/**
 * One line of the history with an organization: what was talked about, on which day and with whom.
 * Plain text on purpose. The person and the author may disappear; the entry stays.
 *
 * @property string $id
 * @property string $organization_id
 * @property Carbon $occurred_on calendar day
 * @property InteractionKind $kind
 * @property string $body
 * @property string|null $person_id
 * @property string|null $author_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OrganizationModel $organization
 * @property-read PersonModel|null $person
 * @property-read UserModel|null $author
 */
final class InteractionModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'contacts_interactions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'occurred_on',
        'kind',
        'body',
        'person_id',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'kind' => InteractionKind::class,
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
     * @return BelongsTo<PersonModel, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonModel::class, 'person_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'author_id');
    }
}
