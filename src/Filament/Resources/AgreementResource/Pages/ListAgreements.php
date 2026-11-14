<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\AgreementResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Contacts\Filament\Resources\AgreementResource;

class ListAgreements extends ListRecords
{
    protected static string $resource = AgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('contacts::contacts.pages.create_agreement')),
        ];
    }
}
