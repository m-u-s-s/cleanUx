<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderConfirmation;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\OrderDraftManager;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** LE CODE PROMO, ENFIN SAISISSABLE. */
class CodePromoALaCommandeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    /** LE CHAMP EST VISIBLE — pas seulement fonctionnel. */
    public function test_le_champ_est_offert_au_client(): void
    {
        $client = $this->panierPret();

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->assertSee('Vous avez un code promo');
    }

    /** TÉMOIN POSITIF — un code valide réduit la facture. */
    public function test_un_code_valide_applique_sa_remise(): void
    {
        $client = $this->panierPret();
        $this->codePromo('BIENVENUE10', 10.0);

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->set('promoCode', 'BIENVENUE10')
            ->call('confirm')
            ->assertSet('promoApplique', true)
            ->assertSee('Commande confirmée');

        // La remise vit dans `pricing_snapshot` et fait baisser le devis :
        // c'est ce que `BookingPromoCodeApplier::writeBackToBooking()` écrit.
        $reservation = Booking::firstOrFail();
        $applique = data_get($reservation->pricing_snapshot, 'promo_code_applied');

        $this->assertNotNull($applique, 'La réservation doit garder la trace du code appliqué');
        $this->assertSame('BIENVENUE10', $applique['code']);
        $this->assertGreaterThan(0, (float) $applique['discount_amount'], 'La remise doit être chiffrée');
        $this->assertLessThan(
            (float) $applique['amount_before'],
            (float) $applique['amount_after'],
            'Le montant après remise doit être inférieur au montant avant'
        );
    }

    /** TÉMOIN DE NON-RÉGRESSION — sans code, la commande se confirme comme avant. */
    public function test_temoin_sans_code_la_commande_passe_toujours(): void
    {
        $client = $this->panierPret();

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->call('confirm')
            ->assertSet('promoApplique', false)
            ->assertSet('promoMessage', null)
            ->assertSee('Commande confirmée');

        $this->assertSame(1, Booking::count());
    }

    /** REFUS — un code inconnu ne fait PAS échouer la commande, il s'explique. */
    public function test_un_code_inconnu_n_annule_pas_la_commande(): void
    {
        $client = $this->panierPret();

        $composant = Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->set('promoCode', 'NEXISTEPAS')
            ->call('confirm')
            ->assertSet('promoApplique', false)
            ->assertSee('Commande confirmée');

        $this->assertNotNull($composant->get('promoMessage'),
            'Le refus doit être dit au client, pas avalé');
        $this->assertSame(1, Booking::count(),
            'Un code refusé ne doit pas coûter sa commande au client');
    }

    private function codePromo(string $code, float $pourcentage): PromoCode
    {
        return PromoCode::create([
            'code' => $code,
            'name' => 'Remise de bienvenue',
            'discount_type' => 'percent',
            'discount_value' => $pourcentage,
            'status' => 'active',
            'valid_from' => Carbon::now()->subDay(),
            'valid_until' => Carbon::now()->addMonth(),
            'audience_scope' => 'all',
        ]);
    }

    private function panierPret(): User
    {
        $client = User::factory()->client()->create();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton');
        $draft->update([
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'lat' => 50.8467,
            'lng' => 4.3525,
            'scheduled_at' => Carbon::parse('2026-09-02 09:00:00'),
        ]);

        $trade = Trade::where('slug', 'peinture')->firstOrFail();
        $item = $manager->itemFor($draft, $trade);
        $manager->saveAnswers(
            $item,
            $trade->questions()->with(['options', 'conditions'])->get(),
            ['surface_m2' => 40, 'etendue' => 'murs_plafonds'],
        );

        session()->put('order_draft_token', 'jeton');

        return $client;
    }
}
