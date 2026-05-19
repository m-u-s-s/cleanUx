<?php

namespace Tests\Feature;

use App\Livewire\Admin\CatalogueServices;
use App\Models\ServiceCatalog;
use App\Models\ServiceOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminServiceOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions'  => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active'    => true,
        ]);
    }

    protected function makeService(): ServiceCatalog
    {
        return ServiceCatalog::factory()->create([
            'name'      => 'Nettoyage bureaux',
            'slug'      => 'nettoyage-bureaux-' . uniqid(),
            'code'      => 'NB' . substr(uniqid(), -4),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_add_a_number_option_to_a_service(): void
    {
        $service = $this->makeService();
        $this->actingAs($this->createAdmin());

        Livewire::test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->set('newOption.label', 'Surface (m²)')
            ->set('newOption.slug', 'surface_m2')
            ->set('newOption.type', 'number')
            ->set('newOption.unit', 'm²')
            ->set('newOption.price_modifier', 'per_unit')
            ->set('newOption.price_modifier_value', '1.5')
            ->set('newOption.min_value', '20')
            ->set('newOption.max_value', '500')
            ->set('newOption.is_active', true)
            ->call('addOption');

        $this->assertDatabaseHas('service_options', [
            'service_catalog_id'   => $service->id,
            'slug'                 => 'surface_m2',
            'label'                => 'Surface (m²)',
            'type'                 => 'number',
            'price_modifier'       => 'per_unit',
            'price_modifier_value' => 1.5000,
        ]);
    }

    public function test_admin_can_add_a_select_option_with_values(): void
    {
        $service = $this->makeService();
        $this->actingAs($this->createAdmin());

        Livewire::test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->set('newOption.label', 'Fréquence')
            ->set('newOption.slug', 'frequence')
            ->set('newOption.type', 'select')
            ->set('newOption.values_text', "hebdo\nbimensuel\nmensuel")
            ->set('newOption.price_modifier', 'percent')
            ->set('newOption.price_modifier_value', '10')
            ->call('addOption');

        $option = ServiceOption::where('slug', 'frequence')
            ->where('service_catalog_id', $service->id)
            ->first();

        $this->assertNotNull($option);
        $this->assertSame(['hebdo', 'bimensuel', 'mensuel'], $option->values);
    }

    public function test_duplicate_slug_within_same_service_is_rejected(): void
    {
        $service = $this->makeService();
        ServiceOption::create([
            'service_catalog_id' => $service->id,
            'slug'               => 'frigo',
            'label'              => 'Frigo',
            'type'               => 'boolean',
            'price_modifier'     => 'fixed',
            'price_modifier_value' => 5,
        ]);

        $this->actingAs($this->createAdmin());

        Livewire::test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->set('newOption.label', 'Frigo 2')
            ->set('newOption.slug', 'frigo') // doublon
            ->set('newOption.type', 'boolean')
            ->set('newOption.price_modifier', 'fixed')
            ->set('newOption.price_modifier_value', '7')
            ->call('addOption')
            ->assertHasErrors(['newOption.slug']);

        $this->assertSame(1, ServiceOption::where('service_catalog_id', $service->id)->count());
    }

    public function test_admin_can_edit_an_existing_option(): void
    {
        $service = $this->makeService();
        $option = ServiceOption::create([
            'service_catalog_id' => $service->id,
            'slug'               => 'repassage',
            'label'              => 'Repassage',
            'type'               => 'boolean',
            'price_modifier'     => 'fixed',
            'price_modifier_value' => 20,
            'is_active'          => true,
        ]);

        $this->actingAs($this->createAdmin());

        Livewire::test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->call('editOption', $option->id)
            ->set("serviceOptions.$option->id.label", 'Repassage (panier)')
            ->set("serviceOptions.$option->id.price_modifier_value", '25')
            ->call('saveOption', $option->id);

        $option->refresh();
        $this->assertSame('Repassage (panier)', $option->label);
        $this->assertSame('25.0000', (string) $option->price_modifier_value);
    }

    public function test_admin_can_toggle_and_delete_an_option(): void
    {
        $service = $this->makeService();
        $option = ServiceOption::create([
            'service_catalog_id' => $service->id,
            'slug'               => 'vitres_ext',
            'label'              => 'Vitres extérieures',
            'type'               => 'boolean',
            'price_modifier'     => 'fixed',
            'price_modifier_value' => 15,
            'is_active'          => true,
        ]);

        $this->actingAs($this->createAdmin());

        Livewire::test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->call('toggleOptionActive', $option->id);

        $this->assertFalse($option->fresh()->is_active);

        Livewire::test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->call('deleteOption', $option->id);

        $this->assertDatabaseMissing('service_options', ['id' => $option->id]);
    }

    public function test_invalid_type_or_modifier_is_rejected(): void
    {
        $service = $this->makeService();
        $this->actingAs($this->createAdmin());

        Livewire::test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->set('newOption.label', 'X')
            ->set('newOption.slug', 'x')
            ->set('newOption.type', 'not_a_type')
            ->set('newOption.price_modifier', 'wat')
            ->call('addOption')
            ->assertHasErrors(['newOption.type', 'newOption.price_modifier']);
    }

    public function test_non_admin_cannot_add_option(): void
    {
        $service = $this->makeService();
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->actingAs($client);

        try {
            Livewire::test(CatalogueServices::class)
                ->set('selectedServiceId', $service->id)
                ->set('newOption.label', 'Vitres')
                ->set('newOption.slug', 'vitres')
                ->set('newOption.type', 'boolean')
                ->set('newOption.price_modifier', 'fixed')
                ->set('newOption.price_modifier_value', '10')
                ->call('addOption');
        } catch (\Throwable $e) {
            $this->assertTrue(
                $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || str_contains(strtolower($e->getMessage()), 'unauthorized')
                || str_contains(strtolower($e->getMessage()), 'forbidden'),
                'Une exception d\'autorisation devrait être levée.'
            );
        }

        $this->assertDatabaseMissing('service_options', [
            'service_catalog_id' => $service->id,
            'slug'               => 'vitres',
        ]);
    }
}
