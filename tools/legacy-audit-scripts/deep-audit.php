
<?php

require_once __DIR__ . '/vendor/autoload.php';

$root = __DIR__;
$outputDir = $root . '/storage/app/deep-audit';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

echo "🔍 Audit profond CleanUx...\n";

function normalizePathForAudit(string $path): string
{
    return str_replace(['\\', '//'], '/', $path);
}

function relativePathForAudit(string $root, string $path): string
{
    return ltrim(str_replace(normalizePathForAudit($root), '', normalizePathForAudit($path)), '/');
}

function collectPhpFiles(string $root, array $dirs): array
{
    $files = [];

    foreach ($dirs as $dir) {
        $fullDir = $root . DIRECTORY_SEPARATOR . $dir;

        if (! is_dir($fullDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            if (str_ends_with($path, '.php')) {
                $files[] = $path;
            }
        }
    }

    sort($files);

    return $files;
}

function collectBladeFiles(string $root, array $dirs): array
{
    $files = [];

    foreach ($dirs as $dir) {
        $fullDir = $root . DIRECTORY_SEPARATOR . $dir;

        if (! is_dir($fullDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            if (str_ends_with($path, '.blade.php')) {
                $files[] = $path;
            }
        }
    }

    sort($files);

    return $files;
}

function extractNamespace(string $content): ?string
{
    if (preg_match('/namespace\s+([^;]+);/', $content, $m)) {
        return trim($m[1]);
    }

    return null;
}

function extractClassLikeName(string $content): ?string
{
    if (preg_match('/\b(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $content, $m)) {
        return $m[2];
    }

    return null;
}

function extractFqcn(string $content): ?string
{
    $namespace = extractNamespace($content);
    $class = extractClassLikeName($content);

    if (! $namespace || ! $class) {
        return null;
    }

    return $namespace . '\\' . $class;
}

function kebabCaseForAudit(string $value): string
{
    $value = preg_replace('/(?<!^)[A-Z]/', '-$0', $value);
    $value = str_replace('_', '-', $value);

    return strtolower($value);
}

function livewireConventionView(string $fqcn): ?string
{
    $prefix = 'App\\Livewire\\';

    if (! str_starts_with($fqcn, $prefix)) {
        return null;
    }

    $relative = substr($fqcn, strlen($prefix));
    $parts = explode('\\', $relative);

    $viewParts = array_map(fn ($part) => kebabCaseForAudit($part), $parts);

    return 'livewire.' . implode('.', $viewParts);
}

function viewExistsForAudit(string $root, string $view): bool
{
    $view = str_replace('::', '.', $view);

    if (str_starts_with($view, 'mail::')) {
        return true;
    }

    $path = str_replace('.', DIRECTORY_SEPARATOR, $view);

    $candidates = [
        $root . '/resources/views/' . $path . '.blade.php',
        $root . '/resources/views/' . $path . '.php',
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            return true;
        }
    }

    return false;
}

function parseUseStatements(string $content): array
{
    $uses = [];

    if (preg_match_all('/^use\s+([^;]+);/m', $content, $matches)) {
        foreach ($matches[1] as $use) {
            $use = trim($use);

            if (str_contains($use, ' as ')) {
                [$fqcn, $alias] = preg_split('/\s+as\s+/i', $use);
                $uses[trim($alias)] = trim($fqcn);
            } else {
                $parts = explode('\\', $use);
                $uses[end($parts)] = $use;
            }
        }
    }

    return $uses;
}

function resolveClassNameForAudit(string $raw, ?string $namespace, array $uses): string
{
    $raw = trim($raw, '\\');

    if (str_starts_with($raw, 'App\\')) {
        return $raw;
    }

    if (isset($uses[$raw])) {
        return $uses[$raw];
    }

    if ($namespace) {
        return $namespace . '\\' . $raw;
    }

    return $raw;
}

function routeListForAudit(string $root): array
{
    $rawJson = shell_exec(PHP_BINARY . ' artisan route:list --json --no-ansi 2>&1');

    if ($rawJson === null || trim($rawJson) === '') {
        return [[], []];
    }

    $jsonStart = strpos($rawJson, '[');

    if ($jsonStart !== false) {
        $rawJson = substr($rawJson, $jsonStart);
    }

    $routes = json_decode($rawJson, true);

    if (! is_array($routes)) {
        return [[], []];
    }

    $names = [];
    $uris = [];

    foreach ($routes as $route) {
        if (! empty($route['name'])) {
            $names[$route['name']] = true;
        }

        if (! empty($route['uri'])) {
            $uris[] = trim($route['uri'], '/');
        }
    }

    return [$names, $uris];
}

function urlMatchesKnownRoute(string $url, array $uris): bool
{
    $url = trim(parse_url($url, PHP_URL_PATH) ?? $url, '/');

    foreach ($uris as $uri) {
        $pattern = preg_quote(trim($uri, '/'), '#');
        $pattern = preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', $pattern);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $url)) {
            return true;
        }
    }

    return false;
}

function textReport(array $items, string $emptyMessage): string
{
    if ($items === []) {
        return $emptyMessage . PHP_EOL;
    }

    $text = '';

    foreach ($items as $name => $locations) {
        $text .= $name . PHP_EOL;

        foreach ($locations as $location) {
            $text .= '  - ' . $location . PHP_EOL;
        }

        $text .= PHP_EOL;
    }

    return $text;
}

function addIssue(array &$bag, string $name, string $location): void
{
    if (! isset($bag[$name])) {
        $bag[$name] = [];
    }

    $bag[$name][] = $location;
}

/*
|--------------------------------------------------------------------------
| 1. Livewire components without Blade view
|--------------------------------------------------------------------------
*/

$missingLivewireViews = [];
$livewireComponents = [];

foreach (collectPhpFiles($root, ['app/Livewire']) as $file) {
    $content = file_get_contents($file);
    $relative = relativePathForAudit($root, $file);
    $fqcn = extractFqcn($content);

    if (! $fqcn) {
        continue;
    }

    if (! str_starts_with($fqcn, 'App\\Livewire\\')) {
        continue;
    }

    if (
        ! str_contains($content, 'extends Component')
        && ! str_contains($content, 'extends \\Livewire\\Component')
        && ! str_contains($content, 'extends Livewire\\Component')
    ) {
        continue;
    }

    $views = [];

    if (preg_match_all('/return\s+view\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $view) {
            $views[] = $view;
        }
    }

    if ($views === []) {
        $convention = livewireConventionView($fqcn);

        if ($convention) {
            $views[] = $convention;
        }
    }

    $livewireComponents[$fqcn] = [
        'file' => $relative,
        'views' => $views,
    ];

    foreach ($views as $view) {
        if (! viewExistsForAudit($root, $view)) {
            addIssue($missingLivewireViews, $view, $relative . ' => ' . $fqcn);
        }
    }
}

/*
|--------------------------------------------------------------------------
| 2. PSR-4 audit
|--------------------------------------------------------------------------
*/

$psr4Issues = [];

$composerPath = $root . '/composer.json';
$composer = file_exists($composerPath)
    ? json_decode(file_get_contents($composerPath), true)
    : [];

$psr4Maps = [];

foreach (['autoload', 'autoload-dev'] as $section) {
    foreach (($composer[$section]['psr-4'] ?? []) as $prefix => $dirs) {
        foreach ((array) $dirs as $dir) {
            $psr4Maps[] = [
                'prefix' => $prefix,
                'dir' => rtrim($dir, '/\\'),
            ];
        }
    }
}

foreach ($psr4Maps as $map) {
    $baseDir = $root . DIRECTORY_SEPARATOR . $map['dir'];

    if (! is_dir($baseDir)) {
        continue;
    }

    foreach (collectPhpFiles($root, [$map['dir']]) as $file) {
        $content = file_get_contents($file);
        $fqcn = extractFqcn($content);

        if (! $fqcn) {
            continue;
        }

        if (! str_starts_with($fqcn, $map['prefix'])) {
            addIssue(
                $psr4Issues,
                'Namespace hors préfixe PSR-4',
                relativePathForAudit($root, $file) . ' => ' . $fqcn . ' attendu sous ' . $map['prefix']
            );

            continue;
        }

        $relativeClass = substr($fqcn, strlen($map['prefix']));
        $expected = normalizePathForAudit($root . '/' . $map['dir'] . '/' . str_replace('\\', '/', $relativeClass) . '.php');
        $actual = normalizePathForAudit($file);

        if ($expected !== $actual) {
            addIssue(
                $psr4Issues,
                $fqcn,
                'Actuel:  ' . relativePathForAudit($root, $actual) . PHP_EOL .
                '    Attendu: ' . relativePathForAudit($root, $expected)
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| 3. $this->method() calls audit
|--------------------------------------------------------------------------
*/

$thisMethodIssues = [];
$unloadableClasses = [];

foreach (collectPhpFiles($root, ['app']) as $file) {
    $content = file_get_contents($file);
    $relative = relativePathForAudit($root, $file);
    $fqcn = extractFqcn($content);

    if (! $fqcn) {
        continue;
    }

    if (! class_exists($fqcn) && ! trait_exists($fqcn)) {
        addIssue($unloadableClasses, $fqcn, $relative);
        continue;
    }

    if (! class_exists($fqcn)) {
        continue;
    }

    try {
        $reflection = new ReflectionClass($fqcn);
    } catch (Throwable $e) {
        addIssue($unloadableClasses, $fqcn, $relative . ' => ' . $e->getMessage());
        continue;
    }

    $knownMethods = [];

    foreach ($reflection->getMethods() as $method) {
        $knownMethods[$method->getName()] = true;
    }

    $hasMagicCall = isset($knownMethods['__call']);

    if (preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $match) {
            $method = $match[0];

            if ($hasMagicCall || isset($knownMethods[$method])) {
                continue;
            }

            if (in_array($method, ['dispatchBrowserEvent', 'emit', 'emitTo'], true)) {
                continue;
            }

            $before = substr($content, 0, $match[1]);
            $line = substr_count($before, "\n") + 1;

            addIssue(
                $thisMethodIssues,
                $fqcn . '::$this->' . $method . '()',
                $relative . ':' . $line
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| 4. Tests pointing to old routes/views/livewire/pages
|--------------------------------------------------------------------------
*/

[$definedRouteNames, $definedUris] = routeListForAudit($root);

$testRouteIssues = [];
$testViewIssues = [];
$testLivewireClassIssues = [];
$testHardcodedUrlIssues = [];

foreach (collectPhpFiles($root, ['tests']) as $file) {
    $content = file_get_contents($file);
    $relative = relativePathForAudit($root, $file);
    $namespace = extractNamespace($content);
    $uses = parseUseStatements($content);
    $lines = preg_split('/\R/', $content);

    foreach ($lines as $index => $line) {
        $lineNumber = $index + 1;
        $location = $relative . ':' . $lineNumber;

        if (preg_match_all('/route\s*\(\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', $line, $matches)) {
            foreach ($matches[1] as $routeName) {
                if (! isset($definedRouteNames[$routeName])) {
                    addIssue($testRouteIssues, $routeName, $location);
                }
            }
        }

        if (preg_match_all('/assertViewIs\s*\(\s*[\'"]([A-Za-z0-9_.:-]+)[\'"]/', $line, $matches)) {
            foreach ($matches[1] as $viewName) {
                if (! viewExistsForAudit($root, $viewName)) {
                    addIssue($testViewIssues, $viewName, $location);
                }
            }
        }

        if (preg_match_all('/Livewire::test\s*\(\s*\\\\?([A-Za-z0-9_\\\\]+)::class/', $line, $matches)) {
            foreach ($matches[1] as $rawClass) {
                $fqcn = resolveClassNameForAudit($rawClass, $namespace, $uses);

                if (! class_exists($fqcn)) {
                    addIssue($testLivewireClassIssues, $fqcn, $location);
                }
            }
        }

        if (preg_match_all('/\$this->(get|post|put|patch|delete)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $line, $matches)) {
            foreach ($matches[2] as $url) {
                if (! str_starts_with($url, '/')) {
                    continue;
                }

                if (! urlMatchesKnownRoute($url, $definedUris)) {
                    addIssue($testHardcodedUrlIssues, $url, $location);
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
*/

$reports = [
    'missing-livewire-views.txt' => textReport(
        $missingLivewireViews,
        '✅ Aucun composant Livewire sans vue Blade détecté.'
    ),
    'psr4-mismatches.txt' => textReport(
        $psr4Issues,
        '✅ Aucune erreur PSR-4 détectée.'
    ),
    'this-methods-missing.txt' => textReport(
        $thisMethodIssues,
        '✅ Aucun appel $this->method() inexistant détecté.'
    ),
    'unloadable-classes.txt' => textReport(
        $unloadableClasses,
        '✅ Toutes les classes app/ scannées sont chargeables.'
    ),
    'test-missing-routes.txt' => textReport(
        $testRouteIssues,
        '✅ Aucun test ne pointe vers une route inexistante.'
    ),
    'test-missing-views.txt' => textReport(
        $testViewIssues,
        '✅ Aucun test ne pointe vers une vue inexistante.'
    ),
    'test-missing-livewire-classes.txt' => textReport(
        $testLivewireClassIssues,
        '✅ Aucun test ne pointe vers une classe Livewire inexistante.'
    ),
    'test-hardcoded-url-issues.txt' => textReport(
        $testHardcodedUrlIssues,
        '✅ Aucun hardcoded URL suspect dans les tests.'
    ),
];

foreach ($reports as $filename => $content) {
    file_put_contents($outputDir . '/' . $filename, $content);
}

file_put_contents(
    $outputDir . '/livewire-components.txt',
    implode(PHP_EOL, array_map(
        fn ($fqcn, $data) => $fqcn . ' => ' . $data['file'] . ' => ' . implode(', ', $data['views']),
        array_keys($livewireComponents),
        $livewireComponents
    )) . PHP_EOL
);

echo PHP_EOL;
echo "✅ Audit profond terminé.\n";
echo "📁 Résultats : storage/app/deep-audit\n";
echo PHP_EOL;

foreach ($reports as $filename => $content) {
    echo "===== {$filename} =====\n";
    echo $content . PHP_EOL;
}
