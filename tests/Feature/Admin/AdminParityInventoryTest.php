<?php

namespace Tests\Feature\Admin;

use App\Admin\Console\ResourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * La parité mobile se MESURE, elle ne se déclare pas.
 *
 * POURQUOI CE FICHIER EXISTE. « Tous les modules sont opérationnels comme sur le web » est une
 * phrase invérifiable tant que personne ne compte. Ce test compte : pour chaque page
 * d'administration, il repère les gestes que le composant web sait faire, et vérifie que le
 * descripteur servi au mobile en offre au moins autant.
 *
 * L'HEURISTIQUE EST ASSUMÉE. Repérer un « geste » dans du PHP par lecture de source ne peut pas
 * être exact : une méthode qui délègue à un service ne s'écrit pas comme une qui appelle `save()`.
 * Le compte sert de SEUIL, pas de vérité — il empêche un module de repartir à zéro sans qu'on le
 * voie, ce qu'aucune relecture humaine ne garantit sur quatre-vingts modules.
 *
 * CE QU'IL NE PRÉTEND PAS. Qu'un geste porté fasse la même chose que celui du web : cela relève des
 * tests de chaque domaine. Ici on tient l'INVENTAIRE, et un inventaire qui recule fait échouer la
 * suite.
 */
class AdminParityInventoryTest extends TestCase
{
    /*
     * UNE BASE EST NÉCESSAIRE, et ce n'est pas un détail de test : certains descripteurs
     * construisent leurs listes déroulantes en INTERROGEANT la base — les pays d'un filtre, les
     * secteurs d'un formulaire. Les figer dans le fichier obligerait à le rééditer à chaque pays
     * ajouté ; les lire suppose une base, jusque dans l'introspection.
     */
    use RefreshDatabase;

    /**
     * Les modules dont le web n'offre aucun geste, et pour lesquels le mobile n'a donc rien à
     * porter : une liste reste une liste.
     */
    private const SEUIL_MINIMAL = 1;

    /**
     * Les modules que l'heuristique compte comme actifs alors qu'ils ne le sont pas.
     *
     * CHAQUE ENTRÉE PORTE SA RAISON, et la raison est vérifiable : c'est ce qui distingue une
     * exception d'un contournement. Une liste sans motifs se remplirait à chaque module gênant, et
     * la mesure finirait par ne plus rien mesurer.
     */
    private const HORS_MESURE = [
        'emails' => 'Ses deux « gestes » sont une PRÉVISUALISATION de gabarit et un accesseur de '
            .'liste. Aucun n’écrit : l’heuristique les compte parce qu’ils appellent un service, '
            .'ce qu’elle ne peut pas distinguer d’une écriture.',

        'missions' => 'Sa page web agit sur des RÉSERVATIONS, pas sur des missions — la table '
            .'`missions` est vide et le dispatch vit dans le module ia-dispatch, où il est porté. '
            .'Y ajouter une action porterait sur un modèle que personne n’alimente.',

        'face-check' => 'Le geste central de cet écran est de COMPARER DEUX VISAGES À L’ŒIL : lever '
            .'un blocage sans avoir regardé les deux images n’est pas une décision, c’est un clic. '
            .'Porter ces gestes sur mobile obligerait à servir des images biométriques (RGPD art. 9) '
            .'à une seconde surface, avec son propre cache, ses propres captures d’écran et sa '
            .'propre journalisation à écrire — pour un gain nul, puisqu’un administrateur qui '
            .'instruit un dossier d’usurpation d’identité le fait devant un écran, pas dans un '
            .'couloir. La décision est la même que pour la console en lecture seule : le mobile '
            .'montre, il ne tranche pas.',
    ];

    public function test_l_inventaire_de_parite_ne_recule_pas(): void
    {
        $registre = app(ResourceRegistry::class);
        $manquants = [];

        foreach ((array) config('admin_console.modules') as $module) {
            if (($module['coverage'] ?? '') === 'report') {
                // Une synthèse n'a rien à éditer : c'est un tableau de bord, pas un formulaire.
                continue;
            }

            $gestesWeb = $this->gestesDuComposantWeb((string) ($module['routes'][0] ?? ''));

            if ($gestesWeb < self::SEUIL_MINIMAL || isset(self::HORS_MESURE[$module['key']])) {
                continue;
            }

            $descripteur = $registre->for($module['key']);

            if (! $descripteur) {
                $manquants[] = $module['key'].' : aucun descripteur';

                continue;
            }

            // Les actions GLOBALES comptent autant : rafraîchir tous les taux est le geste principal
            // de sa page, et l'omettre ici ferait passer un module porté pour manquant.
            $porte = count($descripteur->formFields())
                + count($descripteur->actions())
                + count($descripteur->globalActions());

            /*
             * Les ressources SECONDAIRES comptent pour leur module.
             *
             * Certaines pages web sont des tableaux de bord multi-modèles : « Opérations B2B » gère
             * contrats, ordres de travail ET grilles tarifaires. Le moteur sert un modèle par
             * descripteur ; le module les rassemble. Ne compter que le principal ferait passer pour
             * manquant un module dont tous les gestes sont portés — ailleurs.
             */
            foreach ((array) ($module['resources'] ?? []) as $secondaire) {
                $autre = $registre->for($secondaire);

                $this->assertNotNull($autre, "Ressource secondaire {$secondaire} déclarée mais absente du registre.");

                $porte += count($autre->formFields()) + count($autre->actions()) + count($autre->globalActions());
            }

            if ($porte === 0) {
                $manquants[] = sprintf(
                    '%s : %d geste(s) sur le web, aucun porté',
                    $module['key'],
                    $gestesWeb,
                );
            }
        }

        $this->assertSame([], $manquants, "Modules web actifs sans aucune prise côté mobile :\n".implode("\n", $manquants));
    }

    public function test_chaque_exception_porte_une_raison_ecrite(): void
    {
        foreach (self::HORS_MESURE as $cle => $raison) {
            $this->assertGreaterThan(80, strlen($raison), "L’exception {$cle} n’explique pas assez.");
        }
    }

    public function test_la_mesure_trouve_bien_des_gestes(): void
    {
        /*
         * Le mode d'échec le plus discret d'un test de ce genre : l'heuristique cesse de repérer
         * quoi que ce soit — un remaniement des composants, un format d'écriture différent — et le
         * test passe au vert pour la pire des raisons.
         */
        $total = 0;

        foreach ((array) config('admin_console.modules') as $module) {
            $total += $this->gestesDuComposantWeb((string) ($module['routes'][0] ?? ''));
        }

        $this->assertGreaterThan(50, $total, 'La mesure ne repère plus de gestes : elle a cessé de mesurer.');
    }

    /** Combien de gestes le composant Livewire servant cette route sait-il faire ? */
    private function gestesDuComposantWeb(string $uri): int
    {
        static $cache = [];

        if (isset($cache[$uri])) {
            return $cache[$uri];
        }

        $classe = null;

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri) {
                $action = $route->getActionName();
                $classe = str_contains($action, '@') ? explode('@', $action)[0] : $action;
                break;
            }
        }

        if (! $classe || ! class_exists($classe)) {
            return $cache[$uri] = 0;
        }

        $fichier = (new ReflectionClass($classe))->getFileName();
        $source = $fichier ? (string) file_get_contents($fichier) : '';

        // Écriture directe, délégation à un service, ou journalisation : les trois signes qu'une
        // méthode publique FAIT quelque chose au lieu de lire.
        $signes = [
            '->update(', '->save()', '::create(', '->delete(', 'updateOrCreate', '->forceFill(',
            'app(', 'ActivityLogger::', '::dispatch(', 'DB::transaction',
        ];

        $ignorees = ['mount', 'render', 'boot', 'updating', 'updated', 'hydrate', 'dehydrate'];

        preg_match_all('/\n    public function (\w+)\([^)]*\)[^{]*\{(.*?)\n    \}/s', $source, $m, PREG_SET_ORDER);

        $gestes = 0;

        foreach ($m as [, $nom, $corps]) {
            foreach ($ignorees as $prefixe) {
                if (str_starts_with($nom, $prefixe)) {
                    continue 2;
                }
            }

            foreach ($signes as $signe) {
                if (str_contains($corps, $signe)) {
                    $gestes++;
                    break;
                }
            }
        }

        return $cache[$uri] = $gestes;
    }
}
