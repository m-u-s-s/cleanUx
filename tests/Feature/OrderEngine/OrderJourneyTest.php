<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\OrderDraft;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le parcours de commande, du point de vue d'un visiteur.
 *
 * Ce que ces tests verrouillent, ce sont les lois qui font vendre : un prix avant toute demande
 * d'identité, aucune réponse perdue en revenant en arrière, un questionnaire réduit à l'essentiel
 * en mode urgent, et des modes qui ne s'offrent que là où ils ont un sens.
 */
class OrderJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    /** La page est publique : rien ne doit se dresser entre le visiteur et son estimation. */
    public function test_the_journey_is_reachable_without_an_account(): void
    {
        $this->get('/commander')->assertOk();
    }

    /**
     * LA loi 1, rendue vérifiable.
     *
     * Un visiteur sans compte compose sa commande et obtient un PRIX. Aucun compte n'est créé au
     * passage — un prix caché derrière un formulaire d'inscription est la première cause d'abandon.
     */
    public function test_a_visitor_gets_a_price_without_ever_being_asked_who_they_are(): void
    {
        $usersBefore = User::count();

        $component = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->dispatch('question-answered', code: 'surface_m2', value: 40, valid: true)
            ->dispatch('question-answered', code: 'etendue', value: 'murs_plafonds', valid: true);

        // 12 000 + 40 × 250 + 4 500 = 26 500
        $this->assertSame(26500, $component->instance()->quote()->minCents);
        $this->assertSame($usersBefore, User::count(), 'Le parcours a créé un compte pour afficher un prix.');
        $this->assertNull(OrderDraft::firstOrFail()->client_id);
    }

    /**
     * LA loi 10 : revenir en arrière ne perd rien.
     *
     * Le client répond, retourne au choix du métier, revient — et retrouve ses réponses. Elles
     * vivent dans le panier, pas dans le composant, et c'est ce qui rend la promesse tenable.
     */
    public function test_going_back_and_returning_keeps_every_answer(): void
    {
        $component = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->dispatch('question-answered', code: 'surface_m2', value: 40, valid: true)
            ->dispatch('question-answered', code: 'etendue', value: 'murs_plafonds', valid: true)
            ->call('backToTrades')
            ->call('selectTrade', $this->peinture()->id);

        $answers = $component->instance()->answers;

        $this->assertSame('murs_plafonds', $answers['etendue'] ?? null);
        $this->assertEquals(40, $answers['surface_m2'] ?? null);
        $this->assertSame(26500, $component->instance()->quote()->minCents);
    }

    /** Le prix se recalcule à chaque réponse : le client voit l'effet de son choix immédiatement. */
    public function test_the_price_moves_with_each_answer(): void
    {
        $component = Livewire::test(OrderJourney::class)->call('selectTrade', $this->peinture()->id);

        $base = $component->instance()->quote()->minCents;

        $component->dispatch('question-answered', code: 'etendue', value: 'complet', valid: true);

        $this->assertGreaterThan($base, $component->instance()->quote()->minCents);
    }

    /**
     * L'intention de mode voyage dans l'URL.
     *
     * L'application mobile n'a plus d'écran de réservation natif : ses trois cartes — immédiat,
     * rendez-vous, multi-services — ouvrent toutes ce parcours. Sans ce paramètre, elles
     * arriveraient toutes les trois sur le même écran planifié, et le choix d'entrée deviendrait
     * décoratif : le client demanderait « immédiat » puis devrait le redemander.
     *
     * L'intention n'est pas appliquée tout de suite — les modes dépendent du métier, et aucun
     * n'est encore choisi. Elle attend la sélection.
     */
    public function test_an_intended_mode_survives_until_a_trade_is_chosen(): void
    {
        Livewire::withQueryParams(['mode' => 'asap'])
            ->test(OrderJourney::class)
            ->assertSet('mode', 'scheduled')
            ->call('selectTrade', Trade::where('slug', 'plumbing')->firstOrFail()->id)
            ->assertSet('mode', 'asap');
    }

    /**
     * Un métier qui n'accepte pas le mode demandé le DIT.
     *
     * Un ravalement de façade n'est pas un service immédiat. Basculer en silence sur « planifié »
     * laisserait le client croire qu'il a commandé une intervention dans l'heure.
     */
    public function test_a_trade_that_refuses_the_intended_mode_says_so(): void
    {
        Livewire::withQueryParams(['mode' => 'asap'])
            ->test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->assertSet('mode', 'scheduled')
            ->assertSee('n’accepte pas les interventions immédiates');
    }

    /**
     * Le mode retenu est ÉCRIT SUR LA COMMANDE, pas seulement à l'écran.
     *
     * `reprice()` recalcule à partir de `order_drafts.mode`, jamais de la propriété du composant.
     * Les deux qui divergent, c'est l'écran qui annonce une majoration d'urgence pendant que le
     * devis enregistré — celui que la confirmation reprend — est calculé au tarif planifié. Le
     * client voit un prix, il en paie un autre.
     */
    public function test_the_mode_reaches_the_order_not_just_the_screen(): void
    {
        $component = Livewire::withQueryParams(['mode' => 'asap'])
            ->test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'plumbing')->firstOrFail()->id);

        $this->assertSame('asap', OrderDraft::latest('id')->first()->mode);
        $this->assertSame('asap', $component->get('mode'));
    }

    /**
     * Et le repli suit la même règle.
     *
     * Passer d'un métier qui accepte l'immédiat à un métier qui le refuse ramène l'écran au
     * planifié — la commande doit suivre, sinon elle reste facturée avec la majoration d'urgence
     * d'un mode que le client ne voit plus nulle part.
     */
    public function test_falling_back_to_scheduled_also_updates_the_order(): void
    {
        Livewire::withQueryParams(['mode' => 'asap'])
            ->test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'plumbing')->firstOrFail()->id)
            ->call('selectTrade', $this->peinture()->id)
            ->assertSet('mode', 'scheduled');

        $this->assertSame('scheduled', OrderDraft::latest('id')->first()->mode);
    }

    /** Une valeur inventée dans l'URL est ignorée, sans rien casser. */
    public function test_a_bogus_mode_in_the_url_is_ignored(): void
    {
        Livewire::withQueryParams(['mode' => 'n’importe quoi'])
            ->test(OrderJourney::class)
            ->assertOk()
            ->call('selectTrade', Trade::where('slug', 'plumbing')->firstOrFail()->id)
            ->assertSet('mode', 'scheduled');
    }

    /**
     * Le dock appelle bien `selectTrade`.
     *
     * Les tests choisissent un métier par `->call('selectTrade', ...)`, ce qui ne prouve rien du
     * bouton. Le clic passe désormais par Alpine — pour envelopper la sélection dans une transition
     * d'élément partagé — et un `wire:click` supprimé par mégarde laisserait un dock inerte que
     * toute la suite continuerait de déclarer vert.
     */
    public function test_the_dock_actually_calls_select_trade(): void
    {
        $sector = $this->peinture()->sector;

        $html = Livewire::test(OrderJourney::class)
            ->set('sectorId', $sector->id)
            ->html();

        $this->assertStringContainsString(
            'selectTrade('.$this->peinture()->id.')',
            $html,
            'Aucun élément du dock n’appelle selectTrade : le métier ne peut plus être choisi.',
        );
    }

    /** Et la variation se dit en mots, pas seulement en chiffres. */
    public function test_the_last_change_is_explained(): void
    {
        $component = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->dispatch('question-answered', code: 'etendue', value: 'murs_plafonds', valid: true);

        $this->assertSame('Que faut-il peindre ?', $component->instance()->lastChange()['label']);
    }

    /**
     * L'explication de la variation est LUE SUR MOBILE aussi.
     *
     * Elle ne vivait que dans la carte collante du grand écran. Sur un produit conçu à 390 px
     * d'abord, c'était le mauvais sens : le client mobile — le plus nombreux — voyait le montant
     * bouger sans jamais savoir pourquoi. Le libellé doit donc apparaître DEUX fois dans le rendu :
     * la carte de droite et la barre du pouce.
     */
    public function test_the_price_variation_is_explained_on_the_thumb_bar_too(): void
    {
        $html = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->dispatch('question-answered', code: 'etendue', value: 'murs_plafonds', valid: true)
            ->html();

        /*
         * Trois occurrences : l'intitulé de la question elle-même, la carte collante du grand
         * écran, et la barre du pouce. Deux signifient que la barre basse ne le porte pas — c'était
         * l'état avant ce correctif.
         */
        $this->assertSame(
            3,
            substr_count($html, 'Que faut-il peindre ?'),
            'L’explication de la variation manque à la barre basse mobile.',
        );
    }

    /**
     * Le mode immédiat pose MOINS de questions.
     *
     * La vitesse prime sur la précision : poser huit questions à quelqu'un dont l'eau coule dans
     * le couloir serait absurde. La fourchette annoncée est simplement plus large.
     */
    public function test_the_immediate_mode_asks_only_what_is_essential(): void
    {
        $plomberie = Trade::where('slug', 'plumbing')->firstOrFail();

        $component = Livewire::test(OrderJourney::class)->call('selectTrade', $plomberie->id);
        $planned = $component->instance()->questions()->count();

        $component->call('setMode', OrderMode::ASAP);
        $urgent = $component->instance()->questions()->count();

        $this->assertLessThan($planned, $urgent);
        $this->assertGreaterThan(0, $urgent, 'Le mode immédiat ne pose plus aucune question.');
    }

    /** Un métier fermé au mode immédiat ne le propose pas — et le refuse si on insiste. */
    public function test_a_trade_closed_to_the_immediate_mode_never_offers_it(): void
    {
        $component = Livewire::test(OrderJourney::class)->call('selectTrade', $this->peinture()->id);

        $this->assertNotContains(OrderMode::ASAP, $component->instance()->availableModes());

        $component->call('setMode', OrderMode::ASAP);

        $this->assertSame(OrderMode::SCHEDULED, $component->instance()->mode);
    }

    /** La majoration d'urgence se voit dans le prix, avant toute confirmation. */
    public function test_the_immediate_mode_shows_its_surcharge_before_confirming(): void
    {
        $plomberie = Trade::where('slug', 'plumbing')->firstOrFail();

        $component = Livewire::test(OrderJourney::class)->call('selectTrade', $plomberie->id);
        $planned = $component->instance()->quote()->minCents;

        $component->call('setMode', OrderMode::ASAP);

        $this->assertGreaterThan($planned, $component->instance()->quote()->minCents);
    }

    /** Une question cachée ne s'affiche pas — et son supplément n'entre pas dans le prix. */
    public function test_a_conditional_question_appears_only_when_its_condition_holds(): void
    {
        $component = Livewire::test(OrderJourney::class)->call('selectTrade', $this->peinture()->id);

        // `allVisibleQuestions` et non `visibleQuestions` : la seconde ne rend que l'étape
        // COURANTE depuis que le questionnaire se découpe, et « application » comme
        // « type_pistolet » vivent sur la seconde étape du métier seedé.
        $codes = fn () => $component->instance()->allVisibleQuestions->pluck('code')->all();

        $component->dispatch('question-answered', code: 'application', value: 'rouleau', valid: true);
        $this->assertNotContains('type_pistolet', $codes());

        $component->dispatch('question-answered', code: 'application', value: 'pistolet', valid: true);
        $this->assertContains('type_pistolet', $codes());
    }

    /** Le panier est écrit au fil de l'eau, pas à la validation : un onglet fermé ne perd rien. */
    public function test_answers_are_persisted_as_they_come(): void
    {
        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->dispatch('question-answered', code: 'etendue', value: 'murs_plafonds', valid: true);

        $answer = OrderDraft::firstOrFail()->items()->firstOrFail()
            ->answers()->where('question_code', 'etendue')->firstOrFail();

        $this->assertSame('Murs et plafonds', $answer->answer_label_snapshot);
    }

    /** Un métier au devis obligatoire ne promet aucun prix, et le dit. */
    public function test_a_quote_only_trade_announces_no_price(): void
    {
        Livewire::test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'roofing')->value('id'))
            ->assertSee('demande un devis');
    }

    /** Le secteur retenu montre ses métiers, et rien que les siens. */
    public function test_choosing_a_sector_shows_only_its_trades(): void
    {
        $sector = Sector::where('slug', 'espaces-verts')->firstOrFail();

        $component = Livewire::test(OrderJourney::class)->call('selectSector', $sector->id);

        $slugs = $component->instance()->trades()->pluck('slug');

        $this->assertTrue($slugs->contains('jardinage'));
        $this->assertFalse($slugs->contains('peinture'));
    }

    /** L'adresse d'entrée directe fonctionne : un lien partagé ouvre le bon métier. */
    public function test_a_deep_link_opens_the_right_trade(): void
    {
        $this->get('/commander/batiment-renovation/peinture')
            ->assertOk()
            ->assertSee('Quelle surface à peindre ?');
    }

    /**
     * Le jeton du panier n'est PAS une propriété pilotable depuis le navigateur.
     *
     * Une propriété Livewire voyage par le client : si le jeton en était une, changer sa valeur
     * dans les outils de développement ouvrirait le panier de quelqu'un d'autre. Il vit donc en
     * session.
     */
    public function test_the_basket_token_comes_from_the_session(): void
    {
        $component = Livewire::test(OrderJourney::class);

        $this->assertSame(session('order_draft_token'), $component->instance()->sessionToken);
        $this->assertNotEmpty($component->instance()->sessionToken);
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }
}
