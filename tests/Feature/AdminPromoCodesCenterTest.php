<?php

namespace Tests\Feature;

use App\Livewire\Admin\Promotions\PromoCodesCenter;
use App\Models\PromoCampaign;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Tests Livewire admin pour le centre de gestion des codes promo. */
class AdminPromoCodesCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-promotions', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    public function test_render_lists_existing_promo_codes(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        PromoCode::factory()->create(['code' => 'WELCOME10', 'name' => 'Bienvenue']);
        PromoCode::factory()->create(['code' => 'SUMMER20', 'name' => 'Été']);

        Livewire::test(PromoCodesCenter::class)
            ->assertOk()
            ->assertSee('WELCOME10')
            ->assertSee('SUMMER20');
    }

    public function test_admin_can_create_a_promo_code(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(PromoCodesCenter::class)
            ->set('code', 'newcode15')
            ->set('name', 'Nouvelle remise')
            ->set('discount_type', PromoCode::TYPE_PERCENT)
            ->set('discount_value', 15)
            ->set('max_uses_per_user', 2)
            ->set('status', PromoCode::STATUS_ACTIVE)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'NEWCODE15',
            'name' => 'Nouvelle remise',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'status' => PromoCode::STATUS_ACTIVE,
            'source' => PromoCode::SOURCE_MANUAL,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_create_with_campaign_sets_campaign_source(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $campaign = PromoCampaign::factory()->create(['name' => 'Campagne Test']);

        Livewire::test(PromoCodesCenter::class)
            ->set('code', 'CAMP30')
            ->set('discount_type', PromoCode::TYPE_FIXED)
            ->set('discount_value', 30)
            ->set('promo_campaign_id', $campaign->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'CAMP30',
            'promo_campaign_id' => $campaign->id,
            'source' => PromoCode::SOURCE_CAMPAIGN,
        ]);
    }

    public function test_percent_discount_above_100_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(PromoCodesCenter::class)
            ->set('code', 'TOOHIGH')
            ->set('discount_type', PromoCode::TYPE_PERCENT)
            ->set('discount_value', 150)
            ->call('save')
            ->assertHasErrors('discount_value');

        $this->assertDatabaseMissing('promo_codes', ['code' => 'TOOHIGH']);
    }

    public function test_short_code_fails_validation(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(PromoCodesCenter::class)
            ->set('code', 'AB')
            ->call('save')
            ->assertHasErrors('code');

        $this->assertDatabaseMissing('promo_codes', ['code' => 'AB']);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        PromoCode::factory()->create(['code' => 'EXISTING']);

        Livewire::test(PromoCodesCenter::class)
            ->set('code', 'existing')
            ->set('discount_value', 10)
            ->call('save')
            ->assertHasErrors('code');

        $this->assertSame(1, PromoCode::where('code', 'EXISTING')->count());
    }

    public function test_edit_loads_form_then_save_updates(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $promo = PromoCode::factory()->create([
            'code' => 'EDITME',
            'name' => 'Avant',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 12,
            'status' => PromoCode::STATUS_DRAFT,
        ]);

        Livewire::test(PromoCodesCenter::class)
            ->call('edit', $promo->id)
            ->assertSet('editingId', $promo->id)
            ->assertSet('code', 'EDITME')
            ->assertSet('name', 'Avant')
            ->set('name', 'Après')
            ->set('discount_value', 25)
            ->call('save')
            ->assertHasNoErrors();

        $promo->refresh();
        $this->assertSame('Après', $promo->name);
        $this->assertSame('25.00', (string) $promo->discount_value);
    }

    public function test_archive_action_sets_archived_status(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $promo = PromoCode::factory()->create(['status' => PromoCode::STATUS_ACTIVE]);

        Livewire::test(PromoCodesCenter::class)
            ->call('archive', $promo->id)
            ->assertDispatched('toast');

        $this->assertSame(PromoCode::STATUS_ARCHIVED, $promo->fresh()->status);
    }

    public function test_activate_action_sets_active_status(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $promo = PromoCode::factory()->create(['status' => PromoCode::STATUS_DRAFT]);

        Livewire::test(PromoCodesCenter::class)
            ->call('activate', $promo->id)
            ->assertDispatched('toast');

        $this->assertSame(PromoCode::STATUS_ACTIVE, $promo->fresh()->status);
    }

    public function test_generate_random_code_fills_code(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $component = Livewire::test(PromoCodesCenter::class)
            ->assertSet('code', '')
            ->call('generateRandomCode');

        $code = $component->get('code');
        $this->assertSame(8, strlen($code));
        $this->assertSame(strtoupper($code), $code);
    }

    public function test_reset_form_restores_defaults(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(PromoCodesCenter::class)
            ->set('code', 'TEMP')
            ->set('discount_value', 99)
            ->set('status', PromoCode::STATUS_ACTIVE)
            ->call('resetForm')
            ->assertSet('code', '')
            ->assertSet('editingId', null)
            ->assertSet('discount_type', PromoCode::TYPE_PERCENT)
            ->assertSet('discount_value', 10.0)
            ->assertSet('status', PromoCode::STATUS_DRAFT)
            ->assertSet('audience_scope', PromoCode::SCOPE_ALL);
    }

    public function test_search_and_status_filters_narrow_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        PromoCode::factory()->create(['code' => 'ALPHA10', 'status' => PromoCode::STATUS_ACTIVE]);
        PromoCode::factory()->create(['code' => 'BETA20', 'status' => PromoCode::STATUS_PAUSED]);

        Livewire::test(PromoCodesCenter::class)
            ->set('search', 'ALPHA')
            ->assertSee('ALPHA10')
            ->assertDontSee('BETA20')
            ->set('search', '')
            ->set('filterStatus', PromoCode::STATUS_PAUSED)
            ->assertSee('BETA20')
            ->assertDontSee('ALPHA10');
    }

    public function test_campaign_filter_narrows_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $campaign = PromoCampaign::factory()->create();
        PromoCode::factory()->create(['code' => 'INCAMP', 'promo_campaign_id' => $campaign->id]);
        PromoCode::factory()->create(['code' => 'NOCAMP', 'promo_campaign_id' => null]);

        Livewire::test(PromoCodesCenter::class)
            ->set('filterCampaignId', $campaign->id)
            ->assertSee('INCAMP')
            ->assertDontSee('NOCAMP');
    }
}
