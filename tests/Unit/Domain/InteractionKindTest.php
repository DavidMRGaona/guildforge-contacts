<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Domain;

use Modules\Contacts\Domain\Enums\InteractionKind;
use Tests\TestCase;

final class InteractionKindTest extends TestCase
{
    public function test_the_kinds_are_the_ones_the_design_lists(): void
    {
        $this->assertSame(
            ['meeting', 'call', 'email', 'message', 'other'],
            array_keys(InteractionKind::options()),
        );
    }

    public function test_every_case_has_a_spanish_label(): void
    {
        /** @var array{enums: array{interaction_kind: array<string, string>}} $texts */
        $texts = require __DIR__.'/../../../lang/es/contacts.php';

        foreach (InteractionKind::cases() as $kind) {
            $this->assertArrayHasKey($kind->value, $texts['enums']['interaction_kind']);
        }
    }
}
