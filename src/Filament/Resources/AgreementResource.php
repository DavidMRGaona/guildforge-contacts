<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources;

use App\Filament\Resources\BaseResource;
use App\Filament\Support\PanelNavigationGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\CreateAgreement;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\EditAgreement;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\ListAgreements;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\ViewAgreement;
use Modules\Contacts\Filament\Support\AgreementTable;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;

/**
 * «Acuerdos»: every agreement of every organization in one list, child entry of «Contactos».
 * The same agreements are also a tab of each organization (AgreementsRelationManager).
 */
class AgreementResource extends BaseResource
{
    protected static ?string $model = AgreementModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::People->getLabel();
    }

    public static function getNavigationParentItem(): string
    {
        return __('contacts::contacts.navigation.contacts');
    }

    public static function getNavigationLabel(): string
    {
        return __('contacts::contacts.navigation.agreements');
    }

    public static function getModelLabel(): string
    {
        return __('contacts::contacts.model.agreement.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contacts::contacts.model.agreement.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->label(__('contacts::contacts.fields.organization'))
                    ->relationship('organization', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->columnSpanFull(),
                ...AgreementTable::fields(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(AgreementTable::columns(withOrganization: true))
            ->filters(AgreementTable::filters())
            ->recordActions(AgreementTable::recordActions())
            ->defaultSort('review_on');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgreements::route('/'),
            'create' => CreateAgreement::route('/create'),
            'view' => ViewAgreement::route('/{record}'),
            'edit' => EditAgreement::route('/{record}/edit'),
        ];
    }
}
