<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Knowledge;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\Resources\AgreementResource;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\CreateAgreement;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\EditAgreement;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\ListAgreements;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\ViewAgreement;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\CreateOrganization;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\ListOrganizations;
use Modules\Contacts\Filament\Resources\OrganizationResource\Pages\ViewOrganization;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Who sees the panel guide, and the help button on the module's pages.
 */
final class ContactsGuideTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string GUIDE = 'contacts.panel.contacts';

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([OrganizationResource::class, AgreementResource::class]);
        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    public function test_the_guides_are_valid(): void
    {
        $this->assertModuleKnowledgeGuidesAreValid('contacts');
    }

    public function test_an_admin_sees_the_guide_rendered_without_raw_markup(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $guide = $this->queries()->guide($admin, GuideAudience::Panel, GuideId::fromString(self::GUIDE));

        $this->assertNotNull($guide);
        $this->assertStringNotContainsString('[[', $guide->html);
        $this->assertStringNotContainsString('{{', $guide->html);
        $this->assertStringNotContainsString('[!', $guide->html);
    }

    public function test_only_who_holds_one_of_the_permissions_sees_the_guide(): void
    {
        $this->assertTrue($this->sees($this->userWith('contacts:contacts.view_any')));
        $this->assertTrue($this->sees($this->userWith('contacts:contacts.manage')));
        $this->assertFalse($this->sees(UserModel::factory()->editor()->create()));
    }

    public function test_the_pages_open_the_guide(): void
    {
        $this->actingAsPanelAdmin();
        $organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
        $agreement = $organization->agreements()->create(['title' => 'Descuento', 'kind' => AgreementKind::Discount]);
        $org = ['record' => $organization->getRouteKey()];
        $agr = ['record' => $agreement->getRouteKey()];

        $pages = [
            [ListOrganizations::class, []],
            [CreateOrganization::class, []],
            [ViewOrganization::class, $org],
            [EditOrganization::class, $org],
            [ListAgreements::class, []],
            [CreateAgreement::class, []],
            [ViewAgreement::class, $agr],
            [EditAgreement::class, $agr],
        ];

        foreach ($pages as [$page, $parameters]) {
            Livewire::test($page, $parameters)
                ->assertActionVisible('contextualHelp')
                ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => self::GUIDE]));
        }
    }

    public function test_someone_who_only_reads_contacts_gets_the_help_button(): void
    {
        $this->actingAs($this->userWith('contacts:contacts.view_any'));

        Livewire::test(ListOrganizations::class)->assertActionVisible('contextualHelp');
    }

    private function sees(UserModel $user): bool
    {
        return in_array(self::GUIDE, $this->queries()->visibleIds($user, GuideAudience::Panel), true);
    }

    private function userWith(string $permissionKey): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);
        $permission = PermissionModel::query()->firstOrCreate(
            ['key' => $permissionKey],
            ['label' => $permissionKey, 'resource' => 'contacts', 'action' => 'test', 'module' => 'contacts'],
        );
        $role->permissions()->attach($permission->id);
        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return $this->app->make(KnowledgeQueryServiceInterface::class);
    }
}
