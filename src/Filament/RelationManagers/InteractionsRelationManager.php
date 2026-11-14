<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\RelationManagers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Contacts\Domain\Enums\InteractionKind;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

/**
 * The «Historial» tab of an organization: what was talked about, newest first. Plain text on
 * purpose, so it needs no rich editor.
 */
class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    /**
     * Whoever may not manage contacts reads the history, nothing else, whatever page hosts the tab.
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
        return __('contacts::contacts.relation_managers.interactions.title');
    }

    protected static function getModelLabel(): string
    {
        return __('contacts::contacts.model.interaction.singular');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('contacts::contacts.model.interaction.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('occurred_on')
                    ->label(__('contacts::contacts.interaction.occurred_on'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    // A calendar day: «today» is the display zone's, never the server's
                    ->default(fn (): string => app(TimezoneServiceInterface::class)->today()->format('Y-m-d'))
                    ->required(),

                Select::make('kind')
                    ->label(__('contacts::contacts.interaction.kind'))
                    ->options(InteractionKind::options())
                    ->native(false)
                    ->required(),

                // Only the people of this organization (Filament validates the value against these options)
                Select::make('person_id')
                    ->label(__('contacts::contacts.interaction.person'))
                    ->options(fn (): array => $this->peopleOptions())
                    ->placeholder(__('contacts::contacts.interaction.no_person'))
                    ->native(false)
                    ->searchable()
                    ->columnSpanFull(),

                // Optional, and only agreements of this organization (validated against these options)
                Select::make('agreement_id')
                    ->label(__('contacts::contacts.interaction.agreement'))
                    ->options(fn (): array => $this->agreementOptions())
                    ->placeholder(__('contacts::contacts.interaction.no_agreement'))
                    ->native(false)
                    ->searchable()
                    ->columnSpanFull(),

                Textarea::make('body')
                    ->label(__('contacts::contacts.interaction.body'))
                    ->required()
                    ->rows(5)
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['person', 'agreement', 'author']))
            ->columns([
                TextColumn::make('occurred_on')
                    ->label(__('contacts::contacts.interaction.occurred_on'))
                    // Never through ->date(): it converts to the display zone and could shift the day
                    ->formatStateUsing(fn (Carbon $state): string => $state->format('d/m/Y'))
                    ->sortable(),

                TextColumn::make('kind')
                    ->label(__('contacts::contacts.interaction.kind'))
                    ->badge()
                    ->formatStateUsing(fn (InteractionKind $state): string => $state->label())
                    ->color(fn (InteractionKind $state): string => $state->color())
                    ->visibleFrom('sm'),

                TextColumn::make('person.name')
                    ->label(__('contacts::contacts.interaction.person'))
                    ->placeholder('—')
                    ->visibleFrom('md'),

                TextColumn::make('agreement.title')
                    ->label(__('contacts::contacts.interaction.agreement'))
                    ->placeholder('—')
                    ->limit(40)
                    ->visibleFrom('xl'),

                TextColumn::make('author.name')
                    ->label(__('contacts::contacts.interaction.author'))
                    ->placeholder('—')
                    // At lg the fixed sidebar leaves no more room than at md
                    ->visibleFrom('xl'),

                TextColumn::make('body')
                    ->label(__('contacts::contacts.interaction.body'))
                    ->wrap()
                    ->lineClamp(3),
            ])
            // Newest day first; the ones of one day, the last written first
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('occurred_on')->orderByDesc('created_at'))
            ->emptyStateHeading(__('contacts::contacts.relation_managers.interactions.empty_state'))
            ->emptyStateDescription(__('contacts::contacts.relation_managers.interactions.empty_state_description'))
            ->headerActions([
                CreateAction::make()
                    ->label(__('contacts::contacts.relation_managers.interactions.create'))
                    ->modalHeading(__('contacts::contacts.relation_managers.interactions.create'))
                    // The author is whoever writes it, never a form field
                    ->mutateDataUsing(function (array $data): array {
                        $data['author_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading(__('contacts::contacts.relation_managers.interactions.delete_heading'))
                        ->modalDescription(__('contacts::contacts.relation_managers.interactions.delete_description')),
                ]),
            ]);
    }

    /**
     * @return array<string, string> person id => name
     */
    private function peopleOptions(): array
    {
        $organization = $this->getOwnerRecord();

        if (! $organization instanceof OrganizationModel) {
            return [];
        }

        return $organization->people()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string> agreement id => title
     */
    private function agreementOptions(): array
    {
        $organization = $this->getOwnerRecord();

        if (! $organization instanceof OrganizationModel) {
            return [];
        }

        return $organization->agreements()->orderBy('title')->pluck('title', 'id')->all();
    }
}
