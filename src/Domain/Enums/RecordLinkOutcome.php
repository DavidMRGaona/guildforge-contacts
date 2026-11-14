<?php

declare(strict_types=1);

namespace Modules\Contacts\Domain\Enums;

/**
 * What came of asking to link a record. «Unavailable» covers, on purpose, a record that does not
 * exist, one the person may not see and a type with no source right now: telling them apart
 * would reveal that a hidden record exists.
 */
enum RecordLinkOutcome: string
{
    case Linked = 'linked';
    case AlreadyLinked = 'already_linked';
    case Unavailable = 'unavailable';
    case NotAllowed = 'not_allowed';
}
