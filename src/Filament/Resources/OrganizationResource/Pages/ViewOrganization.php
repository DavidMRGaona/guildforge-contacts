<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Contacts\Filament\Resources\OrganizationResource;

/**
 * What someone who may see contacts but not change them opens: the same data, read only.
 */
class ViewOrganization extends ViewRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = OrganizationResource::class;

    public function getTitle(): string
    {
        return __('contacts::contacts.pages.view_organization');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...array_filter([$this->knowledgeGuideAction('contacts.panel.contacts')]),
        ];
    }
}
