<?php

namespace Tests\Feature\Rental;

use App\Models\FleetVehicle;
use App\Models\RentalVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** « NOS LOCATIONS » NE DOIT RIEN EMPRUNTER À FLEET NI AU MOTEUR DE COMMANDE. */
class LeModuleLocationResteIsoleTest extends TestCase
{
    use RefreshDatabase;

    /** Les fichiers du module de location, tels qu'ils existent. */
    private const FICHIERS_DU_MODULE = [
        'app/Models/RentalVehicle.php',
        'app/Models/RentalBooking.php',
        'app/Models/RentalPickupPoint.php',
        'app/Models/RentalVehicleMedia.php',
        'app/Services/Rental/RentalAvailability.php',
        'app/Services/Rental/RentalPricing.php',
        'app/Services/Rental/RentalBookingService.php',
        'app/Livewire/Rental/LocationCatalogue.php',
        'app/Livewire/Rental/LocationVehicle.php',
        'app/Livewire/Rental/LocationConfirmation.php',
        'app/Livewire/Rental/LocationEntryTile.php',
        'app/Livewire/Admin/Rental/NosLocationsCenter.php',
    ];

    /** LES DEUX PARCS SONT DEUX TABLES, ET ELLES LE RESTENT. */
    public function test_les_deux_parcs_sont_deux_tables_distinctes(): void
    {
        $this->assertTrue(Schema::hasTable('fleet_vehicles'));
        $this->assertTrue(Schema::hasTable('rental_vehicles'));

        // Une colonne que seule la location porte : le prix. Fleet ne vend rien.
        $this->assertTrue(Schema::hasColumn('rental_vehicles', 'daily_price_cents'));
        $this->assertFalse(
            Schema::hasColumn('fleet_vehicles', 'daily_price_cents'),
            'Fleet a reçu une colonne de prix : les deux notions commencent à se mélanger.',
        );
    }

    /** Créer une voiture de location ne crée aucun véhicule de flotte, et l'inverse. */
    public function test_creer_dans_lun_ne_touche_pas_a_lautre(): void
    {
        RentalVehicle::factory()->create();

        $this->assertSame(1, RentalVehicle::query()->count());
        $this->assertSame(0, FleetVehicle::query()->count());

        FleetVehicle::factory()->create();

        $this->assertSame(1, RentalVehicle::query()->count());
        $this->assertSame(1, FleetVehicle::query()->count());
    }

    /** AUCUN FICHIER DU MODULE N'IMPORTE FLEET NI LE MOTEUR DE COMMANDE. */
    public function test_le_module_nimporte_ni_fleet_ni_le_moteur_de_commande(): void
    {
        $interdits = [
            'App\\Models\\FleetVehicle',
            'App\\Models\\FleetAssignment',
            'App\\Models\\FleetEquipment',
            'App\\Livewire\\OrderEngine\\',
            'App\\Services\\OrderEngine\\',
            'App\\Models\\OrderDraft',
        ];

        $coupables = [];

        foreach (self::FICHIERS_DU_MODULE as $chemin) {
            $source = (string) file_get_contents(base_path($chemin));

            $this->assertNotSame('', $source, "{$chemin} est introuvable : le test ne mesure plus rien.");

            foreach ($interdits as $classe) {
                if (str_contains($source, 'use '.$classe)) {
                    $coupables[] = "{$chemin} importe {$classe}";
                }
            }
        }

        $this->assertSame(
            [],
            $coupables,
            "Le module de location s’est mis à dépendre de Fleet ou du moteur de commande.\n"
            .implode("\n", $coupables),
        );
    }

    /** TÉMOIN DU MOTIF — la recherche saurait voir un import interdit. */
    public function test_temoin_le_motif_reconnait_un_import_interdit(): void
    {
        $factice = "<?php\nuse App\\Models\\FleetVehicle;\n";

        $this->assertTrue(str_contains($factice, 'use App\\Models\\FleetVehicle'));
        $this->assertFalse(str_contains("<?php\nuse App\\Models\\RentalVehicle;\n", 'use App\\Models\\FleetVehicle'));
    }

    /** LE MOTEUR DE COMMANDE NE CONNAÎT PAS LA LOCATION NON PLUS — sauf par la case du catalogue. */
    public function test_le_moteur_de_commande_ignore_la_location(): void
    {
        $source = (string) file_get_contents(base_path('app/Livewire/OrderEngine/OrderJourney.php'));

        $this->assertNotSame('', $source);

        // Les deux mots relevés ensemble : une fuite en amène souvent une seconde, et savoir que
        // `Rental` est cité ne dit rien de `Location`.
        $fuites = array_values(array_filter(
            ['Rental', 'Location'],
            fn (string $mot) => str_contains($source, 'App\\Models\\'.$mot),
        ));

        $this->assertSame(
            [],
            $fuites,
            'OrderJourney a commencé à connaître le module de location : la case doit rester '
            .'un composant autonome inséré dans la vue.',
        );
    }

    /** LES DEUX CATALOGUES ONT DEUX ADRESSES, ET AUCUNE NE MASQUE L'AUTRE. */
    public function test_les_deux_catalogues_ont_des_adresses_distinctes(): void
    {
        $this->assertSame('location', route('location.catalogue', absolute: false) === '/location' ? 'location' : 'autre');

        $this->get('/location')->assertOk();

        // Et le parcours de commande répond toujours, inchangé.
        $this->get('/commander')->assertOk();
    }
}
