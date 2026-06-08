<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\BookingFavorite;
use App\Models\CustomerProfile;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exercises the stateful interaction methods of the public booking form
 * Livewire component (promo preview, provider/company picker management,
 * favorites, ASAP booking-mode guard) that are not covered by the trade-grid
 * and provider-selection suites.
 */
class PrendreRendezVousInteractionsTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->client()->create();
    }

    // ─────────────────────────────────────────────────────────────
    // Promo code preview
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function preview_promo_code_does_nothing_for_an_empty_code(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', '   ')
            ->set('devis_estime', 100)
            ->call('previewPromoCode')
            ->assertSet('promo_valid', false)
            ->assertSet('promo_message', null)
            ->assertSet('promo_discount_preview', null);
    }

    #[Test]
    public function preview_promo_code_reports_when_estimate_is_unavailable(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', 'WELCOME10')
            ->set('devis_estime', 0)
            ->call('previewPromoCode')
            ->assertSet('promo_valid', false)
            ->assertSet('promo_message', 'Estimation indisponible pour évaluer le code.');
    }

    #[Test]
    public function preview_promo_code_validates_an_active_percent_code(): void
    {
        $this->actingAs($this->client());

        PromoCode::factory()->create([
            'code' => 'SAVE10',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'min_booking_amount' => 0,
            'max_discount_amount' => 1000,
            'first_booking_only' => false,
            'audience_scope' => PromoCode::SCOPE_ALL,
            'status' => PromoCode::STATUS_ACTIVE,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'promo_campaign_id' => null,
        ]);

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', 'SAVE10')
            ->set('devis_estime', 100)
            ->call('previewPromoCode')
            ->assertSet('promo_valid', true)
            ->assertSet('promo_discount_preview', 10.0);

        $this->assertStringContainsString('Code valide', $component->get('promo_message'));
    }

    #[Test]
    public function preview_promo_code_reports_an_unknown_code(): void
    {
        $this->actingAs($this->client());

        $component = Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', 'NOPE-DOES-NOT-EXIST')
            ->set('devis_estime', 100)
            ->call('previewPromoCode')
            ->assertSet('promo_valid', false);

        $this->assertNotNull($component->get('promo_message'));
    }

    #[Test]
    public function clear_promo_code_resets_all_promo_state(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->set('promo_code', 'SAVE10')
            ->set('promo_discount_preview', 12.5)
            ->set('promo_message', 'something')
            ->set('promo_valid', true)
            ->call('clearPromoCode')
            ->assertSet('promo_code', null)
            ->assertSet('promo_discount_preview', null)
            ->assertSet('promo_message', null)
            ->assertSet('promo_valid', false);
    }

    // ─────────────────────────────────────────────────────────────
    // Favorites + preferred provider
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function rebook_favorite_sets_the_preferred_provider_for_the_owner(): void
    {
        $client = $this->client();
        $provider = User::factory()->employe()->create();

        $favorite = BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $provider->id,
        ]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('preferredProviderMessage', 'stale')
            ->set('preferredProviderAlternativeSlots', [['date' => '2026-01-01', 'heure' => '10:00']])
            ->call('rebookFavorite', $favorite->id)
            ->assertSet('preferredProviderUserId', $provider->id)
            ->assertSet('preferredProviderMessage', null)
            ->assertSet('preferredProviderAlternativeSlots', []);
    }

    #[Test]
    public function rebook_favorite_ignores_a_favorite_owned_by_another_client(): void
    {
        $client = $this->client();
        $other = $this->client();
        $provider = User::factory()->employe()->create();

        $foreignFavorite = BookingFavorite::query()->create([
            'client_user_id' => $other->id,
            'preferred_provider_user_id' => $provider->id,
        ]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->call('rebookFavorite', $foreignFavorite->id)
            ->assertSet('preferredProviderUserId', null);
    }

    #[Test]
    public function pick_preferred_provider_sets_provider_and_closes_picker(): void
    {
        $this->actingAs($this->client());
        $provider = User::factory()->employe()->create();

        Livewire::test(PrendreRendezVous::class)
            ->set('showProviderPicker', true)
            ->call('pickPreferredProvider', $provider->id)
            ->assertSet('preferredProviderUserId', $provider->id)
            ->assertSet('showProviderPicker', false)
            ->assertSet('preferredProviderMessage', null);
    }

    #[Test]
    public function clear_preferred_provider_resets_preference_state(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->set('preferredProviderUserId', 42)
            ->set('preferredProviderMessage', 'msg')
            ->set('preferredProviderAlternativeSlots', [['date' => '2026-01-01', 'heure' => '09:00']])
            ->call('clearPreferredProvider')
            ->assertSet('preferredProviderUserId', null)
            ->assertSet('preferredProviderMessage', null)
            ->assertSet('preferredProviderAlternativeSlots', []);
    }

    #[Test]
    public function toggle_provider_picker_flips_visibility(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->assertSet('showProviderPicker', false)
            ->call('toggleProviderPicker')
            ->assertSet('showProviderPicker', true)
            ->call('toggleProviderPicker')
            ->assertSet('showProviderPicker', false);
    }

    #[Test]
    public function client_favorites_property_returns_only_the_clients_favorites(): void
    {
        $client = $this->client();
        $other = $this->client();
        $mine = User::factory()->employe()->create(['name' => 'Mon Presta']);
        $theirs = User::factory()->employe()->create(['name' => 'Autre Presta']);

        BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $mine->id,
        ]);
        BookingFavorite::query()->create([
            'client_user_id' => $other->id,
            'preferred_provider_user_id' => $theirs->id,
        ]);

        $this->actingAs($client);

        $favorites = Livewire::test(PrendreRendezVous::class)->get('clientFavorites');

        $this->assertCount(1, $favorites);
        $this->assertSame($mine->id, (int) $favorites->first()->preferred_provider_user_id);
    }

    // ─────────────────────────────────────────────────────────────
    // Company picker (SP3)
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function on_company_selected_sets_company_and_clears_worker(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->set('preferredProviderUserId', 7)
            ->set('showProviderPicker', true)
            ->call('onCompanySelected', 99)
            ->assertSet('assignedProviderOrganizationId', 99)
            ->assertSet('preferredProviderUserId', null)
            ->assertSet('showProviderPicker', false)
            ->assertSet('preferredCompanyMessage', null);
    }

    #[Test]
    public function clear_assigned_company_resets_the_company_preference(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->set('assignedProviderOrganizationId', 55)
            ->call('clearAssignedCompany')
            ->assertSet('assignedProviderOrganizationId', null);
    }

    // ─────────────────────────────────────────────────────────────
    // Premium gate
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function cannot_pick_premium_provider_without_a_premium_profile(): void
    {
        $this->actingAs($this->client());

        $component = Livewire::test(PrendreRendezVous::class);

        $this->assertFalse($component->instance()->canPickPremiumProvider());
    }

    #[Test]
    public function can_pick_premium_provider_with_an_active_premium_profile(): void
    {
        $client = $this->client();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        $this->actingAs($client->fresh());

        $component = Livewire::test(PrendreRendezVous::class);

        $this->assertTrue($component->instance()->canPickPremiumProvider());
    }

    // ─────────────────────────────────────────────────────────────
    // ASAP booking mode
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function switching_to_asap_mode_without_a_covered_address_shows_a_guidance_message(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->set('is_recurrent', true)
            ->set('booking_mode', 'asap')
            ->assertSet('is_recurrent', false)
            ->assertSet('priorite', 'urgente')
            ->assertSet('asapMessage', 'Entrez d’abord une adresse couverte.');
    }
}
