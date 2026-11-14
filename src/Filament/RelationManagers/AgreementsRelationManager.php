<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\RelationManagers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Contacts\Filament\Support\AgreementTable;

/**
 * The «Acuerdos» tab of an organization: what was agreed with it and when to look at it again.
 */
class AgreementsRelationManager extends RelationManager
{
    protected static string $relationship = 'agreements';

    /**
     * Whoever may not manage contacts reads the agreements, nothing else, whatever page hosts the tab.
     */
    public function isReadOnly(): bool
    {
        $user = auth()->user();

        return ! $user instanceof UserModel
            || ! app(AuthorizationServiceInterface::class)->can($user, 'contacts:contacts.manage')
            || parent::isReadOnly();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contacts::contacts.relation_managers.agreements.title');
    }

    protected static function getModelLabel(): string
    {
        return __('contacts::contacts.model.agreement.singular');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('contacts::contacts.model.agreement.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(AgreementTable::fields())->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(AgreementTable::columns(withOrganization: false))
            ->filters(AgreementTable::filters())
            ->defaultSort('review_on')
            ->emptyStateHeading(__('contacts::contacts.relation_managers.agreements.empty_state'))
            ->emptyStateDescription(__('contacts::contacts.relation_managers.agreements.empty_state_description'))
            ->headerActions([
                CreateAction::make()
                    ->label(__('contacts::contacts.relation_managers.agreements.create'))
                    ->modalHeading(__('contacts::contacts.relation_managers.agreements.create')),
            ])
            ->recordActions(AgreementTable::recordActions());
    }
}
