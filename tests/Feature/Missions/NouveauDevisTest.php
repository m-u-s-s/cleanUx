<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklistItem;
use App\Models\MissionMedia;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionTodoService;
use App\Services\Missions\OnSite\MissionChecklistService as OnSiteChecklistService;
use App\Services\Missions\QuoteRevisionWindow;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LE NOUVEAU DEVIS — et surtout la fenêtre qui empêche d'en faire une arme.
 *
 * La règle du porteur : la révision se fait AU DÉBUT, avant que le prestataire ne touche à quoi que
 * ce soit. Un imprévu découvert en travaillant passe par le supplément, pas par ici.
 *
 * Trois faits mesurables ferment la fenêtre, et aucun n'est déclaratif : une tâche cochée, une
 * photo « après », l'échéance. La photo « avant » ne ferme rien — elle se prend justement pour
 * constater l'écart.
 */
class NouveauDevisTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $prestataire;

    private function mission(string $moteur = 'domicile', ?Carbon $demarree = null): Mission
    {
        $this->client = User::factory()->client()->create();
        $this->prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $this->prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $this->client->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
            'devis_estime' => 50.00,
            'estimated_price' => 50.00,
        ] + match ($moteur) {
            'vehicule' => ['dropoff_lat' => 50.9010, 'dropoff_lng' => 4.4844],
            'horaire' => ['purchased_minutes' => 180],
            default => [],
        });

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->prestataire->id,
            'status' => $demarree ? MissionStatus::STARTED : MissionStatus::ARRIVED,
            'actual_start_at' => $demarree,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $this->prestataire->id,
            'arrived_at' => $demarree ?? Carbon::now(),
        ]);

        return $mission->fresh('booking');
    }

    private function fenetre(): QuoteRevisionWindow
    {
        return app(QuoteRevisionWindow::class);
    }

    // ── TÉMOINS : la fenêtre est ouverte quand elle doit l'être ───────────────

    public function test_a_l_arrivee_la_fenetre_est_ouverte(): void
    {
        $etat = $this->fenetre()->etat($this->mission());

        $this->assertTrue($etat['open']);
        $this->assertNull($etat['reason']);
    }

    public function test_une_photo_avant_ne_ferme_rien(): void
    {
        $mission = $this->mission();

        MissionMedia::create([
            'mission_id' => $mission->id,
            'uploaded_by_user_id' => $this->prestataire->id,
            'media_type' => MissionMedia::TYPE_BEFORE_PHOTO,
            'path' => 'missions/avant.jpg',
        ]);

        $this->assertTrue(
            $this->fenetre()->etat($mission->fresh())['open'],
            'la photo « avant » se prend justement pour constater l’écart',
        );
    }

    // ── LES TROIS FERMETURES ──────────────────────────────────────────────────

    public function test_une_tache_cochee_ferme_la_fenetre(): void
    {
        $mission = $this->mission();
        $item = app(MissionTodoService::class)->ajouter($mission, $this->client, 'Nettoyer la hotte');

        // Coché PAR LE VRAI CHEMIN : `basculer()` pose `completed_at`, que la fenêtre lit pour
        // savoir si le prestataire a agi. Écrire `status` à la main laisserait cette date nulle et
        // le test mesurerait un état que l'application ne produit jamais.
        app(OnSiteChecklistService::class)->basculer($item, 'done', null, $this->prestataire);

        $etat = $this->fenetre()->etat($mission->fresh());

        $this->assertFalse($etat['open']);
        $this->assertStringContainsString('commencé', $etat['reason']);
    }

    public function test_une_photo_apres_ferme_la_fenetre(): void
    {
        $mission = $this->mission();

        MissionMedia::create([
            'mission_id' => $mission->id,
            'uploaded_by_user_id' => $this->prestataire->id,
            'media_type' => MissionMedia::TYPE_AFTER_PHOTO,
            'path' => 'missions/apres.jpg',
        ]);

        $this->assertFalse($this->fenetre()->etat($mission->fresh())['open']);
    }

    public function test_l_echeance_ferme_la_fenetre(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $mission = $this->mission(demarree: Carbon::parse('2026-08-18 10:00:00'));

        Carbon::setTestNow('2026-08-18 10:31:00');
        $etat = $this->fenetre()->etat($mission->fresh());

        $this->assertFalse($etat['open']);
        $this->assertStringContainsString('délai', $etat['reason']);
        Carbon::setTestNow();
    }

    /**
     * LA SYMÉTRIE, et c'est la garde la plus importante du module.
     *
     * Sans elle, un client ajoute trois tâches lourdes à la minute 25 — quand plus rien n'est
     * révisable — et la règle anti-abus prestataire devient une arme entre ses mains.
     */
    public function test_une_tache_ajoutee_par_le_client_rouvre_la_fenetre(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $mission = $this->mission(demarree: Carbon::parse('2026-08-18 10:00:00'));

        Carbon::setTestNow('2026-08-18 10:28:00');
        app(MissionTodoService::class)->ajouter($mission, $this->client, 'Et les vitres aussi');

        // L'échéance de base tombe à 10:30 ; l'ajout rouvre jusqu'à 10:34.
        Carbon::setTestNow('2026-08-18 10:32:00');

        $this->assertTrue(
            $this->fenetre()->etat($mission->fresh())['open'],
            'le client a changé la demande : le prestataire doit pouvoir y répondre',
        );

        // ... et six minutes après l'ajout, elle est refermée.
        Carbon::setTestNow('2026-08-18 10:35:00');
        $this->assertFalse($this->fenetre()->etat($mission->fresh())['open']);

        Carbon::setTestNow();
    }

    public function test_une_course_n_a_pas_de_revision(): void
    {
        $etat = $this->fenetre()->etat($this->mission('vehicule'));

        $this->assertFalse($etat['open']);
        $this->assertStringContainsString('trajet', $etat['reason']);
    }

    public function test_une_mission_horaire_n_a_pas_de_revision(): void
    {
        $etat = $this->fenetre()->etat($this->mission('horaire'));

        $this->assertFalse($etat['open']);
        $this->assertStringContainsString('temps', $etat['reason']);
    }
}
