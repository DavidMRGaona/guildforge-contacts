<?php

declare(strict_types=1);

namespace Modules\Contacts\Application\DTOs;

/**
 * The links of an organization or agreement as one person may see them: the visible ones, in the
 * order to show them, and how many others are stored but not available to them (record deleted or
 * hidden from them, module not active, source failing). Those are only counted, never described.
 */
final readonly class ResolvedRecordLinksDTO
{
    /**
     * @param  array<string, ResolvedRecordLinkDTO>  $visible  Keyed by link id, newest record first
     */
    public function __construct(
        public array $visible,
        public int $unavailable,
    ) {}

    /**
     * @return list<string>
     */
    public function visibleIds(): array
    {
        return array_keys($this->visible);
    }

    public function find(string $linkId): ?ResolvedRecordLinkDTO
    {
        return $this->visible[$linkId] ?? null;
    }
}
