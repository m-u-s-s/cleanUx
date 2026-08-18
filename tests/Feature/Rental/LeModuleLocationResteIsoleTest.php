<?php

namespace Tests\Feature\Rental;

use App\Models\FleetVehicle;
use App\Models\RentalVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * « NOS LOCATIONS » NE DOIT RIEN EMPRUNTER À FLEET NI AU MOTEUR DE COMMANDE.
 *
 * La demande est explicite sur ce point : inspiré de Fleet, mais sans le modifier ; ajouté au
 * catalogue, sans en toucher le reste ; fonctionnant différemment, et différencié dans le code.
 * Trois exigences qu'une relecture ne peut pas tenir dans la durée — d'où ce fichier.
 *
 * ── POURQUOI L'ISOLATION SE TESTE ────────────────────────────────────────────────────────────
 *
 * Le mélange ne se produit jamais d'un coup. Il arrive une ligne à la fois, chacune raisonnable :
 * « les deux ont un véhicule, autant partager la table », « le catalogue sait déjà lister, autant
 * réutiliser le composant ». Six mois plus tard, changer un prix de location casse une affectation
 * de chantier. Ce dépôt en a plusieurs exemples, et il les appelle « deux notions, un événement ».
 *
 * Ce test ne dit pas que le partage est interdit pour toujours. Il dit qu'il devient un geste
 * VISIBLE : on ne peut plus le faire sans venir ici l'écrire.
 */
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

    /**
     * LES DEUX PARCS SONT DEUX TABLES, ET ELLES LE RESTENT.
     *
     * Fleet est un registre d'employeur — ce qu'une société confie à ses exécutants pour aller
     * travailler, sans transaction. Ici le véhicule est un produit vendu. Une seule table aurait
     * porté deux cycles de vie qui ne se rencontrent jamais, et la moitié des colonnes de chaque
     * ligne serait vide selon le cas.
     */
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

    /**
     * AUCUN FICHIER DU MODULE N'IMPORTE FLEET NI LE MOTEUR DE COMMANDE.
     *
     * C'est la forme la plus simple de l'isolation, et la plus facile à briser : un `use` ajouté
     * « juste pour lire une valeur » suffit à créer le couplage qu'on veut éviter.
     */
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

    /**
     * TÉMOIN DU MOTIF — la recherche saurait voir un import interdit.
     *
     * Sans lui, le test précédent serait vert sur une comparaison qui ne mord jamais : il compterait
     * zéro coupable en ne sachant reconnaître aucun coupable.
     */
    public function test_temoin_le_motif_reconnait_un_import_interdit(): void
    {
        $factice = "<?php\nuse App\\Models\\FleetVehicle;\n";

        $this->assertTrue(str_contains($factice, 'use App\\Models\\FleetVehicle'));
        $this->assertFalse(str_contains("<?php\nuse App\\Models\\RentalVehicle;\n", 'use App\\Models\\FleetVehicle'));
    }

    /**
     * LE MOTEUR DE COMMANDE NE CONNAÎT PAS LA LOCATION NON PLUS — sauf par la case du catalogue.
     *
     * `OrderJourney.php` ne doit pas avoir bougé d'une ligne : la case est un composant autonome,
     * inséré dans la VUE. Si la classe se mettait à parler de location, les deux parcours
     * commenceraient à se tenir par la main.
     */
    public function test_le_moteur_de_commande_ignore_la_location(): void
    {
        $source = (string) file_get_contents(base_path('app/Livewire/OrderEngine/OrderJourney.php'));

        $this->assertNotSame('', $source);

        foreach (['Rental', 'Location'] as $mot) {
            $this->assertStringNotContainsString(
                'App\\Models\\'.$mot,
                $source,
                'OrderJourney a commencé à connaître le module de location : la case doit rester '
                .'un composant autonome inséré dans la vue.',
            );
        }
    }

    /**
     * LES DEUX CATALOGUES ONT DEUX ADRESSES, ET AUCUNE NE MASQUE L'AUTRE.
     *
     * `/commander` et `/location` sont deux racines distinctes. Le jour où l'une passerait sous
     * l'autre, un changement de préfixe casserait les deux d'un coup.
     */
    public function test_les_deux_catalogues_ont_des_adresses_distinctes(): void
    {
        $this->assertSame('location', route('location.catalogue', absolute: false) === '/location' ? 'location' : 'autre');

        $this->get('/location')->assertOk();

        // Et le parcours de commande répond toujours, inchangé.
        $this->get('/commander')->assertOk();
    }
}
