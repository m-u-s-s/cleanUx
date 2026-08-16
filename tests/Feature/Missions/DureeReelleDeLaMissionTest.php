<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Services\Missions\MissionLifecycleService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * LE TEMPS RÉELLEMENT PASSÉ — deux défauts qui se tenaient par la main.
 *
 * 1. `MissionProfitService::calculate()` était appelé AVANT que `actual_end_at` ne soit écrit :
 *    `actual_duration_minutes` valait 0 sur toute mission clôturée par le chemin normal, le coût
 *    salarié aussi, et la marge affichée à l'administrateur égalait le prix client — 100 % sur
 *    chaque intervention.
 *
 * 2. `bookings.duree_reelle` n'était écrite que par un employé tapant un nombre à la main dans le
 *    rapport de fin web. Une mission clôturée depuis le mobile la laissait vide, et le calcul de
 *    coût interne retombait sur la durée ESTIMÉE.
 *
 * Sans ces deux-là, aucun dépassement d'heures ne pourrait être constaté.
 */
class DureeReelleDeLaMissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_duree_reelle_est_calculee_a_la_cloture(): void
    {
        $scenario = SpineScenario::make()->build();
        $mission = $this->missionCommenceeIlYA($scenario, 95);

        app(MissionLifecycleService::class)->completeMission($mission, $scenario->provider);

        $mission->refresh();

        $this->assertSame(MissionStatus::COMPLETED, $mission->status);
        $this->assertSame(
            95,
            (int) $mission->actual_duration_minutes,
            'La durée réelle valait 0 sur toute mission clôturée : le calcul précédait l’écriture de la fin.',
        );
    }

    public function test_la_duree_reelle_est_reportee_sur_la_reservation(): void
    {
        $scenario = SpineScenario::make()->build();
        $mission = $this->missionCommenceeIlYA($scenario, 140);

        app(MissionLifecycleService::class)->completeMission($mission, $scenario->provider);

        $this->assertSame(140, (int) $scenario->booking->refresh()->duree_reelle);
    }

    /**
     * ON N'ÉCRASE PAS UNE SAISIE HUMAINE.
     *
     * Si un employé a corrigé la durée à la main, il a vu quelque chose que l'horloge ignore : une
     * pause non enregistrée, un départ anticipé, un imprévu.
     */
    public function test_une_duree_saisie_a_la_main_est_respectee(): void
    {
        $scenario = SpineScenario::make()->build();
        $scenario->booking->forceFill(['duree_reelle' => 60])->save();

        $mission = $this->missionCommenceeIlYA($scenario, 200);

        app(MissionLifecycleService::class)->completeMission($mission, $scenario->provider);

        $this->assertSame(60, (int) $scenario->booking->refresh()->duree_reelle);
    }

    /**
     * LE PIÈGE QUI A FAIT ÉCHOUER LA PREMIÈRE TENTATIVE.
     *
     * `RendezVousObserver::saved()` resynchronise la mission sur TOUTE sauvegarde d'une réservation
     * `confirme`, et cette synchronisation ramène le statut à sa valeur initiale. Écrire la durée
     * par le modèle faisait donc retomber à `assigned` une mission qu'on venait de clôturer — et le
     * client recevait « assigned » en réponse à sa validation.
     */
    public function test_ecrire_la_duree_ne_ramene_pas_la_mission_en_arriere(): void
    {
        $scenario = SpineScenario::make()->build();
        $scenario->booking->forceFill(['status' => 'confirme'])->save();

        $mission = $this->missionCommenceeIlYA($scenario, 75);

        /*
         * Passer la réservation en `confirme` a fait naître la checklist du métier : la clôture est
         * refusée tant qu'elle n'est pas cochée, et c'est la règle métier, pas un défaut. On coche,
         * comme le ferait le prestataire — sinon ce test mesurerait le refus de la checklist au lieu
         * du piège de l'observateur.
         */
        $mission->loadMissing('checklists.items');
        foreach ($mission->checklists->flatMap->items as $item) {
            $item->update(['status' => 'done']);
        }

        $rendue = app(MissionLifecycleService::class)->completeMission($mission, $scenario->provider);

        $this->assertSame(MissionStatus::COMPLETED, $rendue->status, 'La mission ne doit pas régresser.');
        $this->assertSame(MissionStatus::COMPLETED, $mission->refresh()->status);
    }

    /** TÉMOIN : une mission jamais démarrée ne fabrique aucune durée. */
    public function test_sans_debut_aucune_duree_nest_inventee(): void
    {
        $scenario = SpineScenario::make()->build();

        $mission = $scenario->mission;
        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => null,
        ])->save();

        app(MissionLifecycleService::class)->completeMission($mission, $scenario->provider);

        $this->assertNull($scenario->booking->refresh()->duree_reelle);
        $this->assertSame(0, (int) $mission->refresh()->actual_duration_minutes);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function missionCommenceeIlYA(SpineScenario $scenario, int $minutes): Mission
    {
        $mission = $scenario->mission;

        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now()->subMinutes($minutes),
            'actual_end_at' => null,
        ])->save();

        return $mission->refresh();
    }
}
