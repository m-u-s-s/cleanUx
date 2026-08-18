<?php

namespace Tests\Feature\Rental;

use App\Models\RentalPickupPoint;
use App\Models\RentalVehicle;
use App\Models\RentalVehicleMedia;
use App\Services\Rental\RentalAvailability;
use Database\Seeders\NosLocationsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * LE SEMIS DE DÉMONSTRATION DOIT PRODUIRE UN PARC QU'ON PEUT RÉELLEMENT REGARDER.
 *
 * Un semis qui pose des lignes sans images montre l'emoji de repli partout, et ne dit donc rien de
 * l'écran qu'il est censé faire voir. Ce fichier vérifie qu'il produit des IMAGES VALIDES, un parc
 * varié, et qu'on peut le relancer.
 */
class SemisDeDemonstrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_le_semis_remplit_le_parc_et_les_agences(): void
    {
        $this->seed(NosLocationsDemoSeeder::class);

        $this->assertSame(3, RentalPickupPoint::query()->count());
        $this->assertSame(8, RentalVehicle::query()->count());
        $this->assertSame(8, RentalVehicle::query()->actif()->count(), 'Un parc fermé ne montrerait rien.');
    }

    /**
     * LE PARC EST VARIÉ, sinon les filtres n'ont rien à trier.
     *
     * Huit citadines rouges montreraient une grille et rien d'autre : ni catégorie, ni boîte, ni
     * énergie ne se distingueraient.
     */
    public function test_le_parc_donne_de_quoi_filtrer(): void
    {
        $this->seed(NosLocationsDemoSeeder::class);

        $options = app(RentalAvailability::class)->optionsDeFiltre();

        $this->assertGreaterThanOrEqual(4, count($options['categories']));
        $this->assertCount(2, $options['transmissions']);
        $this->assertGreaterThanOrEqual(3, count($options['fuels']));
    }

    /**
     * LES IMAGES SONT DE VRAIS PNG, décodables par le navigateur.
     *
     * Un fichier mal formé — CRC faux, en-tête tronqué — est refusé EN SILENCE : la balise reste
     * vide et rien dans le journal ne le dit. On décode donc réellement.
     */
    public function test_les_images_produites_sont_des_png_valides(): void
    {
        $this->seed(NosLocationsDemoSeeder::class);

        $media = RentalVehicleMedia::query()->firstOrFail();

        $this->assertTrue(Storage::disk('public')->exists($media->path));

        $octets = (string) Storage::disk('public')->get($media->path);
        $info = getimagesizefromstring($octets);

        $this->assertNotFalse($info, 'Le fichier produit n’est pas une image décodable.');
        $this->assertSame('image/png', $info['mime']);
        $this->assertGreaterThan(100, $info[0]);
    }

    /** Au moins un véhicule porte une rotation complète, et elle est ordonnée. */
    public function test_une_rotation_complete_est_produite(): void
    {
        $this->seed(NosLocationsDemoSeeder::class);

        $vehicule = RentalVehicle::query()
            ->whereHas('media', fn ($q) => $q->where('type', RentalVehicleMedia::TYPE_ROTATION))
            ->firstOrFail();

        $rotation = $vehicule->rotation360;

        $this->assertCount(24, $rotation);
        $this->assertSame(range(0, 23), $rotation->pluck('position')->all(),
            'L’ordre EST le sens de rotation : une position manquante fait sauter la voiture.');
    }

    /**
     * UN VÉHICULE SANS GARANTIE FIGURE AU PARC.
     *
     * C'est le cas qui montre que la fiche n'affiche pas un choix entre deux options identiques.
     * Sans lui, ce comportement ne se verrait jamais à l'écran.
     */
    public function test_le_parc_contient_un_vehicule_sans_garantie(): void
    {
        $this->seed(NosLocationsDemoSeeder::class);

        $sansGarantie = RentalVehicle::query()->get()->filter(fn (RentalVehicle $v) => ! $v->proposeUneGarantie());

        $this->assertGreaterThanOrEqual(1, $sansGarantie->count());
    }

    /**
     * RELANCÉ, LE SEMIS NE DOUBLE RIEN.
     *
     * Un semis de démonstration qu'on n'ose pas relancer ne sert qu'une fois : on le relance après
     * avoir cassé ses données en jouant avec l'écran, et c'est précisément à ce moment-là qu'il
     * doit tenir.
     */
    public function test_le_semis_est_idempotent(): void
    {
        $this->seed(NosLocationsDemoSeeder::class);

        $vehicules = RentalVehicle::query()->count();
        $medias = RentalVehicleMedia::query()->count();

        $this->seed(NosLocationsDemoSeeder::class);

        $this->assertSame($vehicules, RentalVehicle::query()->count());
        $this->assertSame($medias, RentalVehicleMedia::query()->count());
    }
}
