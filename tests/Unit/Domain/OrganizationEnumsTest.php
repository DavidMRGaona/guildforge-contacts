<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Domain;

use Modules\Contacts\Domain\Enums\OrganizationStatus;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Tests\TestCase;

final class OrganizationEnumsTest extends TestCase
{
    public function test_the_types_are_the_ones_the_design_lists(): void
    {
        $this->assertSame(
            ['supplier', 'shop', 'association', 'institution', 'sponsor', 'media', 'other'],
            array_keys(OrganizationType::options()),
        );
    }

    public function test_the_statuses_are_active_and_archived(): void
    {
        $this->assertSame(['active', 'archived'], array_keys(OrganizationStatus::options()));
    }

    public function test_every_case_has_a_spanish_label(): void
    {
        /** @var array{enums: array{type: array<string, string>, status: array<string, string>}} $texts */
        $texts = require __DIR__.'/../../../lang/es/contacts.php';

        foreach (OrganizationType::cases() as $type) {
            $this->assertArrayHasKey($type->value, $texts['enums']['type']);
        }
        foreach (OrganizationStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $texts['enums']['status']);
        }
    }
}
