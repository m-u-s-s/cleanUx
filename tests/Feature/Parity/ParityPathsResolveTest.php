<?php

namespace Tests\Feature\Parity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ParityPathsResolveTest extends TestCase
{
    /** Chaque chemin du registre atteint une vraie route GET. */
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
