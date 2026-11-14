<?php

declare(strict_types=1);

namespace Modules\Contacts\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\Application\DTOs\ResolvedRecordLinkDTO;
use Modules\Contacts\Application\DTOs\ResolvedRecordLinksDTO;
use Modules\Contacts\Application\Services\RecordLinkServiceInterface;
use Modules\Contacts\Domain\Enums\RecordLinkOutcome;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

final readonly class RecordLinkService implements RecordLinkServiceInterface
{
    private const string MANAGE = 'contacts:contacts.manage';

    /** Size of the record_type column. */
    private const int MAX_TYPE_LENGTH = 100;

    public function __construct(
        private LinkableRecordLookupInterface $lookup,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function types(): array
    {
        return $this->lookup->types();
    }

    public function search(string $type, string $query, UserModel $viewer): array
    {
        if (! $this->fitsType($type)) {
            return [];
        }

        return $this->lookup->search($type, $query, $viewer->id);
    }

    public function find(string $type, string $recordId, UserModel $viewer): ?LinkableRecordDTO
    {
        if (! $this->fitsType($type) || mb_strlen($recordId) > LinkableRecordDTO::MAX_ID_LENGTH) {
            return null;
        }

        return $this->lookup->find($type, $recordId, $viewer->id);
    }

    public function link(OrganizationModel|AgreementModel $owner, string $type, string $recordId, UserModel $viewer): RecordLinkOutcome
    {
        if (! $this->authorization->can($viewer, self::MANAGE)) {
            return RecordLinkOutcome::NotAllowed;
        }

        // Asked again here, for this viewer: whatever the picker sent is only a candidate
        $record = $this->find($type, $recordId, $viewer);

        if (! $record instanceof LinkableRecordDTO) {
            return RecordLinkOutcome::Unavailable;
        }

        $attributes = [
            'organization_id' => $owner instanceof AgreementModel ? $owner->organization_id : $owner->id,
            'agreement_id' => $owner instanceof AgreementModel ? $owner->id : null,
            'record_type' => $record->type,
            'record_id' => $record->id,
        ];

        if (LinkModel::query()->where($attributes)->exists()) {
            return RecordLinkOutcome::AlreadyLinked;
        }

        try {
            // A savepoint of its own: a duplicate from a parallel request must not spoil an outer
            // transaction in PostgreSQL
            DB::transaction(fn (): LinkModel => LinkModel::create($attributes + ['created_by' => $viewer->id]));
        } catch (UniqueConstraintViolationException) {
            return RecordLinkOutcome::AlreadyLinked;
        }

        return RecordLinkOutcome::Linked;
    }

    public function resolve(OrganizationModel|AgreementModel $owner, UserModel $viewer): ResolvedRecordLinksDTO
    {
        $links = $owner->links()->get(['id', 'record_type', 'record_id']);
        $labels = $this->lookup->types();

        /** @var array<string, LinkableRecordDTO|null> $records */
        $records = [];
        $visible = [];
        $unavailable = 0;

        foreach ($links as $link) {
            // The contract has no batch lookup: each record is asked once per page view
            $key = $link->record_type."\0".$link->record_id;

            if (! array_key_exists($key, $records)) {
                $records[$key] = $this->find($link->record_type, $link->record_id, $viewer);
            }

            $record = $records[$key];
            $label = $labels[$link->record_type] ?? null;

            // Missing, hidden from this viewer, source gone or failing, type unnamed: all alike
            if (! $record instanceof LinkableRecordDTO || $label === null) {
                $unavailable++;

                continue;
            }

            $visible[] = new ResolvedRecordLinkDTO($link->id, $label, $record);
        }

        usort($visible, $this->newestFirst(...));

        $keyed = [];

        foreach ($visible as $resolved) {
            $keyed[$resolved->linkId] = $resolved;
        }

        return new ResolvedRecordLinksDTO($keyed, $unavailable);
    }

    /**
     * Newest record first, undated ones last, then by title.
     */
    private function newestFirst(ResolvedRecordLinkDTO $a, ResolvedRecordLinkDTO $b): int
    {
        $dateA = $a->record->date?->getTimestamp();
        $dateB = $b->record->date?->getTimestamp();

        if ($dateA !== $dateB) {
            return match (true) {
                $dateA === null => 1,
                $dateB === null => -1,
                default => $dateB <=> $dateA,
            };
        }

        return [mb_strtolower($a->record->title), $a->linkId] <=> [mb_strtolower($b->record->title), $b->linkId];
    }

    /**
     * A type or id longer than its column was never stored and can never be.
     */
    private function fitsType(string $type): bool
    {
        return mb_strlen($type) <= self::MAX_TYPE_LENGTH;
    }
}
