<?php

namespace Tests\Feature\Rental;

use App\Models\RentalBooking;
use App\Models\RentalVehicle;
use App\Services\Rental\RentalAvailability;
use App\Services\Rental\RentalPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** LE CATALOGUE DE LOCATION NE MONTRE QUE CE QU'ON PEUT RÉELLEMENT LOUER. */
class DisponibiliteEtTarifsTest extends TestCase
{
    use RefreshDatabase;

    private function periode(): array
    {
        return [Carbon::parse('2026-10-10 09:00'), Carbon::parse('2026-10-15 09:00')];
    }

    private function vehiculeProposable(array $attributs = []): RentalVehicle
    {
        return RentalVehicle::factory()->actif()->create($attributs);
    }

    private function louerDu(RentalVehicle $vehicule, string $debut, string $fin, string $statut = RentalBooking::STATUT_CONFIRMEE): RentalBooking
    {
        return RentalBooking::factory()->create([
            'rental_vehicle_id' => $vehicule->id,
            'starts_at' => Carbon::parse($debut),
            'ends_at' => Carbon::parse($fin),
            'status' => $statut,
        ]);
    }

    // ── Ce qui bloque, et ce qui ne bloque pas ───────────────────────────

    public function test_une_voiture_libre_est_proposee(): void
    {
        $this->vehiculeProposable();
        [$debut, $fin] = $this->periode();

        $this->assertCount(1, app(RentalAvailability::class)->catalogue($debut, $fin));
    }

    /**
     * @dataProvider recouvrementsQuiBloquent
     */
    public function test_une_location_qui_recouvre_la_periode_retire_la_voiture(string $debut, string $fin, string $cas): void
    {
        $vehicule = $this->vehiculeProposable();
        $this->louerDu($vehicule, $debut, $fin);

        [$d, $f] = $this->periode();

        $this->assertCount(
            0,
            app(RentalAvailability::class)->catalogue($d, $f),
            "Recouvrement « {$cas} » non détecté : la voiture reste au catalogue alors qu’elle est louée.",
        );
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function recouvrementsQuiBloquent(): array
    {
        return [
            'a cheval sur le debut' => ['2026-10-08 09:00', '2026-10-12 09:00', 'à cheval sur le début'],
            'a cheval sur la fin' => ['2026-10-13 09:00', '2026-10-18 09:00', 'à cheval sur la fin'],
            // LE CAS QU'UN TEST NAÏF OUBLIE, et le plus courant en pratique.
            'enchassee' => ['2026-10-11 09:00', '2026-10-13 09:00', 'enchâssée dans la période'],
            'englobante' => ['2026-10-01 09:00', '2026-10-30 09:00', 'englobant la période'],
            'identique' => ['2026-10-10 09:00', '2026-10-15 09:00', 'identique'],
        ];
    }

    /**
     * @dataProvider recouvrementsQuiNeBloquentPas
     */
    public function test_une_location_hors_periode_laisse_la_voiture_disponible(string $debut, string $fin, string $cas): void
    {
        $vehicule = $this->vehiculeProposable();
        $this->louerDu($vehicule, $debut, $fin);

        [$d, $f] = $this->periode();

        $this->assertCount(
            1,
            app(RentalAvailability::class)->catalogue($d, $f),
            "Cas « {$cas} » : la voiture est masquée alors qu’elle est libre. Le parc paraît plus "
            .'petit qu’il ne l’est, et des locations possibles ne sont jamais proposées.',
        );
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function recouvrementsQuiNeBloquentPas(): array
    {
        return [
            'avant' => ['2026-10-01 09:00', '2026-10-05 09:00', 'entièrement avant'],
            'apres' => ['2026-10-20 09:00', '2026-10-25 09:00', 'entièrement après'],
            // LES BORNES SONT STRICTES, ET C'EST CE QUI PERMET D'ENCHAÎNER DEUX CLIENTS.
            'finit quand la notre commence' => ['2026-10-05 09:00', '2026-10-10 09:00', 'se termine au départ'],
            'commence quand la notre finit' => ['2026-10-15 09:00', '2026-10-20 09:00', 'commence au retour'],
        ];
    }

    /** UNE LOCATION ANNULÉE OU RENDUE NE RÉSERVE PLUS RIEN. */
    public function test_une_location_annulee_ou_rendue_ne_bloque_pas(): void
    {
        $vehicule = $this->vehiculeProposable();
        [$d, $f] = $this->periode();

        $this->louerDu($vehicule, '2026-10-11 09:00', '2026-10-13 09:00', RentalBooking::STATUT_ANNULEE);
        $this->assertCount(1, app(RentalAvailability::class)->catalogue($d, $f));

        $this->louerDu($vehicule, '2026-10-11 09:00', '2026-10-13 09:00', RentalBooking::STATUT_RENDUE);
        $this->assertCount(1, app(RentalAvailability::class)->catalogue($d, $f));
    }

    /** UN PANIER OUVERT NE BLOQUE PAS NON PLUS. */
    public function test_un_brouillon_ne_bloque_pas(): void
    {
        $vehicule = $this->vehiculeProposable();
        $this->louerDu($vehicule, '2026-10-11 09:00', '2026-10-13 09:00', RentalBooking::STATUT_BROUILLON);

        [$d, $f] = $this->periode();

        $this->assertCount(1, app(RentalAvailability::class)->catalogue($d, $f));
    }

    /** Une voiture désactivée disparaît du catalogue, quelle que soit sa disponibilité. */
    public function test_une_voiture_desactivee_nest_pas_proposee(): void
    {
        RentalVehicle::factory()->create(['is_active' => false]);

        [$d, $f] = $this->periode();

        $this->assertCount(0, app(RentalAvailability::class)->catalogue($d, $f));
    }

    // ── L'entrée du catalogue ────────────────────────────────────────────

    public function test_sans_aucune_voiture_le_compte_est_nul(): void
    {
        $this->assertSame(0, app(RentalAvailability::class)->combienDeVehiculesProposables());
    }

    public function test_avec_une_voiture_proposable_le_compte_le_dit(): void
    {
        $this->vehiculeProposable();

        $this->assertSame(1, app(RentalAvailability::class)->combienDeVehiculesProposables());
    }

    /** LE COMPTE ET LA LISTE DISENT LA MÊME CHOSE. */
    public function test_le_compte_de_lentree_et_la_liste_saccordent(): void
    {
        $this->vehiculeProposable();
        $this->vehiculeProposable();
        $loue = $this->vehiculeProposable();
        $this->louerDu($loue, '2026-10-11 09:00', '2026-10-13 09:00');

        [$d, $f] = $this->periode();
        $service = app(RentalAvailability::class);

        $this->assertSame(
            $service->combienDeVehiculesProposables($d, $f),
            $service->catalogue($d, $f)->count(),
        );
    }

    // ── Les tarifs ───────────────────────────────────────────────────────

    /** TOUTE JOURNÉE ENTAMÉE EST DUE — comme dans toutes les agences. */
    public function test_une_journee_entamee_est_facturee(): void
    {
        $vehicule = $this->vehiculeProposable(['daily_price_cents' => 5000, 'min_rental_days' => 1]);
        $tarif = app(RentalPricing::class);

        $this->assertSame(1, $tarif->joursFactures($vehicule, Carbon::parse('2026-10-10 08:00'), Carbon::parse('2026-10-11 08:00')));
        $this->assertSame(2, $tarif->joursFactures($vehicule, Carbon::parse('2026-10-10 08:00'), Carbon::parse('2026-10-11 09:00')));
    }

    /** Le minimum du véhicule s'impose même sur une location de deux heures. */
    public function test_le_minimum_du_vehicule_sapplique(): void
    {
        $vehicule = $this->vehiculeProposable(['min_rental_days' => 3]);

        $this->assertSame(
            3,
            app(RentalPricing::class)->joursFactures($vehicule, Carbon::parse('2026-10-10 08:00'), Carbon::parse('2026-10-10 10:00')),
        );
    }

    /** LES DEUX PRIX SONT RENDUS ENSEMBLE, TOUJOURS. C'est ce que la confirmation doit montrer. */
    public function test_le_devis_donne_le_prix_avec_et_sans_garantie(): void
    {
        $vehicule = $this->vehiculeProposable([
            'daily_price_cents' => 5000,
            'waiver_daily_price_cents' => 1500,
            'deposit_cents' => 80000,
            'waiver_deposit_cents' => 15000,
            'min_rental_days' => 1,
        ]);

        $devis = app(RentalPricing::class)->devis($vehicule, Carbon::parse('2026-10-10 09:00'), Carbon::parse('2026-10-13 09:00'));

        $this->assertSame(3, $devis['days']);
        $this->assertSame(15000, $devis['sans_garantie']['total_cents']);
        $this->assertSame(80000, $devis['sans_garantie']['deposit_cents']);
        $this->assertSame(19500, $devis['avec_garantie']['total_cents']);
        $this->assertSame(15000, $devis['avec_garantie']['deposit_cents']);
        $this->assertSame(4500, $devis['avec_garantie']['supplement_cents']);
        $this->assertTrue($devis['propose_une_garantie']);
    }

    /** TÉMOIN — un véhicule sans garantie le dit, et ses deux prix sont identiques. */
    public function test_temoin_un_vehicule_sans_garantie_nen_propose_pas(): void
    {
        $vehicule = RentalVehicle::factory()->actif()->sansGarantie()->create(['daily_price_cents' => 4000]);

        $devis = app(RentalPricing::class)->devis($vehicule, Carbon::parse('2026-10-10 09:00'), Carbon::parse('2026-10-12 09:00'));

        $this->assertFalse($devis['propose_une_garantie']);
        $this->assertSame($devis['sans_garantie']['total_cents'], $devis['avec_garantie']['total_cents']);
        $this->assertSame(0, $devis['avec_garantie']['supplement_cents']);
    }

    /** La devise suit le véhicule, jamais une constante. */
    public function test_le_devis_porte_la_devise_du_vehicule(): void
    {
        $vehicule = $this->vehiculeProposable(['currency' => 'MAD']);

        $this->assertSame('MAD', app(RentalPricing::class)->devis($vehicule, null, null)['currency']);
    }
}
