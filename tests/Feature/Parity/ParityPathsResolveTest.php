<?php

namespace Tests\Feature\Parity;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ParityPathsResolveTest extends TestCase
{
    public function test_every_registry_path_resolves_to_a_registered_get_route(): void
    {
        $routes = Route::getRoutes();
        $unresolved = [];

        foreach (config('parity.modules', []) as $module) {
            $path = ltrim((string) $module['path'], '/');
            $matched = collect($routes->getRoutes())->first(function ($route) use ($path) {
                return in_array('GET', $route->methods(), true)
                    && trim($route->uri(), '/') === $path;
            });
            if (! $matched) {
                $unresolved[] = $module['key'].' → /'.$path;
            }
        }

        $this->assertSame([], $unresolved, "Registry paths with no matching GET route:\n".implode("\n", $unresolved));
    }
}
