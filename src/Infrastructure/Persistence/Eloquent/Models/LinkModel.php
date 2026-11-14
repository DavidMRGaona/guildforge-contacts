<?php

declare(strict_types=1);

namespace Modules\Contacts\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A record of another part of the site (an event, a tournament…) linked to an organization or to
 * one of its agreements. Only its type and id are kept: the title, date and panel link are asked
 * to the record's source each time, for the person looking, through the core's lookup.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $agreement_id
 * @property string $record_type
 * @property string $record_id
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OrganizationModel $organization
 * @property-read AgreementModel|null $agreement
 * @property-read UserModel|null $creator
 */
final class LinkModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'contacts_links';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'agreement_id',
        'record_type',
        'record_id',
        'created_by',
    ];

    /**
     * @return BelongsTo<OrganizationModel, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }

    /**
     * @return BelongsTo<AgreementModel, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(AgreementModel::class, 'agreement_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }
}
