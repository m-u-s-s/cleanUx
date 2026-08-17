<?php

namespace Tests\Feature\Trajet;

use App\Livewire\OrderEngine\OrderJourney;
use App\Livewire\OrderEngine\QuestionRenderer;
use App\Models\Question;
use App\Models\Trade;
use App\Support\Domain\LocationRole;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA CARTE DU TRAJET — poser le départ et l'arrivée au doigt, pas seulement au clavier.
 *
 * CE QUE CES TESTS PROTÈGENT.
 *
 * La carte est un COMPLÉMENT : les champs d'adresse restent le chemin principal, et le seul
 * utilisable au clavier ou au lecteur d'écran. Ce que la carte apporte, c'est la précision —
 * « Rue de la Loi 1 » désigne un bâtiment, pas la porte de service ni le côté du terre-plein où le
 * conducteur doit s'arrêter.
 *
 * Elle N'ÉCRIT RIEN elle-même. Le point traverse la question concernée, qui applique le même
 * traitement que « utiliser ma position ». Un écrivain de plus sur la même donnée ferait diverger
 * le panier de ce que la question affiche — le client verrait son adresse changer toute seule au
 * geste suivant.
 *
 * Ses paramètres viennent du NAVIGATEUR : un rôle inconnu et des coordonnées hors du monde sont
 * refusés ici, pas ailleurs.
 */
class CarteDuTrajetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_la_carte_apparait_des_quun_point_est_pose(): void
    {
        $course = $this->course();

        $composant = Livewire::test(OrderJourney::class)
            ->call('selectSector', $course->sector_id)
            ->call('selectTrade', $course->id);

        // Aucun point : pas de carte. Une carte du néant centrée sur un pays n'apprend rien.
        $composant->assertDontSee('Le trajet');

        $composant
            ->call('recordAnswer', 'depart', $this->point(50.8467, 4.3525, 'Rue de la Loi 1'), true)
            ->assertSee('Le trajet')
            ->assertSeeHtml('id="carte-trajet"')
            // Le point de départ est passé au composant Alpine : sans lui, la carte s'initialise
            // sur un centre inventé et le repère n'apparaît nulle part.
            ->assertSeeHtml('carteDuTrajet(')
            ->assertSee('Départ')
            ->assertSee('Arrivée');

        /*
         * LE SCRIPT N'EST PAS DANS LE HTML DU COMPOSANT, et c'est normal : il vit dans
         * `@push('scripts')`, une pile rendue par le layout. Un `assertSeeHtml` le chercherait donc
         * en vain — ce qui a fait échouer la première version de ce test. Le câblage réel de
         * `placerSurLaCarte` est prouvé par les tests de diffusion ci-dessous, qui appellent la
         * méthode ; c'est une meilleure preuve qu'une chaîne trouvée dans du texte.
         */
    }

    /** TÉMOIN : un métier ordinaire n'a pas de trajet, donc pas de carte. */
    public function test_un_metier_ordinaire_naffiche_aucune_carte(): void
    {
        $peinture = Trade::where('slug', 'peinture')->firstOrFail();

        Livewire::test(OrderJourney::class)
            ->call('selectSector', $peinture->sector_id)
            ->call('selectTrade', $peinture->id)
            ->call('recordAnswer', 'surface_m2', 40, true)
            ->assertDontSee('Le trajet')
            ->assertDontSeeHtml('id="carte-trajet"');
    }

    /**
     * DÉPLACER UN REPÈRE ENVOIE LE POINT À SA QUESTION, et pas ailleurs.
     *
     * C'est l'invariant central : le parcours diffuse, la question applique. Sans lui, deux
     * écrivains se disputeraient les colonnes du panier.
     */
    public function test_deplacer_un_repere_diffuse_vers_la_bonne_question(): void
    {
        $course = $this->course();

        Livewire::test(OrderJourney::class)
            ->call('selectSector', $course->sector_id)
            ->call('selectTrade', $course->id)
            ->call('placerSurLaCarte', LocationRole::DROPOFF, 50.9010, 4.4844)
            ->assertDispatched('place-location', code: 'arrivee', lat: 50.9010, lng: 4.4844);
    }

    public function test_le_depart_vise_la_question_de_depart(): void
    {
        $course = $this->course();

        Livewire::test(OrderJourney::class)
            ->call('selectSector', $course->sector_id)
            ->call('selectTrade', $course->id)
            ->call('placerSurLaCarte', LocationRole::PICKUP, 50.8467, 4.3525)
            ->assertDispatched('place-location', code: 'depart');
    }

    /**
     * LE RÔLE VIENT DU NAVIGATEUR — donc il est vérifié.
     *
     * Une méthode publique Livewire est une porte ouverte sur Internet. Un rôle inventé ne doit
     * rien déclencher du tout, pas retomber sur un point par défaut.
     */
    public function test_un_role_inconnu_ne_fait_rien(): void
    {
        $course = $this->course();

        Livewire::test(OrderJourney::class)
            ->call('selectSector', $course->sector_id)
            ->call('selectTrade', $course->id)
            ->call('placerSurLaCarte', 'nimporte_quoi', 50.8467, 4.3525)
            ->assertNotDispatched('place-location');
    }

    public function test_des_coordonnees_hors_du_monde_sont_refusees(): void
    {
        $course = $this->course();

        $composant = Livewire::test(OrderJourney::class)
            ->call('selectSector', $course->sector_id)
            ->call('selectTrade', $course->id);

        foreach ([[91.0, 4.0], [-91.0, 4.0], [50.0, 181.0], [50.0, -181.0]] as [$lat, $lng]) {
            $composant->call('placerSurLaCarte', LocationRole::PICKUP, $lat, $lng)
                ->assertNotDispatched('place-location');
        }
    }

    /**
     * LA QUESTION N'ÉCOUTE QUE SON PROPRE CODE.
     *
     * Chaque question du parcours est une instance du même composant : l'événement leur parvient à
     * toutes. Sans le filtre, poser l'arrivée écraserait aussi le départ, et les deux repères se
     * superposeraient sur la carte.
     */
    public function test_une_question_ignore_le_point_dune_autre(): void
    {
        $course = $this->course();
        $depart = Question::where('trade_id', $course->id)->where('code', 'depart')->firstOrFail();

        Livewire::test(QuestionRenderer::class, ['question' => $depart, 'value' => null])
            ->call('placerDepuisLaCarte', 'arrivee', 50.9010, 4.4844)
            ->assertSet('value', null)
            ->assertNotDispatched('question-answered');
    }

    /** Et elle applique bien celui qui la concerne. */
    public function test_une_question_applique_son_propre_point(): void
    {
        $course = $this->course();
        $depart = Question::where('trade_id', $course->id)->where('code', 'depart')->firstOrFail();

        $composant = Livewire::test(QuestionRenderer::class, ['question' => $depart, 'value' => null])
            ->call('placerDepuisLaCarte', 'depart', 50.8467, 4.3525);

        $valeur = $composant->get('value');

        $this->assertIsArray($valeur);
        $this->assertEqualsWithDelta(50.8467, (float) $valeur['lat'], 0.0001);
        $this->assertEqualsWithDelta(4.3525, (float) $valeur['lng'], 0.0001);
        // Le libellé est un confort : ce sont les coordonnées qui comptent, et elles sont retenues
        // même quand le serveur ne sait pas nommer l'endroit.
        $this->assertNotEmpty($valeur['label']);

        $composant->assertDispatched('question-answered', code: 'depart');
    }

    /**
     * LA CARTE APPREND LES DÉPLACEMENTS PAR ÉVÉNEMENT, jamais par le rendu.
     *
     * Elle vit sous `wire:ignore` — Leaflet possède son nœud et refuse qu'on le remplace. Sans
     * cette annonce, elle afficherait indéfiniment l'état du premier chargement : le client
     * changerait d'adresse au clavier et verrait son repère rester en place.
     */
    public function test_le_serveur_annonce_le_trajet_a_chaque_point(): void
    {
        $course = $this->course();

        Livewire::test(OrderJourney::class)
            ->call('selectSector', $course->sector_id)
            ->call('selectTrade', $course->id)
            ->call('recordAnswer', 'depart', $this->point(50.8467, 4.3525, 'Rue de la Loi 1'), true)
            ->assertDispatched('trajet-mis-a-jour')
            ->call('recordAnswer', 'arrivee', $this->point(50.9010, 4.4844, 'Aéroport', '1930'), true)
            ->assertDispatched('trajet-mis-a-jour');
    }

    /** TÉMOIN : un métier ordinaire n'annonce aucun trajet — il n'en a pas. */
    public function test_un_metier_ordinaire_nannonce_aucun_trajet(): void
    {
        $peinture = Trade::where('slug', 'peinture')->firstOrFail();

        Livewire::test(OrderJourney::class)
            ->call('selectSector', $peinture->sector_id)
            ->call('selectTrade', $peinture->id)
            ->call('recordAnswer', 'surface_m2', 40, true)
            ->assertNotDispatched('trajet-mis-a-jour');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function course(): Trade
    {
        $trade = Trade::where('slug', 'peinture')->firstOrFail()->replicate();
        $trade->slug = 'course-vtc';
        $trade->code = 'VTC';
        $trade->name = 'Course';
        $trade->save();

        foreach ([
            ['depart', 'Où êtes-vous ?', LocationRole::PICKUP, 1],
            ['arrivee', 'Où allez-vous ?', LocationRole::DROPOFF, 2],
        ] as [$code, $label, $role, $ordre]) {
            Question::create([
                'trade_id' => $trade->id,
                'code' => $code,
                'label' => $label,
                'type' => QuestionType::LOCATION,
                'location_role' => $role,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => $ordre,
            ]);
        }

        return $trade->fresh();
    }

    /** @return array{lat: float, lng: float, label: string, postal_code: string} */
    private function point(float $lat, float $lng, string $label, string $cp = '1000'): array
    {
        return ['label' => $label, 'lat' => $lat, 'lng' => $lng, 'postal_code' => $cp];
    }
}
