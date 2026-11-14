<?php

declare(strict_types=1);

namespace Modules\Contacts\Application\Services;

use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

/**
 * Archiving takes an organization out of the default list without deleting anything: its people
 * (and, later, its history and agreements) stay as they are.
 */
interface OrganizationArchiveServiceInterface
{
    public function archive(OrganizationModel $organization): void;

    public function unarchive(OrganizationModel $organization): void;
}
