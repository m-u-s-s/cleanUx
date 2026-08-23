<?php

namespace Tests\Feature\Trajet;

use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Models\Question;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\LocationRole;
use App\Support\Domain\QuestionType;
use App\Support\Domain\TradeRouteRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** UN MÉTIER DEVIENT UN TRAJET PARCE QUE SON PARCOURS LE DIT — et pour aucune autre raison. */
class CatalogueTrajetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function metier(array $attributs = []): Trade
    {
        return Trade::factory()->create($attributs);
    }

    private function localisation(Trade $metier, string $role, array $attributs = []): Question
    {
        return Question::create([
            'trade_id' => $metier->id,
            'code' => $role === LocationRole::PICKUP ? 'depart' : 'arrivee',
            'label' => LocationRole::label($role),
            'type' => QuestionType::LOCATION,
            'location_role' => $role,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => $role === LocationRole::PICKUP ? 1 : 2,
        ] + $attributs);
    }

    public function test_deux_localisations_font_un_trajet(): void
    {
        $metier = $this->metier();

        $this->localisation($metier, LocationRole::PICKUP);
        $this->assertFalse(
            TradeRouteRules::estUnTrajet($metier->fresh()),
            'Un départ seul ne décrit pas un trajet : il décrit une adresse.'
        );

        $this->localisation($metier, LocationRole::DROPOFF);

        $this->assertTrue(TradeRouteRules::estUnTrajet($metier->fresh()));
        $this->assertTrue($metier->fresh()->estUnTrajet());
    }

    /** LE TÉMOIN : sans localisation, rien ne bascule — et le catalogue existant ne change pas. */
    public function test_un_metier_ordinaire_n_est_pas_un_trajet(): void
    {
        $metier = $this->metier();

        Question::create([
            'trade_id' => $metier->id,
            'code' => 'surface',
            'label' => 'Surface',
            'type' => QuestionType::SURFACE,
            'is_active' => true,
        ]);

        $this->assertFalse(TradeRouteRules::estUnTrajet($metier->fresh()));
        $this->assertNull($metier->fresh()->route_rules_since);
    }

    public function test_la_date_de_bascule_est_posee_puis_effacee(): void
    {
        $metier = $this->metier();

        $this->localisation($metier, LocationRole::PICKUP);
        $arrivee = $this->localisation($metier, LocationRole::DROPOFF);

        $this->assertNotNull(
            $metier->fresh()->route_rules_since,
            'Sans cette date, la période de grâce des prestataires déjà inscrits n’a pas d’origine.'
        );

        // Désactiver l'arrivée : le métier n'emmène plus personne nulle part.
        $arrivee->update(['is_active' => false]);

        $this->assertFalse(TradeRouteRules::estUnTrajet($metier->fresh()));
        $this->assertNull(
            $metier->fresh()->route_rules_since,
            'Une exigence levée ne doit pas continuer de courir contre les prestataires.'
        );
    }

    public function test_la_suppression_de_la_question_d_arrivee_annule_le_trajet(): void
    {
        $metier = $this->metier();
        $this->localisation($metier, LocationRole::PICKUP);
        $arrivee = $this->localisation($metier, LocationRole::DROPOFF);

        $this->assertTrue(TradeRouteRules::estUnTrajet($metier->fresh()));

        $arrivee->delete();

        $this->assertFalse(TradeRouteRules::estUnTrajet($metier->fresh()));
        $this->assertNull($metier->fresh()->route_rules_since);
    }

    public function test_le_scope_ne_rend_que_les_metiers_de_trajet(): void
    {
        $course = $this->metier(['name' => 'Course']);
        $this->localisation($course, LocationRole::PICKUP);
        $this->localisation($course, LocationRole::DROPOFF);

        $peinture = $this->metier(['name' => 'Peinture']);
        $this->localisation($peinture, LocationRole::PICKUP);

        $ids = Trade::query()->trajet()->pluck('id')->all();

        $this->assertContains($course->id, $ids);
        $this->assertNotContains(
            $peinture->id,
            $ids,
            'Une seule localisation ne suffit pas : le scope compterait alors tous les métiers qui demandent une adresse.'
        );
    }

    public function test_le_constructeur_refuse_deux_departs(): void
    {
        $metier = $this->metier();
        $this->localisation($metier, LocationRole::PICKUP, ['label' => 'Où êtes-vous ?']);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $metier])
            ->call('startNew')
            ->set('form.label', 'Adresse de prise en charge')
            ->set('form.code', 'depart_bis')
            ->set('form.type', QuestionType::LOCATION)
            ->set('form.location_role', LocationRole::PICKUP)
            ->call('save')
            ->assertHasErrors('form.location_role');

        $this->assertSame(
            1,
            Question::where('trade_id', $metier->id)->where('location_role', LocationRole::PICKUP)->count()
        );
    }

    /** LE TÉMOIN du test précédent : la même séquence, avec l'autre rôle, doit ABOUTIR. */
    public function test_le_constructeur_accepte_l_arrivee_quand_le_depart_existe(): void
    {
        $metier = $this->metier();
        $this->localisation($metier, LocationRole::PICKUP);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $metier])
            ->call('startNew')
            ->set('form.label', 'Où allez-vous ?')
            ->set('form.code', 'destination')
            ->set('form.type', QuestionType::LOCATION)
            ->set('form.location_role', LocationRole::DROPOFF)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(TradeRouteRules::estUnTrajet($metier->fresh()));
    }

    public function test_le_role_disparait_quand_la_question_change_de_type(): void
    {
        $metier = $this->metier();
        $depart = $this->localisation($metier, LocationRole::PICKUP);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $metier])
            ->call('edit', $depart->id)
            ->set('form.type', QuestionType::TEXT)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(
            $depart->fresh()->location_role,
            'Un rôle de trajet resté accroché à un champ texte ferait passer le métier pour un trajet qu’il n’est plus.'
        );
    }

    public function test_les_regles_taxi_sont_datees_a_la_bascule_seulement(): void
    {
        $metier = $this->metier(['taxi_rules' => false]);
        $this->assertNull($metier->taxi_rules_since);

        $metier->update(['taxi_rules' => true]);
        $pose = $metier->fresh()->taxi_rules_since;
        $this->assertNotNull($pose);

        // Un enregistrement qui ne touche pas la règle ne repousse pas l'échéance : sans quoi
        // corriger une faute de frappe dans la description relancerait le délai de tout le monde.
        $metier->fresh()->update(['short_description' => 'Course en ville']);
        $this->assertEquals($pose, $metier->fresh()->taxi_rules_since);

        $metier->fresh()->update(['taxi_rules' => false]);
        $this->assertNull($metier->fresh()->taxi_rules_since);
    }

    public function test_les_regles_taxi_sont_independantes_du_trajet(): void
    {
        // Une dépanneuse va d'un point à un autre sans obéir aux règles du transport de personnes.
        $remorquage = $this->metier(['taxi_rules' => false]);
        $this->localisation($remorquage, LocationRole::PICKUP);
        $this->localisation($remorquage, LocationRole::DROPOFF);

        $this->assertTrue(TradeRouteRules::estUnTrajet($remorquage->fresh()));
        $this->assertFalse((bool) $remorquage->fresh()->taxi_rules);

        // Et l'inverse : cocher les règles taxi ne fabrique pas un trajet.
        $atelier = $this->metier(['taxi_rules' => true]);
        $this->assertFalse(TradeRouteRules::estUnTrajet($atelier->fresh()));
    }
}
