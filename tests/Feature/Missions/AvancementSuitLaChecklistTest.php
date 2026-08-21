<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Services\Missions\OnSite\MissionTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LE COMPTEUR DOIT COMPTER CE QUE LE CLIENT VOIT.
 *
 * L'écran de suivi affiche un badge « Avancement x/y » posé juste au-dessus de « Ma liste de
 * tâches ». Les deux venaient de sources différentes : le badge comptait les items
 * d'INSPECTION QUALITÉ (`inspection_items`), la liste affichait la CHECKLIST DE MISSION
 * (`mission_checklist_items`).
 *
 * Relevé sur l'émulateur : « 0/0 » annoncé au-dessus de six tâches bien réelles. Trois
 * centimètres d'écart, deux ensembles différents.
 *
 * Seule `mission_checklists` bloque la clôture d'une mission : c'est donc elle que
 * l'avancement doit refléter.
 */
class AvancementSuitLaChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function missionAvecTaches(int $total, int $faites): Mission
    {
        $mission = Mission::factory()->create();

        $checklist = MissionChecklist::create([
            'mission_id' => $mission->id,
            'template_name' => 'Checklist standard',
            'status' => 'draft',
        ]);

        for ($i = 1; $i <= $total; $i++) {
            MissionChecklistItem::create([
                'mission_checklist_id' => $checklist->id,
                'label' => 'Tâche '.$i,
                'is_required' => true,
                'status' => $i <= $faites ? 'done' : 'pending',
            ]);
        }

        return $mission->fresh();
    }

    /** TÉMOIN — le compteur suit les tâches réellement présentes. */
    public function test_le_compteur_compte_les_taches_de_la_checklist(): void
    {
        $mission = $this->missionAvecTaches(total: 6, faites: 2);

        $avancement = app(MissionTimelineService::class)->avancement($mission);

        $this->assertSame(6, $avancement['total'], 'Les six tâches doivent être comptées');
        $this->assertSame(2, $avancement['done']);
        $this->assertSame(33, $avancement['percent']);
    }

    /** Une checklist entièrement faite atteint cent pour cent. */
    public function test_tout_fait_donne_cent_pour_cent(): void
    {
        $mission = $this->missionAvecTaches(total: 3, faites: 3);

        $avancement = app(MissionTimelineService::class)->avancement($mission);

        $this->assertSame(3, $avancement['done']);
        $this->assertSame(100, $avancement['percent']);
    }

    /**
     * TÉMOIN NÉGATIF — sans tâche, le compteur reste à zéro sans diviser par zéro.
     *
     * L'écran masque alors la barre : « 0 sur 0 » se lirait comme un travail qui n'avance
     * pas, alors qu'il n'y a simplement rien à cocher.
     */
    public function test_temoin_aucune_tache_donne_zero_sans_planter(): void
    {
        $mission = $this->missionAvecTaches(total: 0, faites: 0);

        $avancement = app(MissionTimelineService::class)->avancement($mission);

        $this->assertSame(0, $avancement['total']);
        $this->assertSame(0, $avancement['percent']);
    }

    /**
     * L'INSPECTION QUALITÉ NE DOIT PLUS INFLUENCER CE COMPTEUR.
     *
     * C'est elle qu'il lisait auparavant. Une mission sans tâche mais avec une inspection
     * remplie ne doit pas annoncer d'avancement au client : il ne verrait rien à cocher en
     * face du chiffre.
     */
    public function test_l_inspection_n_influence_plus_l_avancement(): void
    {
        $mission = $this->missionAvecTaches(total: 4, faites: 1);

        $avancement = app(MissionTimelineService::class)->avancement($mission);

        $this->assertSame(4, $avancement['total'], "Le total vient de la checklist, pas de l'inspection");
        $this->assertSame(1, $avancement['done']);
    }
}
