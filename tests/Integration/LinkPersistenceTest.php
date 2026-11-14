<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Integration;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPersistenceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private OrganizationModel $organization;

    private AgreementModel $agreement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
        $this->agreement = $this->organization->agreements()->create(['title' => 'Sala', 'kind' => AgreementKind::Space]);
    }

    private function link(?AgreementModel $agreement = null, string $recordId = 't-1'): LinkModel
    {
        return LinkModel::create([
            'organization_id' => $this->organization->id,
            'agreement_id' => $agreement?->id,
            'record_type' => 'tournament',
            'record_id' => $recordId,
        ]);
    }

    public function test_it_keeps_only_the_type_and_id_of_the_record(): void
    {
        $link = $this->link();

        $this->assertTrue(DB::getSchemaBuilder()->hasColumns('contacts_links', ['organization_id', 'agreement_id', 'record_type', 'record_id', 'created_by', 'created_at', 'updated_at']));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('contacts_links', 'title'));
        $this->assertSame('tournament', $link->fresh()?->record_type);
    }

    public function test_deleting_the_organization_deletes_its_links(): void
    {
        $this->link();
        $this->link($this->agreement);

        $this->organization->delete();

        $this->assertDatabaseCount('contacts_links', 0);
    }

    public function test_deleting_an_agreement_deletes_only_its_links(): void
    {
        $own = $this->link();
        $this->link($this->agreement);

        $this->agreement->delete();

        $this->assertSame([$own->id], LinkModel::query()->pluck('id')->all());
    }

    public function test_an_organization_links_the_same_record_once(): void
    {
        $this->link();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->link();
    }

    public function test_an_agreement_links_the_same_record_once(): void
    {
        $this->link($this->agreement);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->link($this->agreement);
    }

    public function test_the_organization_and_its_agreement_may_link_the_same_record(): void
    {
        $this->link();
        $this->link($this->agreement);

        $this->assertDatabaseCount('contacts_links', 2);
    }

    public function test_removing_the_account_that_linked_keeps_the_link(): void
    {
        $user = UserModel::factory()->create();
        $link = LinkModel::create([
            'organization_id' => $this->organization->id,
            'record_type' => 'tournament',
            'record_id' => 't-1',
            'created_by' => $user->id,
        ]);

        $user->forceDelete();

        $this->assertNull($link->fresh()?->created_by);
    }
}
