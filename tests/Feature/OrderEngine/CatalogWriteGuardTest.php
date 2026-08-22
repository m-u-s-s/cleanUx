<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un administrateur en LECTURE SEULE ne modifie rien du catalogue.
 *
 * CE QUE CE FICHIER CORRIGE. `refusesWrite()` existait sur cet écran et consultait bien la Policy —
 * mais il ne gardait que les QUATRE mutations de réordonnancement. Les sept autres — enregistrer un
 * secteur, l'archiver, le retirer du carrousel, rattacher un métier, l'activer, l'ouvrir dans une
 * zone, en créer un — étaient ouvertes à quiconque franchissait `EnforcesAdminAccess`.
 *
 * Or ce trait s'arrête à « est-ce un administrateur » : un `platform_role` à « admin » assorti d'un
 * `access_scope` à « readonly » le franchit. Le compte destiné à consulter pouvait donc archiver un
 * secteur du carrousel client.
 *
 * LE TEST EST ÉCRIT PAR ÉNUMÉRATION plutôt que méthode par méthode : une mutation ajoutée demain
 * sans garde doit faire échouer la suite, et non passer inaperçue parce que personne n'aura pensé à
 * lui écrire son test.
 */
class CatalogWriteGuardTest extends TestCase
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
            'access_scope' => 'readonly',
        ]));
    }

    private function ecran(): Testable
    {
        return Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone]);
    }

    public function test_le_lecteur_seul_atteint_bien_l_ecran(): void
    {
        // Il doit CONSULTER : le refus porte sur l'écriture, pas sur l'accès. Sans cette
        // vérification, on pourrait « corriger » le trou en fermant la porte à tout le monde.
        $this->ecran()->assertOk();
    }

    public function test_il_n_enregistre_pas_de_secteur(): void
    {
        $avant = Sector::count();

        $this->ecran()
            ->call('startNewSector')
            ->set('sectorForm.name', 'Secteur interdit')
            ->set('sectorForm.slug', 'secteur-interdit')
            ->call('saveSector');

        $this->assertSame($avant, Sector::count());
        $this->assertDatabaseMissing('sectors', ['slug' => 'secteur-interdit']);
    }

    public function test_il_ne_retire_pas_un_secteur_du_carrousel(): void
    {
        $secteur = Sector::query()->firstOrFail();
        $avant = (bool) $secteur->is_active;

        $this->ecran()->call('toggleSector', $secteur->id);

        // Le carrousel est ce que voit TOUT visiteur : l'en retirer est une décision d'écriture.
        $this->assertSame($avant, (bool) $secteur->fresh()->is_active);
    }

    public function test_il_n_archive_pas_un_secteur(): void
    {
        $secteur = Sector::query()->firstOrFail();

        $this->ecran()
            ->call('confirmArchiveSector', $secteur->id)
            ->call('archiveSector');

        $this->assertNotNull(Sector::find($secteur->id));
    }

    /**
     * La traduction est une ÉCRITURE du catalogue, au même titre que le reste.
     *
     * Un libellé est ce que le client LIT : le changer dans cinq langues change le produit tel
     * qu'il se présente. Ce test existe parce que ce fichier l'exige de toute mutation nouvelle —
     * `saveTranslation()` a été ajoutée après lui, et devait donc venir s'y inscrire.
     */
    public function test_il_ne_traduit_pas_un_libelle(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->ecran()->call('saveTranslation', 'trade', $metier->id, 'nl', 'name', 'Verboden vertaling');

        $this->assertDatabaseMissing('catalog_translations', ['value' => 'Verboden vertaling']);
    }

    public function test_il_ne_rattache_pas_un_metier_a_un_secteur(): void
    {
        $metier = Trade::query()->firstOrFail();
        $secteur = Sector::query()->where('id', '!=', $metier->sector_id)->firstOrFail();
        $avant = $metier->sector_id;

        $this->ecran()->call('attachTrade', $metier->id, $secteur->id);

        $this->assertSame($avant, $metier->fresh()->sector_id);
    }

    public function test_il_n_active_pas_un_metier(): void
    {
        $metier = Trade::query()->firstOrFail();
        $avant = (bool) $metier->is_active;

        $this->ecran()->call('toggleTrade', $metier->id);

        $this->assertSame($avant, (bool) $metier->fresh()->is_active);
    }

    public function test_il_n_ouvre_pas_un_metier_dans_une_zone(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->ecran()->call('basculerMetierDansLaZone', $metier->id);

        // Ouvrir un métier dans une zone décidera du prix et de la disponibilité : c'est une
        // écriture, même si la ligne créée est modeste.
        $this->assertDatabaseMissing('trade_zone_pricing', [
            'trade_id' => $metier->id,
            'service_zone_id' => $this->zone->id,
        ]);
    }

    public function test_il_ne_cree_pas_de_metier(): void
    {
        $secteur = Sector::query()->firstOrFail();

        $this->ecran()
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Métier interdit')
            ->set('slug', 'metier-interdit')
            ->set('code', 'METIER_INTERDIT')
            ->call('enregistrerMetier');

        $this->assertDatabaseMissing('trades', ['slug' => 'metier-interdit']);
    }

    public function test_il_ne_reordonne_rien(): void
    {
        $ordre = Sector::query()->orderBy('sort_order')->pluck('id')->all();

        $this->ecran()->call('reorderSectors', array_reverse($ordre));

        $this->assertSame($ordre, Sector::query()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_aucune_mutation_n_echappe_au_garde(): void
    {
        /*
         * L'ÉNUMÉRATION, et non la liste écrite à la main.
         *
         * Une mutation ajoutée demain sans garde doit faire échouer ce test, et non passer
         * inaperçue parce que personne n'aura pensé à lui écrire le sien. C'est exactement ainsi
         * que les sept trous d'origine sont apparus : au fil des ajouts, un par un.
         */
        $source = (string) file_get_contents(
            base_path('app/Livewire/Admin/OrderEngine/CatalogCenter.php'),
        );

        // Les méthodes qui n'écrivent rien : ouvrir un formulaire, l'annuler, préparer un aperçu.
        $lectureSeule = [
            'mount', 'render', 'startNewSector', 'editSector', 'cancelSector',
            'cancelArchive', 'confirmArchiveSector', 'ouvrirCreationMetier', 'fermerCreationMetier',
        ];

        preg_match_all('/public function (\w+)\([^)]*\)[^{]*\{(.*?)\n    \}/s', $source, $m, PREG_SET_ORDER);

        $sansGarde = [];

        foreach ($m as [, $nom, $corps]) {
            if (in_array($nom, $lectureSeule, true) || ! $this->ecritEnBase($corps)) {
                continue;
            }

            if (! str_contains($corps, 'refusesWrite()')) {
                $sansGarde[] = $nom;
            }
        }

        $this->assertSame([], $sansGarde);
    }

    private function ecritEnBase(string $corps): bool
    {
        /*
         * LA LISTE DOIT SUIVRE LES FAÇONS D'ÉCRIRE, PAS SEULEMENT CELLES D'ELOQUENT.
         *
         * `setTranslation()` écrit dans `catalog_translations` sans qu'aucun de ces verbes
         * n'apparaisse dans le corps de la méthode appelante : il les emploie à l'intérieur du
         * trait. `saveTranslation()` était donc SAUTÉE par cette énumération, et ce test passait au
         * vert sans jamais la regarder — le vert obtenu pour une mauvaise raison, que ce fichier
         * existe précisément pour empêcher.
         *
         * Toute nouvelle façon d'écrire ajoutée au dépôt doit venir s'inscrire ici.
         */
        foreach (['->update(', '->save()', '::create(', '->delete()', 'updateOrCreate', 'firstOrNew', '->archive(', '->setTranslation('] as $signe) {
            if (str_contains($corps, $signe)) {
                return true;
            }
        }

        return false;
    }
}
