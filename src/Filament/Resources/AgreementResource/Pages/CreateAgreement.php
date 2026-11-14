<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\AgreementResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Contacts\Filament\Resources\AgreementResource;

class CreateAgreement extends CreateRecord
{
    protected static string $resource = AgreementResource::class;

    public function getTitle(): string
    {
        return __('contacts::contacts.pages.create_agreement');
    }
}
