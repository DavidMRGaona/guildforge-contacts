<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Resources;

use App\Filament\Actions\RowDetailsAction;
use App\Filament\Resources\BaseResource;
use App\Filament\Support\PanelNavigationGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\Actions\ContactActions;
use Modules\Contacts\Filament\RelationManagers\PeopleRelationManager;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\CreateOrganization;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\ListOrganizations;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\ViewOrganization;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

/**
 * «Contactos»: the outside organizations the association deals with. Their people are a relation
 * manager of the organization page; history and agreements join it as relation managers of their own.
 */
class OrganizationResource extends BaseResource
{
    protected static ?string $model = OrganizationModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::People->getLabel();
    }

    public static function getNavigationLabel(): string
    {
        return __('contacts::contacts.navigation.contacts');
    }

    public static function getModelLabel(): string
    {
        return __('contacts::contacts.model.organization.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contacts::contacts.model.organization.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('contacts::contacts.sections.data'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('contacts::contacts.fields.name'))
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->label(__('contacts::contacts.fields.type'))
                        ->options(OrganizationType::options())
                        ->default(OrganizationType::Other->value)
                        ->required()
                        ->native(false),

                    Textarea::make('notes')
                        ->label(__('contacts::contacts.fields.notes'))
                        ->rows(3)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make(__('contacts::contacts.sections.contact'))
                ->schema([
                    TextInput::make('website')
                        ->label(__('contacts::contacts.fields.website'))
                        ->url()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label(__('contacts::contacts.fields.email'))
                        ->email()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label(__('contacts::contacts.fields.phone'))
                        ->tel()
                        ->maxLength(40),

                    Textarea::make('address')
                        ->label(__('contacts::contacts.fields.address'))
                        ->rows(2)
                        ->maxLength(1000),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('contacts::contacts.fields.name'))
                    ->wrap()
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchBy($query, $search)),

                TextColumn::make('type')
                    ->label(__('contacts::contacts.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (OrganizationType $state): string => $state->label())
                    ->color(fn (OrganizationType $state): string => $state->color()),

                TextColumn::make('status')
                    ->label(__('contacts::contacts.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (OrganizationStatus $state): string => $state->label())
                    ->color(fn (OrganizationStatus $state): string => $state->color())
                    ->visibleFrom('sm'),

                TextColumn::make('email')
                    ->label(__('contacts::contacts.fields.email'))
                    ->placeholder('—')
                    // At lg the fixed sidebar leaves no more room than at md
                    ->visibleFrom('xl'),

                TextColumn::make('phone')
                    ->label(__('contacts::contacts.fields.phone'))
                    ->placeholder('—')
                    ->visibleFrom('2xl'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('contacts::contacts.filters.type'))
                    ->options(OrganizationType::options()),

                // Archived ones stay out of the list until someone asks for them
                SelectFilter::make('status')
                    ->label(__('contacts::contacts.filters.status'))
                    ->options(OrganizationStatus::options())
                    ->default(OrganizationStatus::Active->value),
            ])
            // One menu: labelled actions made the table overflow its container
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    EditAction::make(),
                    ContactActions::archive(),
                    ContactActions::unarchive(),
                    ContactActions::deleteOrganization(),
                ]),
            ])
            ->defaultSort('name');
    }

    /**
     * Matches the organization's own name and email and, through its people, theirs: someone looks
     * for «Marta» without remembering which shop she works at.
     *
     * @param  Builder<OrganizationModel>  $query
     * @return Builder<OrganizationModel>
     */
    private static function searchBy(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($search): void {
            $query->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereHas('people', function (Builder $people) use ($search): void {
                    $people->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%");
                });
        });
    }

    public static function getRelations(): array
    {
        return [
            PeopleRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'view' => ViewOrganization::route('/{record}'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
