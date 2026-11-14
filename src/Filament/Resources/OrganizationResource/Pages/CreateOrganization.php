<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Resources\Pages\CreateRecord;
use Modules\Contacts\Filament\Resources\OrganizationResource;

class CreateOrganization extends CreateRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([$this->knowledgeGuideAction('contacts.panel.contacts')]),
        ];
    }

    public function getTitle(): string
    {
        return __('contacts::contacts.pages.create_organization');
    }
}
