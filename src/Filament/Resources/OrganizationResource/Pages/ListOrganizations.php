<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Contacts\Filament\Resources\OrganizationResource;

class ListOrganizations extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('contacts::contacts.pages.create_organization')),
            ...array_filter([$this->knowledgeGuideAction('contacts.panel.contacts')]),
        ];
    }
}
