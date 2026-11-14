<?php

declare(strict_types=1);

namespace Modules\Contacts\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Domain\Enums\OrganizationType;

/**
 * An outside organization the association deals with. Deleting it deletes everything that hangs
 * from it through the database's cascade (people now; history, agreements and links as they arrive).
 *
 * @property string $id
 * @property string $name
 * @property OrganizationType $type
 * @property OrganizationStatus $status
 * @property string|null $website
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PersonModel> $people
 */
final class OrganizationModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'contacts_organizations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'type',
        'status',
        'website',
        'email',
        'phone',
        'address',
        'notes',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'status' => OrganizationStatus::class,
        ];
    }

    /**
     * @return HasMany<PersonModel, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(PersonModel::class, 'organization_id');
    }

    /**
     * @param  Builder<OrganizationModel>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', OrganizationStatus::Active->value);
    }
}
