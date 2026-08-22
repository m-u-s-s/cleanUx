<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * LES NOMS DU CATALOGUE DANS LES CINQ LANGUES ACTIVES.
 *
 * ── POURQUOI CE SEEDER EXISTE ────────────────────────────────────────────────────────────────
 *
 * Rendre `Sector` et `Trade` traduisibles ne traduit rien : le mécanisme attend qu'on le
 * remplisse. Un catalogue techniquement multilingue et vide reste monolingue à l'écran, et c'est
 * exactement la forme que prend ici la famille de défauts la plus tenace — une capacité complète
 * que rien n'alimente.
 *
 * ── CE QU'IL TRADUIT, ET CE QU'IL LAISSE ─────────────────────────────────────────────────────
 *
 * Les NOMS seulement. Un nom de métier est un terme, il a une traduction juste ; une accroche
 * (« Du petit dépannage au chantier complet ») et une description sont de la PLUME — elles portent
 * un ton, un marché, une promesse commerciale. Les inventer à la place de l'exploitant produirait
 * un texte que personne n'a choisi et que personne ne relirait. L'écran d'administration les
 * signalera comme manquantes, ce qui est la bonne façon de les réclamer.
 *
 * Les entrées de TEST du catalogue — le secteur « MISSION AUTO » et le métier « TAXI » — sont
 * délibérément absentes : traduire une donnée de démonstration lui donnerait l'air d'un vrai
 * service.
 *
 * ── IDEMPOTENT, ET NON DESTRUCTIF ────────────────────────────────────────────────────────────
 *
 * Les clés sont les SLUGS, jamais les libellés : un nom français retouché ne doit pas faire perdre
 * ses cinq traductions à un métier. Et une traduction DÉJÀ SAISIE n'est jamais écrasée — ce
 * seeder propose un point de départ, il ne reprend pas la main sur le travail de l'exploitant.
 *
 * Usage : php artisan db:seed --class=CatalogueTraductionsSeeder
 */
class CatalogueTraductionsSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private array $secteurs = [
        'batiment-renovation' => [
            'nl' => 'Bouw & renovatie',
            'en' => 'Building & renovation',
            'de' => 'Bau & Renovierung',
            'es' => 'Construcción y reformas',
            'it' => 'Edilizia e ristrutturazioni',
        ],
        'nettoyage' => [
            'nl' => 'Schoonmaak',
            'en' => 'Cleaning',
            'de' => 'Reinigung',
            'es' => 'Limpieza',
            'it' => 'Pulizie',
        ],
        'espaces-verts' => [
            'nl' => 'Groenvoorziening',
            'en' => 'Gardens & green spaces',
            'de' => 'Garten & Grünflächen',
            'es' => 'Jardinería y zonas verdes',
            'it' => 'Giardini e aree verdi',
        ],
    ];

    /** @var array<string, array<string, string>> */
    private array $metiers = [
        'peinture' => [
            'nl' => 'Schilderwerk',
            'en' => 'Painting',
            'de' => 'Malerarbeiten',
            'es' => 'Pintura',
            'it' => 'Verniciatura',
        ],
        'nettoyage-fin-chantier' => [
            'nl' => 'Opleveringsschoonmaak',
            'en' => 'Post-construction cleaning',
            'de' => 'Bauendreinigung',
            'es' => 'Limpieza de fin de obra',
            'it' => 'Pulizie post-cantiere',
        ],
        'jardinage' => [
            'nl' => 'Tuinonderhoud',
            'en' => 'Gardening',
            'de' => 'Gartenpflege',
            'es' => 'Jardinería',
            'it' => 'Giardinaggio',
        ],
        'plumbing' => [
            'nl' => 'Loodgieterswerk',
            'en' => 'Plumbing',
            'de' => 'Sanitärinstallation',
            'es' => 'Fontanería',
            'it' => 'Idraulica',
        ],
        'nettoyage' => [
            'nl' => 'Huishoudelijke schoonmaak',
            'en' => 'Home cleaning',
            'de' => 'Haushaltsreinigung',
            'es' => 'Limpieza del hogar',
            'it' => 'Pulizie domestiche',
        ],
        'elagage' => [
            'nl' => 'Boomverzorging',
            'en' => 'Tree surgery',
            'de' => 'Baumpflege',
            'es' => 'Poda y tala',
            'it' => 'Potatura alberi',
        ],
        'electrical' => [
            'nl' => 'Elektriciteit',
            'en' => 'Electrical work',
            'de' => 'Elektroinstallation',
            'es' => 'Electricidad',
            'it' => 'Impianti elettrici',
        ],
        'vitrerie' => [
            'nl' => 'Glazenwassen',
            'en' => 'Window cleaning',
            'de' => 'Fensterreinigung',
            'es' => 'Limpieza de cristales',
            'it' => 'Pulizia vetri',
        ],
        'roofing' => [
            'nl' => 'Dakwerken',
            'en' => 'Roofing',
            'de' => 'Dacharbeiten',
            'es' => 'Tejados',
            'it' => 'Coperture e tetti',
        ],
    ];

    public function run(): void
    {
        $actives = $this->languesActives();

        $poses = 0;
        $poses += $this->traduire(Sector::query()->get(), $this->secteurs, $actives);
        $poses += $this->traduire(Trade::query()->get(), $this->metiers, $actives);

        $this->command?->info("Traductions de catalogue posées : {$poses}");
    }

    /**
     * On n'écrit que dans les langues ACTIVES.
     *
     * Une langue éteinte dans `config/i18n.php` ne s'affiche nulle part : y écrire produirait un
     * texte invisible, et fausserait le décompte des langues manquantes de l'écran
     * d'administration.
     *
     * @return list<string>
     */
    private function languesActives(): array
    {
        $defaut = (string) Config::get('i18n.default', 'fr');

        return collect((array) Config::get('i18n.locales', []))
            ->filter(fn ($meta) => (bool) ($meta['enabled'] ?? false))
            ->keys()
            ->reject(fn ($code) => $code === $defaut)
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Sector|Trade>  $objets
     * @param  array<string, array<string, string>>  $table
     * @param  list<string>  $actives
     */
    private function traduire($objets, array $table, array $actives): int
    {
        $poses = 0;

        foreach ($objets as $objet) {
            $traductions = $table[$objet->slug] ?? null;

            if ($traductions === null) {
                continue;
            }

            foreach ($traductions as $langue => $valeur) {
                if (! in_array($langue, $actives, true)) {
                    continue;
                }

                // Ne JAMAIS écraser une saisie existante : ce seeder propose, il ne décide pas.
                if (filled($objet->translations()->where('field', 'name')->where('locale', $langue)->value('value'))) {
                    continue;
                }

                $objet->setTranslation('name', $langue, $valeur);
                $poses++;
            }
        }

        return $poses;
    }
}
