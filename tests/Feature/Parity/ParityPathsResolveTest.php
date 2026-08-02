<?php

namespace Tests\Feature\Parity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ParityPathsResolveTest extends TestCase
{
    /**
     * Chaque chemin du registre atteint une vraie route GET.
     *
     * La comparaison se faisait sur l'URI DÉCLARÉE, caractère par caractère. Elle ne voyait donc
     * pas les routes à segments optionnels : `/commander` est servi par
     * `commander/{sector?}/{trade?}`, qui répond parfaitement — mais dont l'URI ne s'écrit pas
     * « commander ». Le registre était déclaré cassé alors que la page s'ouvrait.
     *
     * On demande maintenant au ROUTEUR de résoudre, comme le ferait une requête réelle. C'est ce
     * que le test prétend vérifier, et c'est plus strict qu'une égalité de chaînes : une route
     * déclarée en POST seul, ou derrière une contrainte de paramètre que le chemin ne satisfait
     * pas, échoue désormais alors qu'elle passait avant.
     */
    public function test_every_registry_path_resolves_to_a_registered_get_route(): void
    {
        $routes = Route::getRoutes();
        $unresolved = [];

        foreach (config('parity.modules', []) as $module) {
            $path = '/'.ltrim((string) $module['path'], '/');

            try {
                $routes->match(Request::create($path, 'GET'));
            } catch (\Throwable $e) {
                $unresolved[] = $module['key'].' → '.$path.' ('.class_basename($e).')';
            }
        }

        $this->assertSame([], $unresolved, "Registry paths with no matching GET route:\n".implode("\n", $unresolved));
    }
}
