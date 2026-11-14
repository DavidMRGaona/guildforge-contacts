<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LogicException;
use Modules\Contacts\Domain\Enums\AgreementKind;
use Modules\Contacts\Domain\Enums\OrganizationType;
use Modules\Contacts\Filament\Resources\AgreementResource;
use Modules\Contacts\Filament\Resources\AgreementResource\Pages\EditAgreement;
use Modules\Contacts\Filament\Resources\OrganizationResource;
use Modules\Contacts\Filament\Support\ResponsibleCandidates;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\AgreementModel;
use Modules\Contacts\Infrastructure\Persistence\Eloquent\Models\OrganizationModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class AgreementEditTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'contacts';

    protected bool $autoEnableModule = true;

    private OrganizationModel $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([OrganizationResource::class, AgreementResource::class]);
        $this->actingAsPanelAdmin();
        $this->organization = OrganizationModel::create(['name' => 'Tienda', 'type' => OrganizationType::Shop]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agreement(array $attributes = []): AgreementModel
    {
        return $this->organization->agreements()->create($attributes + ['title' => 'Descuento', 'kind' => AgreementKind::Discount]);
    }

    /**
     * @return Testable<Component>
     */
    private function edit(AgreementModel $agreement): Testable
    {
        return Livewire::test(EditAgreement::class, ['record' => $agreement->getRouteKey()]);
    }

    public function test_the_organization_is_fixed_once_the_agreement_exists(): void
    {
        $other = OrganizationModel::create(['name' => 'Otra', 'type' => OrganizationType::Other]);
        $agreement = $this->agreement();

        $this->edit($agreement)->assertFormFieldIsDisabled('organization_id');

        $this->edit($agreement)
            ->fillForm(['title' => 'Nuevo título', 'organization_id' => $other->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $agreement->refresh();
        $this->assertSame('Nuevo título', $agreement->title);
        $this->assertSame($this->organization->getKey(), $agreement->organization_id);
    }

    public function test_the_model_refuses_to_move_an_agreement_to_another_organization(): void
    {
        $other = OrganizationModel::create(['name' => 'Otra', 'type' => OrganizationType::Other]);
        $agreement = $this->agreement();

        $this->expectException(LogicException::class);
        $agreement->update(['organization_id' => $other->getKey()]);
    }

    public function test_an_unrelated_edit_keeps_a_responsible_who_lost_the_permission(): void
    {
        $former = $this->editorWithPermissions(['contacts:contacts.view_any']);
        $agreement = $this->agreement(['responsible_id' => $former->getKey()]);

        $this->edit($agreement)
            ->assertSee('ya no puede gestionar contactos')
            ->assertSee('(sin permiso)')
            ->fillForm(['title' => 'Otro título'])
            ->call('save')
            ->assertHasNoFormErrors();

        $agreement->refresh();
        $this->assertSame('Otro título', $agreement->title);
        $this->assertSame($former->getKey(), $agreement->responsible_id);
    }

    public function test_a_newly_chosen_responsible_without_the_permission_is_still_refused(): void
    {
        $former = $this->editorWithPermissions(['contacts:contacts.view_any']);
        $other = $this->editorWithPermissions(['contacts:contacts.view_any']);
        $manager = $this->editorWithPermissions(['contacts:contacts.manage']);
        $agreement = $this->agreement(['responsible_id' => $former->getKey()]);

        $this->edit($agreement)
            ->fillForm(['responsible_id' => $other->getKey()])
            ->call('save')
            ->assertHasFormErrors(['responsible_id']);

        $this->edit($agreement)
            ->fillForm(['responsible_id' => $manager->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($manager->getKey(), $agreement->refresh()->responsible_id);
    }

    public function test_a_responsible_who_still_holds_the_permission_shows_no_warning(): void
    {
        $manager = $this->editorWithPermissions(['contacts:contacts.manage']);
        $agreement = $this->agreement(['responsible_id' => $manager->getKey()]);

        $this->edit($agreement)->assertDontSee('ya no puede gestionar contactos');
    }

    public function test_the_holders_are_looked_up_once_per_request(): void
    {
        $this->editorWithPermissions(['contacts:contacts.manage']);
        $candidates = app(ResponsibleCandidates::class);

        $first = $candidates->options();
        $this->assertInstanceOf(UserModel::class, $this->editorWithPermissions(['contacts:contacts.manage']));

        $this->assertSame($candidates, app(ResponsibleCandidates::class));
        $this->assertSame($first, $candidates->options());
    }
}
