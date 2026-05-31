<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Dev tool: emits candidate parity-registry entries for every navigable web area
 * (GET, no path params, exactly one segment past its role prefix — the top-level
 * navigable area for that role) under a known role prefix, EXCLUDING paths already
 * present in config/parity.php. Output is reviewed by a human and pasted into
 * config/parity.php (the registry stays a committed file).
 */
class ParityScaffoldRegistry extends Command
{
    protected $signature = 'parity:scaffold-registry {--json : Output JSON instead of PHP array literals}';

    protected $description = 'Emit candidate parity registry entries from the router';

    /** prefix => [role] */
    private const ROLE_PREFIXES = [
        'dashboard/client' => ['client'],
        'dashboard/employe' => ['provider'],
        'admin' => ['admin'],
    ];

    public function handle(Router $router): int
    {
        $existingPaths = collect(config('parity.modules', []))
            ->pluck('path')
            ->map(fn ($p) => '/'.ltrim((string) $p, '/'))
            ->all();

        $candidates = [];
        foreach ($router->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = $route->uri();
            if (str_contains($uri, '{')) {
                continue;
            }

            $match = $this->matchPrefix($uri);   // returns [prefix, roles] or null
            if ($match === null) {
                continue;
            }
            [$prefix, $roles] = $match;

            // one segment past the role prefix only (true top-level navigable area)
            if (substr_count($uri, '/') > substr_count($prefix, '/') + 1) {
                continue;
            }

            $path = '/'.ltrim($uri, '/');
            if (in_array($path, $existingPaths, true)) {
                continue;
            }
            $candidates[$path] = [
                'key' => Str::slug(str_replace('/', '-', $uri)),
                'title' => $this->titleFor($uri),
                'icon' => 'apps-outline',
                'path' => $path,
                'web' => 'native',
                'mobile' => 'webview',
                'roles' => $roles,
                'responsive_verified' => false,
            ];
        }

        $entries = array_values($candidates);

        if ($this->option('json')) {
            $this->line(json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($entries as $e) {
            $roles = "['".implode("', '", $e['roles'])."']";
            $this->line(sprintf(
                "['key' => '%s', 'title' => '%s', 'icon' => '%s', 'path' => '%s', 'web' => 'native', 'mobile' => 'webview', 'roles' => %s, 'responsive_verified' => false],",
                $e['key'], $e['title'], $e['icon'], $e['path'], $roles,
            ));
        }
        $this->info(count($entries).' candidate modules emitted.');

        return self::SUCCESS;
    }

    /** @return array{0:string,1:list<string>}|null */
    private function matchPrefix(string $uri): ?array
    {
        foreach (self::ROLE_PREFIXES as $prefix => $roles) {
            if (str_starts_with($uri, $prefix)) {
                return [$prefix, $roles];
            }
        }

        return null;
    }

    private function titleFor(string $uri): string
    {
        $last = Str::afterLast($uri, '/');

        return Str::of($last)->replace('-', ' ')->title()->toString();
    }
}
