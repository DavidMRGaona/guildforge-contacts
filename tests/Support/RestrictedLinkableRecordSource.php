<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Support;

use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;

/**
 * A source whose records only some viewers may see, as a module's drafts would be.
 */
final class RestrictedLinkableRecordSource implements LinkableRecordSourceInterface
{
    public const string TYPE = 'restricted';

    /** @var list<LinkableRecordDTO> */
    public static array $records = [];

    /** @var list<string> */
    public static array $allowedViewers = [];

    public static function reset(): void
    {
        self::$records = [];
        self::$allowedViewers = [];
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return 'Restricted record';
    }

    public function search(string $query, string $viewerId, int $limit): array
    {
        return in_array($viewerId, self::$allowedViewers, true) ? array_slice(self::$records, 0, $limit) : [];
    }

    public function find(string $id, string $viewerId): ?LinkableRecordDTO
    {
        if (! in_array($viewerId, self::$allowedViewers, true)) {
            return null;
        }

        foreach (self::$records as $record) {
            if ($record->id === $id) {
                return $record;
            }
        }

        return null;
    }
}
