<?php

declare(strict_types=1);

namespace Modules\Contacts\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Modules\Contacts\Application\Services\OrganizationArchiveServiceInterface;
use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

/**
 * The actions of the organization page and list, built once so the row menu and the page header
 * stay identical. Deleting always asks twice: the dialog and a ticked box saying what is lost.
 */
final class ContactActions
{
    public static function archive(): Action
    {
        return Action::make('archive')
            ->label(__('contacts::contacts.actions.archive'))
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('contacts::contacts.actions.archive_heading'))
            ->modalDescription(__('contacts::contacts.actions.archive_description'))
            ->authorize('archive')
            ->visible(fn (OrganizationModel $record): bool => $record->status === OrganizationStatus::Active)
            ->action(function (OrganizationModel $record, OrganizationArchiveServiceInterface $service): void {
                $service->archive($record);

                Notification::make()->title(__('contacts::contacts.actions.archived'))->success()->send();
            });
    }

    public static function unarchive(): Action
    {
        return Action::make('unarchive')
            ->label(__('contacts::contacts.actions.unarchive'))
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('contacts::contacts.actions.unarchive_heading'))
            ->modalDescription(__('contacts::contacts.actions.unarchive_description'))
            ->authorize('unarchive')
            ->visible(fn (OrganizationModel $record): bool => $record->status === OrganizationStatus::Archived)
            ->action(function (OrganizationModel $record, OrganizationArchiveServiceInterface $service): void {
                $service->unarchive($record);

                Notification::make()->title(__('contacts::contacts.actions.unarchived'))->success()->send();
            });
    }

    public static function deleteOrganization(): DeleteAction
    {
        return self::withSecondConfirmation(
            DeleteAction::make(),
            'organization',
        );
    }

    public static function deletePerson(): DeleteAction
    {
        return self::withSecondConfirmation(
            DeleteAction::make(),
            'person',
        );
    }

    /**
     * @param  'organization'|'person'  $subject
     */
    private static function withSecondConfirmation(DeleteAction $action, string $subject): DeleteAction
    {
        return $action
            ->requiresConfirmation()
            ->modalHeading(__('contacts::contacts.delete.'.$subject.'_heading'))
            ->modalDescription(__('contacts::contacts.delete.'.$subject.'_description'))
            ->schema([
                Checkbox::make('confirmation')
                    ->label(__('contacts::contacts.delete.'.$subject.'_check'))
                    ->accepted()
                    ->required(),
            ]);
    }
}
