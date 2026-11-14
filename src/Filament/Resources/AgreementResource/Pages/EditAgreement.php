<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\AgreementResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Contacts\Filament\Actions\ContactActions;
use Modules\Contacts\Filament\Resources\AgreementResource;

class EditAgreement extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = AgreementResource::class;

    public function getTitle(): string
    {
        return __('contacts::contacts.pages.edit_agreement');
    }

    /**
     * Never moves the agreement to another organization, whatever the submitted state says.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['organization_id']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ContactActions::deleteAgreement(),
            ...array_filter([$this->knowledgeGuideAction('contacts.panel.contacts')]),
        ];
    }
}
