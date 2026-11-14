<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\OrganizationResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Contacts\Filament\Resources\OrganizationResource;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    public function getTitle(): string
    {
        return __('contacts::contacts.pages.create_organization');
    }
}
