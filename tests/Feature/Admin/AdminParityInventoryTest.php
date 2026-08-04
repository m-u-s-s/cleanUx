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

            if ($gestesWeb < self::SEUIL_MINIMAL) {
                continue;
            }

            $descripteur = $registre->for($module['key']);

            if (! $descripteur) {
                $manquants[] = $module['key'].' : aucun descripteur';

                continue;
            }

            $porte = count($descripteur->formFields()) + count($descripteur->actions());

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
