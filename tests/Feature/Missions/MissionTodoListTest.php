<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklistItem;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionTodoService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LA LISTE QUE LE CLIENT ÉCRIT, ET LA FENÊTRE QUI LA FERME.
 *
 * Deux abus symétriques sont possibles, et chaque garde en ferme un :
 *
 *  - sans fenêtre, un client ajoute trois tâches lourdes à 18 h et retient le prestataire chez lui
 *    sans contrepartie ;
 *  - sans droit de retrait, un client qui s'est trompé ne peut plus rien corriger.
 *
 * Chaque refus est accompagné de son TÉMOIN : sans lui, un test « ceci est refusé » passe au vert
 * en mesurant une panne.
 */
class MissionTodoListTest extends TestCase
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
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
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
        ]);

        return $mission->fresh('booking');
    }

    private function service(): MissionTodoService
    {
        return app(MissionTodoService::class);
    }

    // ── TÉMOINS POSITIFS ──────────────────────────────────────────────────────

    public function test_le_client_ajoute_une_tache_avant_le_demarrage(): void
    {
        $mission = $this->mission();

        $item = $this->service()->ajouter($mission, $this->client, 'Nettoyer la hotte');

        $this->assertSame('Nettoyer la hotte', $item->label);
        $this->assertSame('client', $item->source);
        $this->assertSame($this->client->id, $item->created_by_user_id);
        $this->assertTrue((bool) $item->is_required, 'une tâche du client conditionne la clôture');
        $this->assertSame('todo', $item->status);
    }

    public function test_le_client_ajoute_encore_une_tache_a_vingt_neuf_minutes(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $mission = $this->mission(demarree: Carbon::parse('2026-08-18 10:00:00'));

        Carbon::setTestNow('2026-08-18 10:29:00');
        $item = $this->service()->ajouter($mission, $this->client, 'Vitres du salon');

        $this->assertSame('Vitres du salon', $item->label);
        Carbon::setTestNow();
    }

    public function test_une_mission_horaire_accepte_la_liste(): void
    {
        $mission = $this->mission('horaire');

        $this->assertNotNull($this->service()->ajouter($mission, $this->client, 'Repasser le linge'));
    }

    public function test_le_client_retire_sa_propre_tache(): void
    {
        $mission = $this->mission();
        $item = $this->service()->ajouter($mission, $this->client, 'Finalement non');

        $this->service()->retirer($mission, $this->client, $item);

        $this->assertNull(MissionChecklistItem::find($item->id));
    }

    // ── REFUS ─────────────────────────────────────────────────────────────────

    public function test_une_course_n_a_pas_de_liste(): void
    {
        $mission = $this->mission('vehicule');

        $this->expectException(DomainException::class);
        $this->service()->ajouter($mission, $this->client, 'Nettoyer la banquette');
    }

    public function test_la_fenetre_se_ferme_a_trente_et_une_minutes(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $mission = $this->mission(demarree: Carbon::parse('2026-08-18 10:00:00'));

        Carbon::setTestNow('2026-08-18 10:31:00');

        try {
            $this->service()->ajouter($mission, $this->client, 'Trop tard');
            $this->fail('la fenêtre aurait dû être fermée');
        } catch (DomainException $e) {
            $this->assertStringContainsString('figée', $e->getMessage());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_une_mission_terminee_n_accepte_plus_rien(): void
    {
        $mission = $this->mission();
        $mission->update(['status' => MissionStatus::COMPLETED]);

        $this->expectException(DomainException::class);
        $this->service()->ajouter($mission->fresh('booking'), $this->client, 'Après coup');
    }

    public function test_un_libelle_vide_est_refuse(): void
    {
        $mission = $this->mission();

        $this->expectException(DomainException::class);
        $this->service()->ajouter($mission, $this->client, '   ');
    }

    public function test_le_client_ne_retire_pas_une_tache_deja_faite(): void
    {
        $mission = $this->mission();
        $item = $this->service()->ajouter($mission, $this->client, 'Déjà faite');
        $item->update(['status' => 'done']);

        $this->expectException(DomainException::class);
        $this->service()->retirer($mission, $this->client, $item->fresh());
    }

    public function test_le_client_ne_retire_pas_une_tache_qui_n_est_pas_de_lui(): void
    {
        $mission = $this->mission();
        $this->service()->ajouter($mission, $this->client, 'La sienne');

        $duGabarit = MissionChecklistItem::create([
            'mission_checklist_id' => $mission->fresh('checklists')->checklists->first()->id,
            'label' => 'Contrôle qualité',
            'item_type' => 'checkbox',
            'is_required' => true,
            'status' => 'todo',
            'source' => 'template',
        ]);

        $this->expectException(DomainException::class);
        $this->service()->retirer($mission, $this->client, $duGabarit);
    }

    // ── LA FENÊTRE, TELLE QUE L'ÉCRAN LA LIT ──────────────────────────────────

    public function test_la_fenetre_sans_demarrage_est_ouverte_sans_echeance(): void
    {
        $fenetre = $this->service()->fenetre($this->mission());

        $this->assertTrue($fenetre['open']);
        $this->assertNull($fenetre['closes_at']);
        $this->assertNull($fenetre['minutes_left']);
    }

    public function test_la_fenetre_annonce_les_minutes_restantes(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $mission = $this->mission(demarree: Carbon::parse('2026-08-18 10:00:00'));

        Carbon::setTestNow('2026-08-18 10:10:00');
        $fenetre = $this->service()->fenetre($mission);

        $this->assertTrue($fenetre['open']);
        $this->assertSame(20, $fenetre['minutes_left']);
        Carbon::setTestNow();
    }

    /**
     * `locked_at` ATTESTE, il ne PILOTE pas : la fenêtre se calcule depuis `actual_start_at`, et
     * cette colonne ne fait qu'enregistrer l'instant où le refus a été opposé. Le support la
     * relira le jour où un client affirmera avoir ajouté à temps.
     */
    public function test_la_fenetre_fermee_atteste_le_verrouillage(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $mission = $this->mission(demarree: Carbon::parse('2026-08-18 10:00:00'));
        $item = $this->service()->ajouter($mission, $this->client, 'À faire');

        Carbon::setTestNow('2026-08-18 10:31:00');

        try {
            $this->service()->ajouter($mission, $this->client, 'Trop tard');
        } catch (DomainException) {
            // attendu
        }

        $this->assertNotNull($item->fresh()->locked_at);
        Carbon::setTestNow();
    }
}
