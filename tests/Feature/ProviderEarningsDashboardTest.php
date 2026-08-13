<?php

namespace Tests\Feature;

use App\Livewire\Provider\ProviderEarningsDashboard;
use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderEarningsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_mounts_for_an_employe_with_default_week_period(): void
    {
        $employe = User::factory()->employe()->create();

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->assertOk()
            ->assertSet('period', 'week')
            ->assertSee('Earnings dashboard');
    }

    public function test_dashboard_aggregates_completed_missions_and_tips_for_the_provider(): void
    {
        $employe = User::factory()->employe()->create();

        $booking = Booking::factory()->termine()->create([
            'employe_id' => $employe->id,
            'provider_amount_cents' => 9000,
            'updated_at' => now(),
        ]);

        BookingTip::create([
            'code' => 'TIP-'.uniqid(),
            'booking_id' => $booking->id,
            'client_user_id' => $booking->client_id,
            'provider_user_id' => $employe->id,
            'amount_cents' => 1500,
            'currency' => 'EUR',
            'status' => BookingTip::STATUS_CHARGED,
        ]);

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->assertOk()
            // Revenu total = 9000 + 1500 = 10500 cents -> 105,00 €
            ->assertSee('105,00')
            // Pourboires 15,00 €
            ->assertSee('15,00')
            ->assertSee('Décomposition revenus');
    }

    /**
     * LE PORTEFEUILLE N'A JAMAIS PU S'AFFICHER SUR CETTE PAGE.
     *
     * Deux colonnes inexistantes dans le même bloc : le filtre portait sur `user_id` quand la
     * colonne s'appelle `provider_user_id`, et la somme sur `amount_cents` quand elle s'appelle
     * `amount` et vaut des euros. Sur MySQL chacune lève « Unknown column » ; sur SQLite — le
     * moteur de cette suite — un identifiant inconnu entre guillemets doubles devient une chaîne
     * littérale, la comparaison est fausse en silence et la somme rend zéro.
     *
     * Le défaut était donc invisible aux tests ET masqué par une table vide : même un paiement
     * réellement encaissé n'aurait rien affiché.
     */
    public function test_le_portefeuille_credite_apparait_dans_les_revenus(): void
    {
        $employe = User::factory()->employe()->create();

        ProviderWalletTransaction::create([
            'provider_user_id' => $employe->id,
            'type' => ProviderWalletTransaction::TYPE_EARNING,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
            'amount' => 150.80,
            'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'idempotency_key' => 'test:earning:'.uniqid(),
            'occurred_at' => now(),
        ]);

        ProviderWalletTransaction::create([
            'provider_user_id' => $employe->id,
            'type' => ProviderWalletTransaction::TYPE_PAYOUT,
            'direction' => ProviderWalletTransaction::DIRECTION_DEBIT,
            'amount' => 50.00,
            'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_PROCESSING,
            'idempotency_key' => 'test:payout:'.uniqid(),
            'occurred_at' => now(),
        ]);

        // Un autre prestataire ne doit pas polluer le total : c'est ce que le filtre corrigé garde.
        $autre = User::factory()->employe()->create();
        ProviderWalletTransaction::create([
            'provider_user_id' => $autre->id,
            'type' => ProviderWalletTransaction::TYPE_EARNING,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
            'amount' => 999.00,
            'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'idempotency_key' => 'test:autre:'.uniqid(),
            'occurred_at' => now(),
        ]);

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->assertOk()
            ->assertSee('150,80')
            ->assertSee('50,00')
            ->assertDontSee('999,00');
    }

    public function test_set_period_switches_each_window_and_rerenders(): void
    {
        $employe = User::factory()->employe()->create();

        $component = Livewire::actingAs($employe)->test(ProviderEarningsDashboard::class);

        foreach (['today', 'week', 'month', 'year'] as $period) {
            $component->call('setPeriod', $period)
                ->assertOk()
                ->assertSet('period', $period);
        }
    }

    public function test_unknown_period_falls_back_to_week_default_range(): void
    {
        $employe = User::factory()->employe()->create();

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->call('setPeriod', 'decade')
            ->assertOk()
            ->assertSet('period', 'decade');
    }

    public function test_empty_provider_shows_no_data_states(): void
    {
        $employe = User::factory()->employe()->create();

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->call('setPeriod', 'today')
            ->assertOk()
            ->assertSee('Aucune donnée sur cette période.')
            ->assertSee('Pas assez de données.');
    }
}
