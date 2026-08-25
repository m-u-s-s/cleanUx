<?php

namespace Tests\Feature\Client;

use App\Livewire\ClientDashboard;
use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA COURBE DES DÉPENSES DU CLIENT.
 *
 * L'espace client n'avait aucun graphique : quatre compteurs, et rien qui montre une
 * évolution. Ce qui se vérifie ici, ce sont les trois façons dont une série peut mentir.
 */
class DepensesParMoisTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        $client = User::factory()->client()->create();
        $this->actingAs($client);

        return $client;
    }

    private function reservation(User $client, string $date, array $surcharges = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'client_id' => $client->id,
            'date' => $date,
            'status' => BookingStatus::TERMINE,
        ], $surcharges));
    }

    public function test_la_serie_couvre_toujours_six_mois(): void
    {
        $client = $this->client();
        $this->reservation($client, now()->toDateString(), ['final_price' => 100]);

        $serie = Livewire::test(ClientDashboard::class)->instance()->depensesParMois;

        $this->assertCount(6, $serie);
    }

    /**
     * LES MOIS VIDES SONT REMPLIS À ZÉRO.
     *
     * Une série qui saute les mois sans dépense dessine une pente continue entre janvier et
     * avril : elle ment sur ce qui s'est passé entre les deux.
     */
    public function test_un_mois_sans_depense_vaut_zero_et_ne_disparait_pas(): void
    {
        $client = $this->client();

        // Une seule dépense, il y a cinq mois : les quatre suivants doivent valoir zéro.
        $this->reservation($client, now()->copy()->subMonths(5)->startOfMonth()->addDay()->toDateString(), [
            'final_price' => 250,
        ]);

        $serie = Livewire::test(ClientDashboard::class)->instance()->depensesParMois;
        $montants = array_column($serie, 'montant');

        $this->assertCount(6, $serie);
        $this->assertSame(250.0, $montants[0]);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0], array_slice($montants, 1));
    }

    /**
     * `final_price` D'ABORD, `devis_estime` EN REPLI.
     *
     * Le prix final n'existe qu'une fois la mission close. S'en tenir à lui ferait disparaître
     * du graphique toute intervention à venir, et la courbe s'arrêterait au mois dernier sans
     * que rien ne l'explique.
     */
    public function test_une_intervention_sans_prix_final_compte_son_devis(): void
    {
        $client = $this->client();
        $this->reservation($client, now()->toDateString(), ['final_price' => null, 'devis_estime' => 80]);

        $serie = Livewire::test(ClientDashboard::class)->instance()->depensesParMois;

        $this->assertSame(80.0, end($serie)['montant']);
    }

    /** TÉMOIN — une réservation ANNULÉE n'est pas une dépense. */
    public function test_une_annulee_ne_compte_pas(): void
    {
        $client = $this->client();
        $this->reservation($client, now()->toDateString(), [
            'final_price' => 500,
            'status' => BookingStatus::ANNULE,
        ]);

        $serie = Livewire::test(ClientDashboard::class)->instance()->depensesParMois;

        $this->assertSame(0.0, end($serie)['montant']);
    }

    /** TÉMOIN — les dépenses d'un AUTRE client ne fuitent pas dans la série. */
    public function test_les_depenses_d_un_autre_client_ne_fuitent_pas(): void
    {
        $client = $this->client();
        $autre = User::factory()->client()->create();

        $this->reservation($autre, now()->toDateString(), ['final_price' => 900]);

        $serie = Livewire::test(ClientDashboard::class)->instance()->depensesParMois;

        $this->assertSame(0.0, array_sum(array_column($serie, 'montant')));
    }

    /** Une dépense plus vieille que la fenêtre n'y entre pas. */
    public function test_une_depense_hors_fenetre_est_exclue(): void
    {
        $client = $this->client();
        $this->reservation($client, Carbon::now()->copy()->subMonths(9)->toDateString(), [
            'final_price' => 700,
        ]);

        $serie = Livewire::test(ClientDashboard::class)->instance()->depensesParMois;

        $this->assertSame(0.0, array_sum(array_column($serie, 'montant')));
    }
}
