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

/** Un administrateur en LECTURE SEULE ne modifie rien du catalogue. CE QUE CE FICHIER CORRIGE. */
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

    /** La traduction est une ÉCRITURE du catalogue, au même titre que le reste. */
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
        // L'ÉNUMÉRATION, et non la liste écrite à la main.
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
        // LA LISTE DOIT SUIVRE LES FAÇONS D'ÉCRIRE, PAS SEULEMENT CELLES D'ELOQUENT.
        foreach (['->update(', '->save()', '::create(', '->delete()', 'updateOrCreate', 'firstOrNew', '->archive(', '->setTranslation('] as $signe) {
            if (str_contains($corps, $signe)) {
                return true;
            }
        }

        return false;
    }
}
