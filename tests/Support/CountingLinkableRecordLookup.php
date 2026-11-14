<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Support;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;

/**
 * Wraps the core's lookup and remembers every call, to prove how many questions a page asks.
 */
final class CountingLinkableRecordLookup implements LinkableRecordLookupInterface
{
    /** @var list<array{type: string, ids: list<string>}> */
    public array $findManyCalls = [];

    /** @var list<array{type: string, id: string}> */
    public array $findCalls = [];

    public function __construct(private readonly LinkableRecordLookupInterface $inner) {}

    public function find(string $type, string $id, string $viewerId): ?LinkableRecordDTO
    {
        $this->findCalls[] = ['type' => $type, 'id' => $id];

        return $this->inner->find($type, $id, $viewerId);
    }

    public function findMany(string $type, array $ids, string $viewerId): array
    {
        $this->findManyCalls[] = ['type' => $type, 'ids' => $ids];

        return $this->inner->findMany($type, $ids, $viewerId);
    }

    public function search(string $type, string $query, string $viewerId, int $limit = self::DEFAULT_SEARCH_LIMIT): array
    {
        return $this->inner->search($type, $query, $viewerId, $limit);
    }

    public function types(): array
    {
        return $this->inner->types();
    }
}
