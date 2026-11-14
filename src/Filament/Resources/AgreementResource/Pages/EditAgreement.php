<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\AgreementResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Contacts\Filament\Actions\ContactActions;
use Modules\Contacts\Filament\Resources\AgreementResource;

class EditAgreement extends EditRecord
{
    protected static string $resource = AgreementResource::class;

    public function getTitle(): string
    {
        return __('contacts::contacts.pages.edit_agreement');
    }

    protected function getHeaderActions(): array
    {
        return [
            ContactActions::deleteAgreement(),
        ];
    }
}
