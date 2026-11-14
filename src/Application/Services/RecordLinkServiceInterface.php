<?php

declare(strict_types=1);

namespace Modules\Contacts\Application\Services;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Contacts\Application\DTOs\ResolvedRecordLinksDTO;
use Modules\Contacts\Domain\Enums\RecordLinkOutcome;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;

/**
 * Links between contacts and records of other parts of the site (events, tournaments…), through
 * the core's linkable record lookup, which fails closed. Nothing here throws because a source
 * is missing or broken: such links are simply not available.
 */
interface RecordLinkServiceInterface
{
    /**
     * Types that can be linked right now, label keyed by type.
     *
     * @return array<string, string>
     */
    public function types(): array;

    /**
     * Records of the type the viewer may see matching the query (the latest ones when empty).
     *
     * @return list<LinkableRecordDTO>
     */
    public function search(string $type, string $query, UserModel $viewer): array;

    /**
     * The record as the viewer may see it, or null.
     */
    public function find(string $type, string $recordId, UserModel $viewer): ?LinkableRecordDTO;

    /**
     * Links the record to the organization, or to the agreement (and so to its organization), only
     * when the viewer may manage contacts and the record's source confirms it for them. The
     * picker's state is never trusted.
     */
    public function link(OrganizationModel|AgreementModel $owner, string $type, string $recordId, UserModel $viewer): RecordLinkOutcome;

    /**
     * The owner's own links as the viewer may see them. An organization's own links are those
     * without an agreement; an agreement's links show on the agreement.
     */
    public function resolve(OrganizationModel|AgreementModel $owner, UserModel $viewer): ResolvedRecordLinksDTO;
}
