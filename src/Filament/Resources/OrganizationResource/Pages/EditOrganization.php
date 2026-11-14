<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Contacts\Filament\Actions\ContactActions;
use Modules\Contacts\Filament\Resources\OrganizationResource;

class EditOrganization extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = OrganizationResource::class;

    public function getTitle(): string
    {
        return __('contacts::contacts.pages.edit_organization');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            ContactActions::archive(),
            ContactActions::unarchive(),
            ContactActions::deleteOrganization(),
            ...array_filter([$this->knowledgeGuideAction('contacts.panel.contacts')]),
        ];
    }
}
