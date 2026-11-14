<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\PersonModel;

class CreateOrganization extends CreateRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = OrganizationResource::class;

    /**
     * The organization and its people are saved together: if a person fails, nothing is left half created.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $people = $data['people'] ?? [];
        unset($data['people']);

        return DB::transaction(function () use ($data, $people): Model {
            $organization = parent::handleRecordCreation($data);

            foreach (is_array($people) ? $people : [] as $person) {
                PersonModel::query()->create([
                    'organization_id' => $organization->getKey(),
                    'name' => $person['name'],
                    'role' => $person['role'] ?? null,
                    'email' => $person['email'] ?? null,
                    'phone' => $person['phone'] ?? null,
                    'notes' => $person['notes'] ?? null,
                ]);
            }

            return $organization;
        });
    }

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
