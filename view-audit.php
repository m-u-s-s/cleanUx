cat > view-audit.php <<'PHP'
    <?php

    require_once __DIR__ . '/vendor/autoload.php';

    $root = __DIR__;
    $outputDir = $root . '/storage/app/view-audit';

    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    echo "🔍 Audit des vues Blade Laravel...\n";

    $scanDirs = ['app', 'resources', 'routes', 'config', 'tests'];

    $directViews = [];
    $dynamicViews = [];
    $bladeComponents = [];
    $dynamicComponents = [];

    function addOccurrence(array &$bag, string $name, string $location): void
    {
        if (! isset($bag[$name])) {
            $bag[$name] = [];
        }

        $bag[$name][] = $location;
    }

    function normalizeViewName(string $view): string
    {
        return trim($view);
    }

    function viewExists(string $root, string $view): bool
    {
        $path = str_replace('.', DIRECTORY_SEPARATOR, $view);

        $candidates = [
            $root . '/resources/views/' . $path . '.blade.php',
            $root . '/resources/views/' . $path . '.php',
            $root . '/resources/views/' . $path . '.html',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return true;
            }
        }

        return false;
    }

    function componentClassExists(string $component): bool
    {
        $parts = explode('.', str_replace('-', ' ', $component));

        $classParts = array_map(function ($part) {
            return str_replace(' ', '', ucwords($part));
        }, $parts);

        $class = 'App\\View\\Components\\' . implode('\\', $classParts);

        return class_exists($class);
    }

    function anonymousComponentExists(string $root, string $component): bool
    {
        $component = str_replace('::', '.', $component);
        $path = str_replace('.', DIRECTORY_SEPARATOR, $component);

        $candidates = [
            $root . '/resources/views/components/' . $path . '.blade.php',
            $root . '/resources/views/components/' . $path . '/index.blade.php',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return true;
            }
        }

        return false;
    }

    foreach ($scanDirs as $dir) {
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

            if (! preg_match('/\.(php|blade\.php)$/', $path)) {
                continue;
            }

            $relativePath = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            $content = file_get_contents($path);
            $lines = preg_split('/\R/', $content);

            foreach ($lines as $index => $line) {
                $lineNumber = $index + 1;
                $location = $relativePath . ':' . $lineNumber;

                /*
            |--------------------------------------------------------------------------
            | Direct PHP view calls
            |--------------------------------------------------------------------------
            | view('...')
            | return view('...')
            | View::make('...')
            */

                if (preg_match_all('/(?<![A-Za-z0-9_])view\s*\(\s*[\'"]([A-Za-z0-9_.:\-\/]+)[\'"]/', $line, $matches)) {
                    foreach ($matches[1] as $view) {
                        addOccurrence($directViews, normalizeViewName($view), $location);
                    }
                }

                if (preg_match_all('/View::make\s*\(\s*[\'"]([A-Za-z0-9_.:\-\/]+)[\'"]/', $line, $matches)) {
                    foreach ($matches[1] as $view) {
                        addOccurrence($directViews, normalizeViewName($view), $location);
                    }
                }

                /*
            |--------------------------------------------------------------------------
            | Blade directives
            |--------------------------------------------------------------------------
            | @include('...')
            | @includeIf('...')
            | @extends('...')
            | @component('...')
            | @each('...')
            */

                if (preg_match_all('/@(include|includeIf|includeWhen|includeUnless|extends|component)\s*\(\s*[\'"]([A-Za-z0-9_.:\-\/]+)[\'"]/', $line, $matches)) {
                    foreach ($matches[2] as $view) {
                        addOccurrence($directViews, normalizeViewName($view), $location);
                    }
                }

                if (preg_match_all('/@each\s*\(\s*[\'"]([A-Za-z0-9_.:\-\/]+)[\'"]/', $line, $matches)) {
                    foreach ($matches[1] as $view) {
                        addOccurrence($directViews, normalizeViewName($view), $location);
                    }
                }

                /*
            |--------------------------------------------------------------------------
            | Dynamic views
            |--------------------------------------------------------------------------
            | view($variable)
            | @include($variable)
            */

                if (
                    (str_contains($line, 'view(') || str_contains($line, '@include(') || str_contains($line, '@extends('))
                    && ! preg_match('/(?<![A-Za-z0-9_])view\s*\(\s*[\'"]/', $line)
                    && ! preg_match('/@(include|includeIf|includeWhen|includeUnless|extends|component)\s*\(\s*[\'"]/', $line)
                ) {
                    $dynamicViews[] = $location . ' => ' . trim($line);
                }

                /*
            |--------------------------------------------------------------------------
            | Blade components
            |--------------------------------------------------------------------------
            | <x-alert />
            | <x-ui.card>
            | <x-admin.widget />
            */

                if (preg_match_all('/<x-([A-Za-z0-9_.:\-]+)(\s|>|\/)/', $line, $matches)) {
                    foreach ($matches[1] as $component) {
                        if (
                            str_starts_with($component, 'slot')
                            || str_starts_with($component, 'dynamic-component')
                        ) {
                            continue;
                        }

                        addOccurrence($bladeComponents, $component, $location);
                    }
                }

                if (str_contains($line, '<x-dynamic-component')) {
                    $dynamicComponents[] = $location . ' => ' . trim($line);
                }
            }
        }
    }

    ksort($directViews);
    ksort($bladeComponents);

    unset($directViews['/']);

    foreach (array_keys($directViews) as $viewName) {
        if (str_starts_with($viewName, 'mail::')) {
            unset($directViews[$viewName]);
        }
    }

    $missingViews = [];

    foreach ($directViews as $view => $locations) {
        if (! viewExists($root, $view)) {
            $missingViews[$view] = $locations;
        }
    }

    unset($bladeComponents['app-layout']);
    unset($bladeComponents['guest-layout']);

    $missingComponents = [];

    foreach ($bladeComponents as $component => $locations) {
        if (
            ! anonymousComponentExists($root, $component)
            && ! componentClassExists($component)
        ) {
            $missingComponents[$component] = $locations;
        }
    }

    $missingViewsText = '';

    foreach ($missingViews as $view => $locations) {
        $missingViewsText .= $view . PHP_EOL;

        foreach ($locations as $location) {
            $missingViewsText .= '  - ' . $location . PHP_EOL;
        }

        $missingViewsText .= PHP_EOL;
    }

    if ($missingViewsText === '') {
        $missingViewsText = "✅ Aucune vue Blade manquante détectée.\n";
    }

    $missingComponentsText = '';

    foreach ($missingComponents as $component => $locations) {
        $missingComponentsText .= $component . PHP_EOL;

        foreach ($locations as $location) {
            $missingComponentsText .= '  - ' . $location . PHP_EOL;
        }

        $missingComponentsText .= PHP_EOL;
    }

    if ($missingComponentsText === '') {
        $missingComponentsText = "✅ Aucun composant Blade manquant détecté.\n";
    }

    $dynamicViewsText = implode(PHP_EOL, $dynamicViews);

    if ($dynamicViewsText === '') {
        $dynamicViewsText = "✅ Aucun appel dynamique de vue détecté.\n";
    } else {
        $dynamicViewsText .= PHP_EOL;
    }

    $dynamicComponentsText = implode(PHP_EOL, $dynamicComponents);

    if ($dynamicComponentsText === '') {
        $dynamicComponentsText = "✅ Aucun composant dynamique détecté.\n";
    } else {
        $dynamicComponentsText .= PHP_EOL;
    }

    file_put_contents($outputDir . '/missing-views.txt', $missingViewsText);
    file_put_contents($outputDir . '/missing-components.txt', $missingComponentsText);
    file_put_contents($outputDir . '/dynamic-views.txt', $dynamicViewsText);
    file_put_contents($outputDir . '/dynamic-components.txt', $dynamicComponentsText);

    file_put_contents(
        $outputDir . '/used-views.txt',
        implode(PHP_EOL, array_keys($directViews)) . PHP_EOL
    );

    file_put_contents(
        $outputDir . '/used-components.txt',
        implode(PHP_EOL, array_keys($bladeComponents)) . PHP_EOL
    );

    echo PHP_EOL;
    echo "✅ Audit vues terminé.\n";
    echo "📄 Vues manquantes : storage/app/view-audit/missing-views.txt\n";
    echo "📄 Composants manquants : storage/app/view-audit/missing-components.txt\n";
    echo "📄 Vues dynamiques : storage/app/view-audit/dynamic-views.txt\n";
    echo "📄 Composants dynamiques : storage/app/view-audit/dynamic-components.txt\n";
    echo PHP_EOL;

    echo "===== VUES MANQUANTES =====\n";
    echo $missingViewsText . PHP_EOL;

    echo "===== COMPOSANTS BLADE MANQUANTS =====\n";
    echo $missingComponentsText . PHP_EOL;

    echo "===== VUES DYNAMIQUES À VÉRIFIER =====\n";
    echo $dynamicViewsText . PHP_EOL;

    echo "===== COMPOSANTS DYNAMIQUES À VÉRIFIER =====\n";
    echo $dynamicComponentsText . PHP_EOL;
