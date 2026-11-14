<?php

declare(strict_types=1);

namespace Modules\Contacts\Infrastructure\Services;

use Modules\Contacts\Application\Services\OrganizationArchiveServiceInterface;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

final class OrganizationArchiveService implements OrganizationArchiveServiceInterface
{
    public function archive(OrganizationModel $organization): void
    {
        $this->move($organization, OrganizationStatus::Archived);
    }

    public function unarchive(OrganizationModel $organization): void
    {
        $this->move($organization, OrganizationStatus::Active);
    }

    private function move(OrganizationModel $organization, OrganizationStatus $status): void
    {
        if ($organization->status === $status) {
            return;
        }

        $organization->status = $status;
        $organization->save();
    }
}
