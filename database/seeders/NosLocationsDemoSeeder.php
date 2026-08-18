<?php

namespace Database\Seeders;

use App\Models\RentalPickupPoint;
use App\Models\RentalVehicle;
use App\Models\RentalVehicleMedia;
use Database\Seeders\Support\PngDeDemonstration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * UN PARC DE DÉMONSTRATION POUR « NOS LOCATIONS ».
 *
 * Il existe pour VOIR le module : la grille du catalogue, les filtres, la fiche, la rotation à 360°
 * et le récapitulatif avec ses deux prix. Un parc vide ne montre que l'écran d'absence.
 *
 * ── CE QU'IL NE FAIT PAS, ET POURQUOI ────────────────────────────────────────────────────────
 *
 * IL N'INVENTE PAS DE MODÈLE 3D. Produire un `.glb` factice afficherait un cube sur une fiche de
 * voiture, c'est-à-dire quelque chose qui ressemble à un défaut. Le visualiseur 3D se voit en
 * déposant un vrai fichier depuis l'écran d'administration ; la rotation photo, elle, est ici et
 * montre exactement l'expérience « vue 360 ».
 *
 * IL EST IDEMPOTENT. Relancé, il ne double pas le parc : chaque voiture est retrouvée par son code,
 * chaque agence par son nom, et les images ne sont réécrites que si elles manquent. Un semis de
 * démonstration qu'on n'ose pas relancer ne sert qu'une fois.
 *
 * ── LES VOITURES SONT OUVERTES ICI, ET C'EST L'EXCEPTION ─────────────────────────────────────
 *
 * L'écran d'administration crée FERMÉ, exprès : une faute de frappe sur un tarif ne doit pas rendre
 * un véhicule louable dans la seconde. Un semis n'a pas ce risque — il pose des valeurs choisies —
 * et une vitrine fermée ne montrerait rien.
 */
class NosLocationsDemoSeeder extends Seeder
{
    /** Le nombre d'images d'une séquence de rotation : un tour complet, tous les 15 degrés. */
    private const IMAGES_DE_ROTATION = 24;

    /**
     * Les clés du tableau ci-dessous qui NE SONT PAS des colonnes.
     *
     * Elles pilotent le semis — quelle agence, quelle teinte, faut-il une rotation — et doivent
     * être retirées avant d'atteindre Eloquent. Les y laisser lèverait : ce dépôt active le refus
     * explicite d'attribut inconnu hors production, précisément pour que ce genre d'oubli se voie
     * au lieu de disparaître en silence.
     *
     * @var list<string>
     */
    private const CLES_DE_PILOTAGE = ['agence', 'teinte', 'rotation'];

    public function run(): void
    {
        $agences = $this->agences();

        foreach ($this->parc() as $index => $definition) {
            $attributs = array_diff_key($definition, array_flip(self::CLES_DE_PILOTAGE));

            $vehicule = RentalVehicle::query()->updateOrCreate(
                ['code' => $definition['code']],
                $attributs + [
                    'pickup_point_id' => $agences[$definition['agence']],
                    'currency' => 'EUR',
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );

            $this->photos($vehicule, (float) $definition['teinte']);

            if ($definition['rotation']) {
                $this->rotation($vehicule, (float) $definition['teinte']);
            }
        }

        $this->command?->info(sprintf(
            'Parc de démonstration : %d véhicules dans %d agences, %d images.',
            RentalVehicle::query()->count(),
            RentalPickupPoint::query()->count(),
            RentalVehicleMedia::query()->count(),
        ));
    }

    /** @return array<string, int> */
    private function agences(): array
    {
        $definitions = [
            'bruxelles' => ['Agence Bruxelles-Centre', 'Rue du Marché aux Herbes 12', '1000', 'Bruxelles', 50.8466, 4.3528],
            'liege' => ['Agence Liège-Guillemins', 'Place des Guillemins 2', '4000', 'Liège', 50.6242, 5.5665],
            'anvers' => ['Agence Anvers-Sud', 'Amerikalei 88', '2000', 'Anvers', 51.2100, 4.3986],
        ];

        $ids = [];

        foreach ($definitions as $cle => [$nom, $adresse, $cp, $ville, $lat, $lng]) {
            $ids[$cle] = RentalPickupPoint::query()->updateOrCreate(
                ['name' => $nom],
                [
                    'address' => $adresse,
                    'postal_code' => $cp,
                    'city' => $ville,
                    'country_code' => 'BE',
                    'lat' => $lat,
                    'lng' => $lng,
                    'phone' => '+32 2 000 00 00',
                    'instructions' => 'Présentez-vous au comptoir avec votre permis et une carte au nom du conducteur.',
                    'is_active' => true,
                ],
            )->id;
        }

        return $ids;
    }

    /**
     * Le parc.
     *
     * Des catégories, des boîtes, des énergies et des prix différents : c'est ce qui donne aux
     * filtres du catalogue de quoi trier réellement. Un parc de huit citadines rouges montrerait
     * une grille et rien d'autre.
     *
     * @return list<array<string, mixed>>
     */
    private function parc(): array
    {
        return [
            [
                'code' => 'LOC-DEMO-CLIO', 'agence' => 'bruxelles', 'teinte' => 0.02, 'rotation' => true,
                'brand' => 'Renault', 'model' => 'Clio', 'year' => 2024, 'color' => 'Rouge',
                'category' => 'citadine', 'transmission' => 'manuelle', 'fuel' => 'essence',
                'seats' => 5, 'doors' => 5, 'luggage' => 2,
                'features' => ['climatisation', 'bluetooth', 'régulateur'],
                'daily_price_cents' => 3900, 'deposit_cents' => 60000,
                'waiver_daily_price_cents' => 900, 'waiver_deposit_cents' => 15000,
                'included_km_per_day' => 200, 'extra_km_price_cents' => 22,
                'min_rental_days' => 1, 'max_rental_days' => 30,
                'min_driver_age' => 21, 'min_license_years' => 1,
                'description' => 'La citadine idéale pour la ville : compacte, sobre, facile à garer.',
            ],
            [
                'code' => 'LOC-DEMO-208', 'agence' => 'bruxelles', 'teinte' => 0.58, 'rotation' => false,
                'brand' => 'Peugeot', 'model' => '208', 'year' => 2025, 'color' => 'Bleu',
                'category' => 'citadine', 'transmission' => 'automatique', 'fuel' => 'electrique',
                'seats' => 5, 'doors' => 5, 'luggage' => 2,
                'features' => ['climatisation', 'GPS', 'caméra de recul'],
                'daily_price_cents' => 4900, 'deposit_cents' => 70000,
                'waiver_daily_price_cents' => 1100, 'waiver_deposit_cents' => 18000,
                'included_km_per_day' => 180, 'extra_km_price_cents' => 25,
                'min_rental_days' => 1, 'max_rental_days' => 21,
                'min_driver_age' => 21, 'min_license_years' => 1,
                'description' => 'Électrique, silencieuse, sans carburant à avancer.',
            ],
            [
                'code' => 'LOC-DEMO-GOLF', 'agence' => 'liege', 'teinte' => 0.32, 'rotation' => true,
                'brand' => 'Volkswagen', 'model' => 'Golf', 'year' => 2024, 'color' => 'Gris',
                'category' => 'compacte', 'transmission' => 'automatique', 'fuel' => 'diesel',
                'seats' => 5, 'doors' => 5, 'luggage' => 3,
                'features' => ['climatisation bi-zone', 'GPS', 'régulateur adaptatif'],
                'daily_price_cents' => 5900, 'deposit_cents' => 90000,
                'waiver_daily_price_cents' => 1300, 'waiver_deposit_cents' => 20000,
                'included_km_per_day' => 250, 'extra_km_price_cents' => 20,
                'min_rental_days' => 2, 'max_rental_days' => 60,
                'min_driver_age' => 23, 'min_license_years' => 2,
                'description' => 'La routière polyvalente : confortable sur autoroute, sobre en diesel.',
            ],
            [
                'code' => 'LOC-DEMO-TUCSON', 'agence' => 'liege', 'teinte' => 0.12, 'rotation' => false,
                'brand' => 'Hyundai', 'model' => 'Tucson', 'year' => 2025, 'color' => 'Sable',
                'category' => 'suv', 'transmission' => 'automatique', 'fuel' => 'hybride',
                'seats' => 5, 'doors' => 5, 'luggage' => 4,
                'features' => ['climatisation', 'GPS', 'toit ouvrant', 'attelage'],
                'daily_price_cents' => 7900, 'deposit_cents' => 120000,
                'waiver_daily_price_cents' => 1800, 'waiver_deposit_cents' => 25000,
                'included_km_per_day' => 250, 'extra_km_price_cents' => 28,
                'min_rental_days' => 2, 'max_rental_days' => 45,
                'min_driver_age' => 25, 'min_license_years' => 3,
                'description' => 'SUV hybride, cinq places et grand coffre — pour les départs en famille.',
            ],
            [
                'code' => 'LOC-DEMO-TRAFIC', 'agence' => 'anvers', 'teinte' => 0.15, 'rotation' => false,
                'brand' => 'Renault', 'model' => 'Trafic', 'year' => 2023, 'color' => 'Blanc',
                'category' => 'utilitaire', 'transmission' => 'manuelle', 'fuel' => 'diesel',
                'seats' => 3, 'doors' => 4, 'luggage' => 8,
                'features' => ['hayon', 'cloison de séparation', 'radar de recul'],
                'daily_price_cents' => 6900, 'deposit_cents' => 100000,
                'waiver_daily_price_cents' => 1500, 'waiver_deposit_cents' => 25000,
                'included_km_per_day' => 150, 'extra_km_price_cents' => 35,
                'min_rental_days' => 1, 'max_rental_days' => 14,
                'min_driver_age' => 25, 'min_license_years' => 3,
                'description' => 'Fourgon 6 m³ pour un déménagement ou une livraison.',
            ],
            [
                'code' => 'LOC-DEMO-SERIE3', 'agence' => 'anvers', 'teinte' => 0.72, 'rotation' => true,
                'brand' => 'BMW', 'model' => 'Série 3', 'year' => 2025, 'color' => 'Noir',
                'category' => 'premium', 'transmission' => 'automatique', 'fuel' => 'hybride',
                'seats' => 5, 'doors' => 5, 'luggage' => 3,
                'features' => ['sièges cuir', 'GPS', 'audio premium', 'aide au stationnement'],
                'daily_price_cents' => 12900, 'deposit_cents' => 200000,
                'waiver_daily_price_cents' => 2900, 'waiver_deposit_cents' => 40000,
                'included_km_per_day' => 200, 'extra_km_price_cents' => 45,
                'min_rental_days' => 2, 'max_rental_days' => 30,
                'min_driver_age' => 28, 'min_license_years' => 5,
                'description' => 'Berline premium — pour un déplacement professionnel ou une occasion.',
            ],
            [
                /*
                 * AUCUNE GARANTIE PROPOSÉE sur ce véhicule, et c'est délibéré.
                 *
                 * C'est le cas qui montre que la fiche n'affiche pas un choix entre deux options
                 * rigoureusement identiques — ce qui ne serait pas un choix mais une confusion.
                 * Sans lui dans le parc, ce comportement ne se verrait jamais à l'écran.
                 */
                'code' => 'LOC-DEMO-TOURAN', 'agence' => 'bruxelles', 'teinte' => 0.45, 'rotation' => false,
                'brand' => 'Volkswagen', 'model' => 'Touran', 'year' => 2023, 'color' => 'Argent',
                'category' => 'monospace', 'transmission' => 'manuelle', 'fuel' => 'diesel',
                'seats' => 7, 'doors' => 5, 'luggage' => 5,
                'features' => ['climatisation', '7 places', 'barres de toit'],
                'daily_price_cents' => 6500, 'deposit_cents' => 90000,
                'waiver_daily_price_cents' => 0, 'waiver_deposit_cents' => 90000,
                'included_km_per_day' => 250, 'extra_km_price_cents' => 24,
                'min_rental_days' => 2, 'max_rental_days' => 30,
                'min_driver_age' => 23, 'min_license_years' => 2,
                'description' => 'Sept places pour les trajets à plusieurs.',
            ],
            [
                'code' => 'LOC-DEMO-YARIS', 'agence' => 'liege', 'teinte' => 0.88, 'rotation' => false,
                'brand' => 'Toyota', 'model' => 'Yaris', 'year' => 2024, 'color' => 'Violet',
                'category' => 'citadine', 'transmission' => 'automatique', 'fuel' => 'hybride',
                'seats' => 5, 'doors' => 5, 'luggage' => 2,
                'features' => ['climatisation', 'caméra de recul', 'régulateur'],
                'daily_price_cents' => 4500, 'deposit_cents' => 65000,
                'waiver_daily_price_cents' => 1000, 'waiver_deposit_cents' => 15000,
                'included_km_per_day' => 220, 'extra_km_price_cents' => 22,
                'min_rental_days' => 1, 'max_rental_days' => 30,
                'min_driver_age' => 21, 'min_license_years' => 1,
                'description' => 'Hybride urbaine, très sobre en ville.',
            ],
        ];
    }

    private function photos(RentalVehicle $vehicule, float $teinte): void
    {
        if ($vehicule->media()->where('type', RentalVehicleMedia::TYPE_GALERIE)->exists()) {
            return;
        }

        // Trois angles : la vignette du catalogue, plus deux vues pour la bande de la fiche.
        foreach ([0.0, 0.9, 1.9] as $position => $angle) {
            $chemin = 'rental/'.$vehicule->code.'/galerie-'.$position.'.png';

            Storage::disk('public')->put($chemin, PngDeDemonstration::photo(800, 600, $teinte, $angle));

            $vehicule->media()->create([
                'type' => RentalVehicleMedia::TYPE_GALERIE,
                'path' => $chemin,
                'position' => $position,
                'alt' => $vehicule->nomComplet(),
            ]);
        }
    }

    /**
     * La séquence de rotation, sur un tour complet.
     *
     * L'ORDRE EST LE SENS DE ROTATION : `position` range les images, et la largeur de la caisse
     * suit le cosinus de l'angle. C'est ce qui fait qu'on lit un objet qui tourne, et non un
     * diaporama.
     */
    private function rotation(RentalVehicle $vehicule, float $teinte): void
    {
        if ($vehicule->media()->where('type', RentalVehicleMedia::TYPE_ROTATION)->exists()) {
            return;
        }

        for ($i = 0; $i < self::IMAGES_DE_ROTATION; $i++) {
            $angle = 2 * M_PI * $i / self::IMAGES_DE_ROTATION;
            $chemin = 'rental/'.$vehicule->code.'/spin/'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'.png';

            Storage::disk('public')->put($chemin, PngDeDemonstration::photo(640, 480, $teinte, $angle));

            $vehicule->media()->create([
                'type' => RentalVehicleMedia::TYPE_ROTATION,
                'path' => $chemin,
                'position' => $i,
            ]);
        }
    }
}
