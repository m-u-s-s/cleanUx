<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\PricingEngine;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE CATALOGUE SE TRADUIT — LA CHAÎNE NE SE COUPE PLUS EN SON MILIEU.
 *
 * Le mécanisme de traduction existait, complet et éprouvé : table polymorphe `catalog_translations`,
 * trait `HasCatalogTranslations` avec sa chaîne de repli, écran d'administration, tests. Mais il
 * n'équipait que le QUESTIONNAIRE — `Question`, `QuestionStep`, `QuestionOption`. Le catalogue qui y
 * mène, lui, n'était traduisible nulle part : `Sector` et `Trade` n'avaient qu'une colonne `name`.
 *
 * Un client néerlandophone parcourait donc un carrousel de secteurs en français, choisissait un
 * métier en français, puis répondait à des questions en néerlandais. Ce n'est pas un défaut
 * d'affichage : c'est la porte d'entrée de la commande qui restait fermée à quatre des cinq langues
 * que `config/i18n.php` déclare actives.
 *
 * ── CE QUE CES TESTS FIXENT ──────────────────────────────────────────────────────────────────
 *
 * Le premier n'est pas décoratif. Sans lui, tous les tests de refus qui suivent passeraient au vert
 * en mesurant un écran cassé plutôt qu'une garde qui fonctionne.
 */
class CatalogueTraductionTest extends TestCase
{
    use RefreshDatabase;

    private Country $pays;

    private ServiceZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);

        $this->pays = Country::factory()->create();
        $this->zone = ServiceZone::factory()->create(['country_id' => $this->pays->id]);

        $this->actingAs(User::factory()->create([
            'role' => 'admin',
            'platform_role' => 'admin',
        ]));
    }

    private function ecran(): Testable
    {
        return Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone]);
    }

    public function test_temoin_un_administrateur_de_plein_exercice_enregistre_bien_une_traduction(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->ecran()->call('saveTranslation', 'trade', $metier->id, 'nl', 'name', 'Schilderwerk');

        $this->assertDatabaseHas('catalog_translations', [
            'translatable_id' => $metier->id,
            'locale' => 'nl',
            'field' => 'name',
            'value' => 'Schilderwerk',
        ]);
    }

    public function test_le_metier_se_lit_dans_la_langue_demandee_et_retombe_sur_le_francais(): void
    {
        $metier = Trade::query()->firstOrFail();
        $francais = $metier->name;

        $this->ecran()->call('saveTranslation', 'trade', $metier->id, 'nl', 'name', 'Schilderwerk');

        $this->app->setLocale('nl');
        $this->assertSame('Schilderwerk', $metier->fresh()->translate('name'));

        // L'allemand n'a pas été traduit : mieux vaut la mauvaise langue qu'un blanc.
        $this->app->setLocale('de');
        $this->assertSame($francais, $metier->fresh()->translate('name'));
    }

    public function test_le_secteur_se_traduit_aussi(): void
    {
        $secteur = Sector::query()->firstOrFail();

        $this->ecran()->call('saveTranslation', 'sector', $secteur->id, 'nl', 'name', 'Bouw en renovatie');

        $this->app->setLocale('nl');
        $this->assertSame('Bouw en renovatie', $secteur->fresh()->translate('name'));
    }

    /**
     * La liste des champs est FERMÉE.
     *
     * Sans elle, un champ arbitraire venu du navigateur créerait des lignes que rien ne lit jamais :
     * invisibles, jamais affichées, et pourtant grossissant à chaque tentative.
     */
    public function test_un_champ_hors_de_la_liste_n_ecrit_rien(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->ecran()->call('saveTranslation', 'trade', $metier->id, 'nl', 'slug', 'schilderwerk');

        $this->assertDatabaseMissing('catalog_translations', [
            'translatable_id' => $metier->id,
            'field' => 'slug',
        ]);
    }

    /** `tagline` appartient au secteur, pas au métier : les listes sont distinctes par type. */
    public function test_un_champ_valable_pour_l_autre_type_n_ecrit_rien(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->ecran()->call('saveTranslation', 'trade', $metier->id, 'nl', 'tagline', 'Iets');

        $this->assertDatabaseMissing('catalog_translations', [
            'translatable_id' => $metier->id,
            'field' => 'tagline',
        ]);
    }

    /**
     * Une langue DÉSACTIVÉE ne reçoit pas de traduction.
     *
     * `pt` est déclarée dans `config/i18n.php` mais éteinte. Y écrire produirait un texte que
     * personne n'affichera jamais, et que le badge « langues manquantes » ne saurait pas compter.
     */
    public function test_une_langue_desactivee_n_ecrit_rien(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->ecran()->call('saveTranslation', 'trade', $metier->id, 'pt', 'name', 'Pintura');

        $this->assertDatabaseMissing('catalog_translations', ['locale' => 'pt']);
    }

    /** La langue par défaut EST la colonne : la « traduire » créerait une seconde source. */
    public function test_la_langue_par_defaut_n_est_pas_traduisible(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->ecran()->call('saveTranslation', 'trade', $metier->id, 'fr', 'name', 'Autre peinture');

        $this->assertDatabaseMissing('catalog_translations', ['locale' => 'fr']);
    }

    /** Effacer le champ revient au libellé français, et ne laisse pas une chaîne vide derrière. */
    public function test_vider_le_champ_retire_la_traduction(): void
    {
        $metier = Trade::query()->firstOrFail();
        $ecran = $this->ecran();

        $ecran->call('saveTranslation', 'trade', $metier->id, 'nl', 'name', 'Schilderwerk');
        $this->assertDatabaseCount('catalog_translations', 1);

        $ecran->call('saveTranslation', 'trade', $metier->id, 'nl', 'name', '');
        $this->assertDatabaseCount('catalog_translations', 0);
    }

    // ─── Le chemin de LECTURE : ce que le client voit ──────────────────────────────────────

    /**
     * LE TÉMOIN DU PARCOURS : sans traduction, le carrousel montre bien le français.
     *
     * Il précède le test suivant pour la même raison que partout ailleurs : si le carrousel ne
     * rendait rien du tout, « le néerlandais s'affiche » et « le néerlandais ne s'affiche pas »
     * passeraient tous deux au vert.
     */
    public function test_temoin_le_carrousel_montre_le_libelle_francais_par_defaut(): void
    {
        $secteur = Sector::query()->firstOrFail();

        Livewire::test(OrderJourney::class)->assertSee($secteur->name);
    }

    public function test_le_carrousel_montre_le_secteur_dans_la_langue_du_client(): void
    {
        $secteur = Sector::query()->firstOrFail();
        $secteur->setTranslation('name', 'nl', 'Bouw en renovatie');

        $this->app->setLocale('nl');

        Livewire::test(OrderJourney::class)
            ->assertSee('Bouw en renovatie')
            ->assertDontSee($secteur->name);
    }

    /**
     * Le devis porte le nom que le client a VU.
     *
     * Un devis rédigé dans une autre langue que celle de la commande serait inopposable : c'est
     * déjà la règle des libellés de questions (`OrderDraftManager:229`), et le nom du métier est
     * la première ligne de ce devis.
     */
    public function test_la_ligne_de_devis_porte_le_nom_traduit(): void
    {
        $metier = Trade::query()->where('base_price_cents', '>', 0)->firstOrFail();
        $metier->setTranslation('name', 'nl', 'Schilderwerk');

        $this->app->setLocale('nl');

        $devis = app(PricingEngine::class)->quoteItem(
            $metier->fresh(),
            collect(),
            [],
            ['mode' => OrderMode::SCHEDULED],
        );

        $libelles = collect($devis->lines)->pluck('label')->all();

        $this->assertContains('Schilderwerk', $libelles);
        $this->assertNotContains($metier->name, $libelles);
    }

    /** L'écran dit ce qui MANQUE : un trou se découvre sinon en production. */
    public function test_l_ecran_annonce_les_langues_manquantes(): void
    {
        $this->ecran()->assertOk()->assertSee('Traductions');

        $metier = Trade::query()->firstOrFail();
        $manquantes = $metier->missingLocales(['name']);

        $this->assertContains('nl', $manquantes);
        $this->assertContains('de', $manquantes);
        $this->assertNotContains('fr', $manquantes, 'La langue de base ne peut pas manquer : elle EST la colonne.');
        $this->assertNotContains('pt', $manquantes, 'Une langue éteinte ne manque pas — elle ne se traduit pas.');
    }
}
