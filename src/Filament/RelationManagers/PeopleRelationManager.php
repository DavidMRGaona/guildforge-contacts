<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\RelationManagers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Contacts\Filament\Actions\ContactActions;

/**
 * The «Personas de contacto» tab of an organization. A person never lives outside one.
 */
class PeopleRelationManager extends RelationManager
{
    protected static string $relationship = 'people';

    /**
     * Whoever may not manage contacts reads the people, nothing else, whatever page hosts the tab.
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
        return __('contacts::contacts.relation_managers.people.title');
    }

    protected static function getModelLabel(): string
    {
        return __('contacts::contacts.model.person.singular');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('contacts::contacts.model.person.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('contacts::contacts.fields.name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('role')
                    ->label(__('contacts::contacts.fields.role'))
                    ->maxLength(255),

                TextInput::make('email')
                    ->label(__('contacts::contacts.fields.email'))
                    ->email()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label(__('contacts::contacts.fields.phone'))
                    ->tel()
                    ->maxLength(40),

                Textarea::make('notes')
                    ->label(__('contacts::contacts.fields.notes'))
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('contacts::contacts.fields.name'))
                    ->wrap()
                    ->sortable(),

                TextColumn::make('role')
                    ->label(__('contacts::contacts.fields.role'))
                    ->placeholder('—')
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
            ->defaultSort('name')
            ->emptyStateHeading(__('contacts::contacts.relation_managers.people.empty_state'))
            ->emptyStateDescription(__('contacts::contacts.relation_managers.people.empty_state_description'))
            ->headerActions([
                CreateAction::make()
                    ->label(__('contacts::contacts.relation_managers.people.create'))
                    ->modalHeading(__('contacts::contacts.relation_managers.people.create')),
            ])
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    EditAction::make(),
                    ContactActions::deletePerson(),
                ]),
            ]);
    }
}
