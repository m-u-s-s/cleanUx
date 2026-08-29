<?php

namespace Tests\Feature\PeerRental;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DEUX MODULES DE LOCATION, ET AUCUN PONT ENTRE EUX.
 *
 * « Nos locations » loue la flotte DE LA PLATEFORME (`Rental*`, tables `rental_*`, routes
 * `location.*`). « Location entre membres » met en relation deux comptes (`Peer*`, tables
 * `peer_*`, routes `peer.*`). Les confondre se paierait en donnees : un vehicule de la
 * plateforme reserve par le moteur des membres n'aurait ni proprietaire a payer ni caution.
 */
class LesDeuxLocationsRestentEtrangeresTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const SYMBOLES_NOS_LOCATIONS = [
        'RentalVehicle', 'RentalBooking', 'RentalPickupPoint', 'RentalVehicleMedia',
        'RentalAvailability', 'RentalBookingService', 'RentalPricing', 'LocationCatalogue',
    ];

    /** @var list<string> */
    private const SYMBOLES_ENTRE_MEMBRES = [
        'PeerVehicle', 'PeerRental', 'PeerCode', 'PeerInspection', 'PeerClaim', 'PeerReview',
    ];

    /** @return list<string> */
    private function fichiersDe(string $motif): array
    {
        $trouves = [];

        foreach ([app_path(), resource_path('views'), base_path('routes'), base_path('database')] as $racine) {
            if (! is_dir($racine)) {
                continue;
            }

            $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

            /** @var \SplFileInfo $fichier */
            foreach ($iterateur as $fichier) {
                if (! $fichier->isFile()) {
                    continue;
                }

                $chemin = str_replace('\\', '/', $fichier->getPathname());

                if (! preg_match('/\.(php|blade\.php)$/', $chemin)) {
                    continue;
                }

                if (preg_match($motif, $chemin) === 1) {
                    $trouves[] = $chemin;
                }
            }
        }

        sort($trouves);

        return $trouves;
    }

    public function test_le_module_entre_membres_ne_cite_jamais_nos_locations(): void
    {
        $fuites = [];

        foreach ($this->fichiersDe('#/(Peer[A-Z][A-Za-z]*\.php|peer-rental|peer_)#') as $chemin) {
            $code = (string) file_get_contents($chemin);

            foreach (self::SYMBOLES_NOS_LOCATIONS as $symbole) {
                // `{@see RentalVehicle}` dans un commentaire dit justement la difference : on
                // ne traque que les emplois REELS, pas les renvois qui l'expliquent.
                $sansCommentaires = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $code);

                if (str_contains($sansCommentaires, $symbole)) {
                    $fuites[] = basename($chemin).' cite '.$symbole;
                }
            }

            if (preg_match("/'location\.[a-z]/", $code) === 1) {
                $fuites[] = basename($chemin).' cite une route location.*';
            }
        }

        $this->assertSame([], $fuites, "Le module entre membres emprunte a « Nos locations » :\n  ".implode("\n  ", $fuites));
    }

    public function test_nos_locations_ne_cite_jamais_le_module_entre_membres(): void
    {
        $fuites = [];

        foreach ($this->fichiersDe('#/(Rental[A-Z][A-Za-z]*\.php|LocationCatalogue|LocationVehicle)#') as $chemin) {
            $code = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($chemin));

            foreach (self::SYMBOLES_ENTRE_MEMBRES as $symbole) {
                if (str_contains($code, $symbole)) {
                    $fuites[] = basename($chemin).' cite '.$symbole;
                }
            }
        }

        $this->assertSame([], $fuites, "« Nos locations » emprunte au module entre membres :\n  ".implode("\n  ", $fuites));
    }

    /** TEMOIN — le balayage lit bien des fichiers ; sans lui, zero fuite ne prouverait rien. */
    public function test_temoin_le_balayage_trouve_les_deux_modules(): void
    {
        $this->assertNotEmpty(
            $this->fichiersDe('#/Peer[A-Z][A-Za-z]*\.php#'),
            'Aucun fichier du module entre membres : la mesure ne porte sur rien.'
        );

        $this->assertNotEmpty(
            $this->fichiersDe('#/Rental[A-Z][A-Za-z]*\.php#'),
            'Aucun fichier de « Nos locations » : la mesure ne porte sur rien.'
        );
    }

    /** Les deux jeux de tables coexistent sans se recouvrir. */
    public function test_les_deux_jeux_de_tables_sont_distincts(): void
    {
        foreach (['peer_vehicles', 'peer_rentals', 'peer_inspections'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} absente");
        }

        foreach (['rental_vehicles', 'rental_bookings'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} absente");
        }
    }

    /** Aucune route ne sert les deux mondes. */
    public function test_aucune_route_ne_melange_les_deux_prefixes(): void
    {
        $melangees = [];

        foreach (Route::getRoutes() as $route) {
            $nom = (string) $route->getName();

            if ($nom === '') {
                continue;
            }

            $uri = $route->uri();

            if (str_starts_with($nom, 'peer.') && str_starts_with($uri, 'location/')) {
                $melangees[] = $nom.' → /'.$uri;
            }

            if (str_starts_with($nom, 'location.') && str_contains($uri, 'peer')) {
                $melangees[] = $nom.' → /'.$uri;
            }
        }

        $this->assertSame([], $melangees, 'Ces routes melangent les deux modules : '.implode(', ', $melangees));
    }
}
