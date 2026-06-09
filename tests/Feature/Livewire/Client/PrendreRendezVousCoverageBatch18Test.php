<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\BookingFavorite;
use App\Models\CustomerProfile;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrendreRendezVousCoverageBatch18Test extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->client()->create();
    }

    private function premiumClientUser(): User
    {
        $client = User::factory()->client()->create();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        return $client->fresh();
    }

    public function test_preview_promo_code_handles_empty_and_missing_estimate_branches(): void
    {
        $this->actingAs($this->clientUser());

        // Empty code → silent reset, no message.
        Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', '   ')
            ->call('previewPromoCode')
            ->assertSet('promo_valid', false)
            ->assertSet('promo_message', null);

        // Code present but no estimate → guard message.
        Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', 'WELCOME10')
            ->set('devis_estime', 0)
            ->call('previewPromoCode')
            ->assertSet('promo_valid', false)
            ->assertSet('promo_message', 'Estimation indisponible pour évaluer le code.');
    }

    public function test_preview_promo_code_reports_invalid_code_when_estimate_present(): void
    {
        $this->actingAs($this->clientUser());

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', 'DOES-NOT-EXIST')
            ->set('devis_estime', 120.0)
            ->call('previewPromoCode')
            ->assertSet('promo_valid', false);

        $this->assertNotNull($component->get('promo_message'));
    }

    public function test_clear_promo_code_resets_all_promo_state(): void
    {
        $this->actingAs($this->clientUser());

        Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', 'ABC')
            ->set('promo_message', 'something')
            ->set('promo_valid', true)
            ->set('promo_discount_preview', 5.0)
            ->call('clearPromoCode')
            ->assertSet('promo_code', null)
            ->assertSet('promo_message', null)
            ->assertSet('promo_valid', false)
            ->assertSet('promo_discount_preview', null);
    }

    public function test_rebook_favorite_sets_preferred_provider_when_owned(): void
    {
        $client = $this->clientUser();
        $provider = User::factory()->employe()->create();
        $this->actingAs($client);

        $favorite = BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $provider->id,
        ]);

        Livewire::test(PrendreRendezVous::class)
            ->call('rebookFavorite', $favorite->id)
            ->assertSet('preferredProviderUserId', $provider->id)
            ->assertSet('preferredProviderMessage', null)
            ->assertSet('preferredProviderAlternativeSlots', []);
    }

    public function test_rebook_favorite_ignores_favorites_of_other_clients(): void
    {
        $client = $this->clientUser();
        $other = $this->clientUser();
        $provider = User::factory()->employe()->create();
        $this->actingAs($client);

        $foreignFavorite = BookingFavorite::query()->create([
            'client_user_id' => $other->id,
            'preferred_provider_user_id' => $provider->id,
        ]);

        Livewire::test(PrendreRendezVous::class)
            ->call('rebookFavorite', $foreignFavorite->id)
            ->assertSet('preferredProviderUserId', null);
    }

    public function test_pick_preferred_provider_sets_state_and_closes_picker(): void
    {
        $provider = User::factory()->employe()->create();
        $this->actingAs($this->clientUser());

        Livewire::test(PrendreRendezVous::class)
            ->set('showProviderPicker', true)
            ->call('pickPreferredProvider', $provider->id)
            ->assertSet('preferredProviderUserId', $provider->id)
            ->assertSet('showProviderPicker', false);
    }

    public function test_company_selection_event_clears_worker_and_sets_company(): void
    {
        $this->actingAs($this->clientUser());

        Livewire::test(PrendreRendezVous::class)
            ->set('preferredProviderUserId', 99)
            ->set('showProviderPicker', true)
            ->call('onCompanySelected', 42)
            ->assertSet('assignedProviderOrganizationId', 42)
            ->assertSet('preferredProviderUserId', null)
            ->assertSet('showProviderPicker', false);
    }

    public function test_clear_assigned_company_and_clear_preferred_provider(): void
    {
        $this->actingAs($this->clientUser());

        Livewire::test(PrendreRendezVous::class)
            ->set('assignedProviderOrganizationId', 7)
            ->call('clearAssignedCompany')
            ->assertSet('assignedProviderOrganizationId', null);

        Livewire::test(PrendreRendezVous::class)
            ->set('preferredProviderUserId', 3)
            ->set('preferredProviderMessage', 'msg')
            ->set('preferredProviderAlternativeSlots', [['date' => '2030-01-01', 'heure' => '10:00']])
            ->call('clearPreferredProvider')
            ->assertSet('preferredProviderUserId', null)
            ->assertSet('preferredProviderMessage', null)
            ->assertSet('preferredProviderAlternativeSlots', []);
    }

    public function test_toggle_provider_picker_flips_the_flag(): void
    {
        $this->actingAs($this->clientUser());

        Livewire::test(PrendreRendezVous::class)
            ->call('toggleProviderPicker')
            ->assertSet('showProviderPicker', true)
            ->call('toggleProviderPicker')
            ->assertSet('showProviderPicker', false);
    }

    public function test_client_favorites_property_returns_owned_favorites(): void
    {
        $client = $this->clientUser();
        $provider = User::factory()->employe()->create(['name' => 'Star Cleaner']);
        $this->actingAs($client);

        BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $provider->id,
        ]);

        $favorites = Livewire::test(PrendreRendezVous::class)
            ->instance()
            ->getClientFavoritesProperty();

        $this->assertCount(1, $favorites);
        $this->assertSame($provider->id, $favorites->first()->preferred_provider_user_id);
    }

    public function test_select_trade_resets_service_and_schema_then_clear_trade(): void
    {
        $this->actingAs($this->clientUser());
        $trade = Trade::factory()->create();

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', 'something')
            ->call('selectTrade', $trade->id)
            ->assertSet('selectedTradeId', $trade->id)
            ->assertSet('selected_service_identifier', null);

        // Same trade again → early return, value unchanged.
        $component
            ->call('selectTrade', $trade->id)
            ->assertSet('selectedTradeId', $trade->id);

        $component
            ->call('clearTrade')
            ->assertSet('selectedTradeId', null)
            ->assertSet('selected_service_identifier', null);
    }

    public function test_available_trades_property_lists_active_trades(): void
    {
        $this->actingAs($this->clientUser());
        $active = Trade::factory()->create(['is_active' => true, 'icon' => '', 'sort_order' => 1]);
        Trade::factory()->create(['is_active' => false]);

        $trades = Livewire::test(PrendreRendezVous::class)
            ->instance()
            ->getAvailableTradesProperty();

        $ids = array_column($trades, 'id');
        $this->assertContains($active->id, $ids);
        // Icon falls back to 'briefcase' when empty.
        $row = collect($trades)->firstWhere('id', $active->id);
        $this->assertSame('briefcase', $row['icon']);
    }

    public function test_services_for_selected_trade_property_branches(): void
    {
        $this->actingAs($this->clientUser());

        $instance = Livewire::test(PrendreRendezVous::class)->instance();

        // No trade selected → falls back to grouped services (array shape).
        $this->assertIsArray($instance->getServicesForSelectedTradeProperty());

        // Selected but missing trade id → returns grouped fallback.
        $instance->selectedTradeId = 999999;
        $this->assertIsArray($instance->getServicesForSelectedTradeProperty());
    }

    public function test_can_pick_premium_provider_reflects_customer_profile(): void
    {
        $this->actingAs($this->clientUser());
        $this->assertFalse(
            Livewire::test(PrendreRendezVous::class)->instance()->canPickPremiumProvider()
        );

        $this->actingAs($this->premiumClientUser());
        $this->assertTrue(
            Livewire::test(PrendreRendezVous::class)->instance()->canPickPremiumProvider()
        );
    }

    public function test_postal_code_inputs_stay_in_sync(): void
    {
        $this->actingAs($this->clientUser());

        Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', '1000')
            ->assertSet('code_postal', '1000');

        Livewire::test(PrendreRendezVous::class)
            ->set('code_postal', '1050')
            ->assertSet('postal_code_input', '1050');
    }

    public function test_switching_to_asap_mode_without_zone_sets_guard_message(): void
    {
        $this->actingAs($this->clientUser());

        Livewire::test(PrendreRendezVous::class)
            ->set('booking_mode', 'asap')
            ->assertSet('is_recurrent', false)
            ->assertSet('priorite', 'urgente')
            ->assertSet('asapMessage', 'Entrez d’abord une adresse couverte.');
    }

    public function test_resolve_asap_slot_directly_without_zone(): void
    {
        $this->actingAs($this->clientUser());

        Livewire::test(PrendreRendezVous::class)
            ->call('resolveAsapSlot')
            ->assertSet('asapMessage', 'Entrez d’abord une adresse couverte.');
    }

    public function test_book_any_available_provider_clears_preference_and_resubmits(): void
    {
        $this->actingAs($this->clientUser());

        // No service/zone context → validerRdv yields validation errors, but the
        // method still clears the preferred provider and forces 'any'.
        Livewire::test(PrendreRendezVous::class)
            ->set('preferredProviderUserId', 5)
            ->set('providerTypePreference', 'independent')
            ->call('bookAnyAvailableProvider')
            ->assertSet('preferredProviderUserId', null)
            ->assertSet('providerTypePreference', 'any');
    }
}
