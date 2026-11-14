<?php

declare(strict_types=1);

namespace Modules\Contacts\Application\DTOs;

use App\Application\Linking\DTOs\LinkableRecordDTO;

/**
 * A stored link its source confirmed for the person looking: the record as they may see it and
 * the label of its type.
 */
final readonly class ResolvedRecordLinkDTO
{
    public function __construct(
        public string $linkId,
        public string $typeLabel,
        public LinkableRecordDTO $record,
    ) {}
}
