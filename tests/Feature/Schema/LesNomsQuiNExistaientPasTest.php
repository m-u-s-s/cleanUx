<?php

namespace Tests\Feature\Schema;

use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Models\Country;
use App\Models\CustomerCredit;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Booking\SmartDispatchService;
use App\Services\Payments\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * QUATRE NOMS QUI N'EXISTAIENT PAS, ET QUE PERSONNE N'ENTENDAIT SE PLAINDRE.
 *
 * Deux tables absentes derrière un `Schema::hasTable` — qui transforme l'absence en `false`
 * silencieux —, deux colonnes absentes derrière un `??` — que PHPStan ne voit pas. Dans les quatre
 * cas le code rendait une valeur légale, et la fonctionnalité n'existait simplement pas.
 */
class LesNomsQuiNExistaientPasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_solde_d_avoirs_lit_la_table_qui_existe(): void
    {
        $client = User::factory()->client()->create();

        CustomerCredit::query()->create([
            'client_id' => $client->id,
            'type' => 'refund',
            'amount' => 40.0,
            'remaining_amount' => 40.0,
            'status' => 'active',
        ]);

        // Rendait 0.0 en permanence : `client_credits` n'existe dans aucune migration, et la garde
        // de `CreateBookingAction` fermait donc définitivement l'application des avoirs.
        $this->assertSame(40.0, $client->fresh()->activeCreditBalance());
    }

    /** LE TÉMOIN : un avoir consommé ou expiré ne compte pas — la garde reste une garde. */
    #[Test]
    public function un_avoir_epuise_ne_compte_pas_dans_le_solde(): void
    {
        $client = User::factory()->client()->create();

        CustomerCredit::query()->create([
            'client_id' => $client->id,
            'type' => 'refund',
            'amount' => 40.0,
            'remaining_amount' => 0.0,
            'status' => 'used',
        ]);

        $this->assertSame(0.0, $client->fresh()->activeCreditBalance());
    }

    #[Test]
    public function le_favori_du_client_pese_enfin_dans_le_barème(): void
    {
        $client = User::factory()->client()->create();
        $prestataire = User::factory()->create();

        $reservation = Booking::factory()->create(['client_id' => $client->id]);

        $sans = app(SmartDispatchService::class);
        $avant = $this->favoriteScore($sans, $prestataire, $reservation);

        BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $prestataire->id,
        ]);

        $apres = $this->favoriteScore($sans, $prestataire, $reservation);

        $this->assertSame(0, $avant, 'sans favori, aucun bonus');
        $this->assertSame(300, $apres, '300 est le poids le plus lourd du barème');
    }

    private function favoriteScore(SmartDispatchService $service, User $prestataire, Booking $rdv): int
    {
        $methode = new \ReflectionMethod($service, 'favoriteScore');
        $methode->setAccessible(true);

        return (int) $methode->invoke($service, $prestataire, $rdv);
    }

    #[Test]
    public function le_compte_connect_s_ouvre_dans_le_pays_de_la_societe(): void
    {
        $maroc = Country::query()->firstOrCreate(
            ['iso_code' => 'MA'],
            ['name' => 'Maroc', 'currency_code' => 'MAD', 'is_active' => true],
        );

        $org = OrganizationAccount::factory()->create(['country_id' => $maroc->id]);
        $prestataire = User::factory()->create(['organization_account_id' => $org->id]);

        $this->assertSame('MA', $this->paysDuPorteur($prestataire));
    }

    /** LE TÉMOIN : sans société ni zone, on retombe bien sur le défaut configuré. */
    #[Test]
    public function sans_rattachement_le_pays_reste_celui_de_la_configuration(): void
    {
        config(['services.stripe.connect_country' => 'BE']);

        $this->assertSame('BE', $this->paysDuPorteur(User::factory()->create()));
    }

    private function paysDuPorteur(User $user): string
    {
        $service = app(StripeConnectService::class);
        $methode = new \ReflectionMethod($service, 'paysDuPorteur');
        $methode->setAccessible(true);

        return (string) $methode->invoke($service, $user);
    }
}
