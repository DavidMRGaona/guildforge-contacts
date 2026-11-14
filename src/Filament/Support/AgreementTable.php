<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Support;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\AgreementStatus;
use Modules\Contacts\Filament\Actions\ContactActions;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;

/**
 * Form fields, columns and filters of agreements, shared by the «Acuerdos» list and the tab of
 * an organization so both stay identical.
 */
final class AgreementTable
{
    /**
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('title')
                ->label(__('contacts::contacts.fields.title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('kind')
                ->label(__('contacts::contacts.fields.kind'))
                ->options(AgreementKind::options())
                ->default(AgreementKind::Other->value)
                ->required()
                ->native(false),

            Select::make('status')
                ->label(__('contacts::contacts.fields.status'))
                ->options(AgreementStatus::options())
                ->default(AgreementStatus::Active->value)
                ->required()
                ->native(false),

            DatePicker::make('starts_on')
                ->label(__('contacts::contacts.fields.starts_on'))
                ->native(false)
                ->displayFormat('d/m/Y'),

            DatePicker::make('ends_on')
                ->label(__('contacts::contacts.fields.ends_on'))
                ->native(false)
                ->displayFormat('d/m/Y')
                ->afterOrEqual('starts_on'),

            DatePicker::make('review_on')
                ->label(__('contacts::contacts.fields.review_on'))
                ->helperText(__('contacts::contacts.hints.review_on'))
                ->native(false)
                ->displayFormat('d/m/Y'),

            Select::make('responsible_id')
                ->label(__('contacts::contacts.fields.responsible'))
                ->helperText(fn (?Model $record): string => self::responsibleHint($record))
                ->options(fn (?Model $record): array => self::responsibleOptionsFor($record))
                ->searchable()
                ->native(false)
                // Checked again on save: a forged id or a newly chosen account without the permission is
                // refused, but the value already stored stays valid so unrelated edits are not blocked
                ->in(fn (?Model $record): array => array_keys(self::responsibleOptionsFor($record)))
                ->validationMessages(['in' => __('contacts::contacts.validation.responsible')]),

            TextInput::make('document_url')
                ->label(__('contacts::contacts.fields.document_url'))
                ->helperText(__('contacts::contacts.hints.document_url'))
                ->url()
                // `url` alone accepts http, ftp, mailto… and a document link is followed by people
                ->rule('regex:/^https:\/\/\S+$/i')
                ->validationMessages(['regex' => __('contacts::contacts.validation.document_url')])
                ->maxLength(2048),

            Textarea::make('conditions')
                ->label(__('contacts::contacts.fields.conditions'))
                ->rows(4)
                ->maxLength(5000)
                ->columnSpanFull(),
        ];
    }

    /**
     * Accounts that may manage contacts: the only ones that can be newly chosen as responsible.
     *
     * @return array<string, string> id => name
     */
    public static function responsibleOptions(): array
    {
        return app(ResponsibleCandidates::class)->options();
    }

    /**
     * The candidates plus the responsible already stored on the record, even when it lost the
     * permission, so the select does not show empty and a save that leaves it alone passes.
     *
     * @return array<string, string>
     */
    private static function responsibleOptionsFor(?Model $record): array
    {
        $options = self::responsibleOptions();
        $stored = self::storedResponsible($record);

        if ($stored instanceof UserModel && ! array_key_exists((string) $stored->getKey(), $options)) {
            $options[(string) $stored->getKey()] = __('contacts::contacts.hints.responsible_lost_option', ['name' => self::userName($stored)]);
        }

        return $options;
    }

    private static function responsibleHint(?Model $record): string
    {
        $stored = self::storedResponsible($record);

        if ($stored instanceof UserModel && ! app(ResponsibleCandidates::class)->holds((string) $stored->getKey())) {
            return __('contacts::contacts.hints.responsible_lost', ['name' => self::userName($stored)]);
        }

        return __('contacts::contacts.hints.responsible');
    }

    /**
     * The responsible as stored, never the form's pending state.
     */
    private static function storedResponsible(?Model $record): ?UserModel
    {
        if (! $record instanceof AgreementModel || $record->getRawOriginal('responsible_id') === null) {
            return null;
        }

        return UserModel::query()->whereKey($record->getRawOriginal('responsible_id'))->first();
    }

    public static function userName(UserModel $user): string
    {
        return ResponsibleCandidates::userName($user);
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function columns(bool $withOrganization): array
    {
        $columns = [
            TextColumn::make('title')
                ->label(__('contacts::contacts.fields.title'))
                ->wrap()
                ->sortable()
                ->searchable(),
        ];

        if ($withOrganization) {
            $columns[] = TextColumn::make('organization.name')
                ->label(__('contacts::contacts.fields.organization'))
                ->wrap()
                ->sortable()
                ->visibleFrom('sm');
        }

        $columns[] = TextColumn::make('status')
            ->label(__('contacts::contacts.fields.status'))
            ->badge()
            ->formatStateUsing(fn (AgreementStatus $state): string => $state->label())
            ->color(fn (AgreementStatus $state): string => $state->color());

        $columns[] = TextColumn::make('review_on')
            ->label(__('contacts::contacts.fields.review_on'))
            ->date('d/m/Y')
            ->placeholder('—')
            ->sortable()
            // A past review day stands out, but only while the agreement is still open
            ->color(fn (AgreementModel $record): string => $record->isReviewDue(app(TimezoneServiceInterface::class)->today()) ? 'danger' : 'gray')
            ->weight(fn (AgreementModel $record): ?string => $record->isReviewDue(app(TimezoneServiceInterface::class)->today()) ? 'bold' : null)
            ->visibleFrom('sm');

        $columns[] = TextColumn::make('kind')
            ->label(__('contacts::contacts.fields.kind'))
            ->formatStateUsing(fn (AgreementKind $state): string => $state->label())
            ->visibleFrom('md');

        $columns[] = TextColumn::make('responsible.name')
            ->label(__('contacts::contacts.fields.responsible'))
            ->formatStateUsing(fn (mixed $state, AgreementModel $record): string => $record->responsible instanceof UserModel ? self::userName($record->responsible) : '—')
            ->placeholder('—')
            // At lg the fixed sidebar leaves no more room than at md
            ->visibleFrom('xl');

        return $columns;
    }

    /**
     * @return array<int, SelectFilter|Filter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label(__('contacts::contacts.filters.status'))
                ->options(AgreementStatus::options()),

            SelectFilter::make('kind')
                ->label(__('contacts::contacts.filters.kind'))
                ->options(AgreementKind::options()),

            Filter::make('review_due')
                ->label(__('contacts::contacts.filters.review_due'))
                ->toggle()
                ->query(function (Builder $query): Builder {
                    /** @var Builder<AgreementModel> $query */
                    $query->reviewDue(app(TimezoneServiceInterface::class)->today());

                    return $query;
                }),
        ];
    }

    /**
     * One menu, like every table of the module: labelled actions made the tables overflow.
     *
     * @return array<int, ActionGroup>
     */
    public static function recordActions(): array
    {
        return [
            ActionGroup::make([
                RowDetailsAction::make(),
                EditAction::make(),
                ContactActions::deleteAgreement(),
            ]),
        ];
    }
}
