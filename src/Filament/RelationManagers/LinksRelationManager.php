<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\RelationManagers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Contacts\Application\DTOs\ResolvedRecordLinkDTO;
use Modules\Contacts\Application\DTOs\ResolvedRecordLinksDTO;
use Modules\Contacts\Application\Services\RecordLinkServiceInterface;
use Modules\Contacts\Domain\Enums\RecordLinkOutcome;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

/**
 * The «Vínculos» tab of an organization or an agreement: events, tournaments and other records of
 * the site linked to it. Every link is asked to its record's source for the person looking; the
 * table only holds what the source confirms, and the rest is a neutral count at the foot (no type,
 * no title), because a deleted record, one hidden from this person and a module turned off must
 * look the same.
 */
class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    /** Records already asked for in this request (one lookup per link and page view). */
    private ?ResolvedRecordLinksDTO $resolved = null;

    /**
     * Whoever may not manage contacts reads the links, nothing else, whatever page hosts the tab.
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
        return __('contacts::contacts.links.title');
    }

    protected static function getModelLabel(): string
    {
        return __('contacts::contacts.links.singular');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('contacts::contacts.links.plural');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->onlyVisible($query))
            ->columns([
                TextColumn::make('title')
                    ->label(__('contacts::contacts.links.record'))
                    ->state(fn (LinkModel $record): ?string => $this->resolvedLink($record)?->record->title)
                    ->wrap(),

                TextColumn::make('type')
                    ->label(__('contacts::contacts.links.type'))
                    ->state(fn (LinkModel $record): ?string => $this->resolvedLink($record)?->typeLabel)
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('sm'),

                TextColumn::make('date')
                    ->label(__('contacts::contacts.links.date'))
                    ->state(fn (LinkModel $record): ?string => $this->formattedDate($this->resolvedLink($record)?->record))
                    ->placeholder('—')
                    ->visibleFrom('md'),
            ])
            ->recordUrl(fn (LinkModel $record): ?string => $this->resolvedLink($record)?->record->panelUrl)
            ->emptyStateHeading(__('contacts::contacts.links.empty_state'))
            ->emptyStateDescription(fn (): string => $this->resolvedLinks()->unavailable > 0
                ? $this->unavailableCount()
                : __('contacts::contacts.links.empty_state_description'))
            // With nothing visible the empty state already says it: the count is never shown twice
            ->contentFooter(fn (): ?View => $this->resolvedLinks()->unavailable > 0 && $this->resolvedLinks()->visible !== []
                // The module registers its view namespace at runtime, so Larastan cannot resolve it as a view-string
                ? view('contacts::filament.links-unavailable', ['message' => $this->unavailableCount()]) // @phpstan-ignore argument.type
                : null)
            ->headerActions([
                $this->linkAction(),
            ])
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    DeleteAction::make()
                        ->label(__('contacts::contacts.links.delete'))
                        ->icon('heroicon-o-link-slash')
                        ->modalHeading(__('contacts::contacts.links.delete_heading'))
                        ->modalDescription(__('contacts::contacts.links.delete_description'))
                        ->modalSubmitActionLabel(__('contacts::contacts.links.delete'))
                        ->successNotificationTitle(__('contacts::contacts.links.deleted'))
                        ->after(function (): void {
                            $this->resolved = null;
                        }),
                ]),
            ]);
    }

    private function linkAction(): Action
    {
        return Action::make('linkRecord')
            ->label(__('contacts::contacts.links.link'))
            ->icon('heroicon-o-link')
            ->modalHeading(__('contacts::contacts.links.link_heading'))
            ->modalDescription(__('contacts::contacts.links.link_description'))
            ->modalSubmitActionLabel(__('contacts::contacts.links.submit'))
            ->visible(fn (): bool => ! $this->isReadOnly())
            ->schema([
                Select::make('record_type')
                    ->label(__('contacts::contacts.links.record_type'))
                    // Only the sources available right now; Filament rejects any other value
                    ->options(fn (): array => $this->service()->types())
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('record_id', null);
                    }),

                Select::make('record_id')
                    ->label(__('contacts::contacts.links.record_id'))
                    ->helperText(__('contacts::contacts.links.record_id_hint'))
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->visible(fn (Get $get): bool => $this->typeFrom($get) !== null)
                    ->options(fn (Get $get): array => $this->recordOptions($this->typeFrom($get), ''))
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => $this->recordOptions($this->typeFrom($get), $search))
                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => $this->recordOptionLabel($this->typeFrom($get), $value)),
            ])
            ->action(function (array $data): void {
                $this->link($data);
            });
    }

    /**
     * Saves only what the record's source confirms now for this person: the picker's options and
     * whatever the request carries are candidates, never proof.
     *
     * @param  array<mixed>  $data
     */
    private function link(array $data): void
    {
        $user = auth()->user();
        $type = $data['record_type'] ?? null;
        $recordId = $data['record_id'] ?? null;

        $outcome = $user instanceof UserModel && is_string($type) && is_string($recordId)
            ? $this->service()->link($this->owner(), $type, $recordId, $user)
            : RecordLinkOutcome::Unavailable;

        $this->resolved = null;

        $notification = Notification::make()->title(__('contacts::contacts.links.'.$outcome->value));

        match ($outcome) {
            RecordLinkOutcome::Linked => $notification->success(),
            RecordLinkOutcome::AlreadyLinked => $notification->warning(),
            RecordLinkOutcome::Unavailable, RecordLinkOutcome::NotAllowed => $notification->danger(),
        };

        $notification->send();
    }

    /**
     * @param  Builder<LinkModel>  $query
     * @return Builder<LinkModel>
     */
    private function onlyVisible(Builder $query): Builder
    {
        $ids = $this->resolvedLinks()->visibleIds();

        if ($ids === []) {
            return $query->whereKey([]);
        }

        $query->whereKey($ids);

        // Kept in the order the service gave (newest record first): each clause brings the next
        // link up among the rest, with its id as a binding
        foreach ($ids as $id) {
            $query->orderByRaw('CASE WHEN contacts_links.id = ? THEN 0 ELSE 1 END', [$id]);
        }

        return $query;
    }

    private function resolvedLinks(): ResolvedRecordLinksDTO
    {
        if ($this->resolved instanceof ResolvedRecordLinksDTO) {
            return $this->resolved;
        }

        $user = auth()->user();

        return $this->resolved = $user instanceof UserModel
            ? $this->service()->resolve($this->owner(), $user)
            : new ResolvedRecordLinksDTO([], 0);
    }

    private function resolvedLink(LinkModel $link): ?ResolvedRecordLinkDTO
    {
        return $this->resolvedLinks()->find($link->id);
    }

    private function unavailableCount(): string
    {
        $count = $this->resolvedLinks()->unavailable;

        return trans_choice('contacts::contacts.links.unavailable_count', $count, ['count' => $count]);
    }

    /**
     * @return array<string, string>
     */
    private function recordOptions(?string $type, string $search): array
    {
        $user = auth()->user();

        if ($type === null || ! $user instanceof UserModel) {
            return [];
        }

        $options = [];

        foreach ($this->service()->search($type, $search, $user) as $record) {
            $options[$record->id] = $this->optionLabel($record);
        }

        return $options;
    }

    private function recordOptionLabel(?string $type, mixed $value): ?string
    {
        $user = auth()->user();

        if ($type === null || ! is_string($value) || ! $user instanceof UserModel) {
            return null;
        }

        $record = $this->service()->find($type, $value, $user);

        return $record instanceof LinkableRecordDTO ? $this->optionLabel($record) : null;
    }

    private function optionLabel(LinkableRecordDTO $record): string
    {
        $date = $this->formattedDate($record);

        return $date === null ? $record->title : "{$record->title} · {$date}";
    }

    private function formattedDate(?LinkableRecordDTO $record): ?string
    {
        if (! $record instanceof LinkableRecordDTO || ! $record->date instanceof DateTimeImmutable) {
            return null;
        }

        // A calendar day keeps its day; an instant is shown in the display zone
        return $record->dateIsDay
            ? $record->date->format('d/m/Y')
            : app(TimezoneServiceInterface::class)->format($record->date, 'd/m/Y H:i');
    }

    private function typeFrom(Get $get): ?string
    {
        $type = $get('record_type');

        return is_string($type) && $type !== '' ? $type : null;
    }

    private function owner(): OrganizationModel|AgreementModel
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof OrganizationModel && ! $owner instanceof AgreementModel) {
            throw new LogicException('Links hang from an organization or an agreement.');
        }

        return $owner;
    }

    private function service(): RecordLinkServiceInterface
    {
        return app(RecordLinkServiceInterface::class);
    }
}
