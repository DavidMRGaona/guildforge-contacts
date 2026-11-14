<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\AgreementResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Contacts\Filament\Resources\AgreementResource;

class ListAgreements extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = AgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('contacts::contacts.pages.create_agreement')),
            ...array_filter([$this->knowledgeGuideAction('contacts.panel.contacts')]),
        ];
    }
}
