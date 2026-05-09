<?php

$root = __DIR__;
$outputDir = $root . '/storage/app/route-audit';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

echo "🔍 Lecture des routes Laravel...\n";

$rawJson = shell_exec(PHP_BINARY . ' artisan route:list --json --no-ansi 2>&1');

if ($rawJson === null || trim($rawJson) === '') {
    echo "❌ Impossible d'exécuter php artisan route:list.\n";
    exit(1);
}

// Corrige le cas où Git Bash affiche "stdout is not a tty" avant le JSON.
$jsonStart = strpos($rawJson, '[');

if ($jsonStart !== false) {
    $rawJson = substr($rawJson, $jsonStart);
}

$routes = json_decode($rawJson, true);

if (! is_array($routes)) {
    file_put_contents($outputDir . '/route-list-error.txt', $rawJson);
    echo "❌ Impossible de lire le JSON de route:list.\n";
    echo "Regarde : storage/app/route-audit/route-list-error.txt\n";
    exit(1);
}

$defined = [];

foreach ($routes as $route) {
    if (! empty($route['name'])) {
        $defined[$route['name']] = $route;
    }
}

ksort($defined);

file_put_contents(
    $outputDir . '/defined-routes-names.txt',
    implode(PHP_EOL, array_keys($defined)) . PHP_EOL
);

$scanDirs = ['app', 'resources', 'routes', 'config', 'tests'];

$directCalls = [];
$optionalChecks = [];
$dynamicCalls = [];

echo "🔎 Scan des fichiers PHP/Blade...\n";

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

            // route('name'), ->route('name'), redirect()->route('name')
            if (preg_match_all('/(?<![A-Za-z0-9_])route\s*\(\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', $line, $matches)) {
                foreach ($matches[1] as $routeName) {
                    $directCalls[$routeName][] = $relativePath . ':' . $lineNumber;
                }
            }

            // to_route('name')
            if (preg_match_all('/to_route\s*\(\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', $line, $matches)) {
                foreach ($matches[1] as $routeName) {
                    $directCalls[$routeName][] = $relativePath . ':' . $lineNumber;
                }
            }

            // Route::has('name') = optionnel, pas forcément une erreur si manquant
            if (preg_match_all('/Route::has\s*\(\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', $line, $matches)) {
                foreach ($matches[1] as $routeName) {
                    $optionalChecks[$routeName][] = $relativePath . ':' . $lineNumber;
                }
            }

            // route($variable) = à vérifier manuellement
            if (str_contains($line, 'route(') && ! preg_match('/(?<![A-Za-z0-9_])route\s*\(\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', $line)) {
                $dynamicCalls[] = $relativePath . ':' . $lineNumber . ' => ' . trim($line);
            }
        }
    }
}

ksort($directCalls);
ksort($optionalChecks);

$missing = [];

foreach ($directCalls as $routeName => $locations) {
    if (! isset($defined[$routeName])) {
        $missing[$routeName] = $locations;
    }
}

$optionalMissing = [];

foreach ($optionalChecks as $routeName => $locations) {
    if (! isset($defined[$routeName])) {
        $optionalMissing[$routeName] = $locations;
    }
}

unset($missing['token']);

$missingText = '';

foreach ($missing as $routeName => $locations) {
    $missingText .= $routeName . PHP_EOL;

    foreach ($locations as $location) {
        $missingText .= '  - ' . $location . PHP_EOL;
    }

    $missingText .= PHP_EOL;
}

if ($missingText === '') {
    $missingText = "✅ Aucune route directe manquante détectée.\n";
}

$optionalText = '';

foreach ($optionalMissing as $routeName => $locations) {
    $optionalText .= $routeName . PHP_EOL;

    foreach ($locations as $location) {
        $optionalText .= '  - ' . $location . PHP_EOL;
    }

    $optionalText .= PHP_EOL;
}

if ($optionalText === '') {
    $optionalText = "✅ Aucune route optionnelle manquante détectée.\n";
}

$dynamicText = implode(PHP_EOL, $dynamicCalls);

if ($dynamicText === '') {
    $dynamicText = "✅ Aucun appel dynamique route(...) détecté.\n";
} else {
    $dynamicText .= PHP_EOL;
}

file_put_contents($outputDir . '/missing-routes.txt', $missingText);
file_put_contents($outputDir . '/optional-missing-routes.txt', $optionalText);
file_put_contents($outputDir . '/dynamic-route-calls.txt', $dynamicText);

echo PHP_EOL;
echo "✅ Audit terminé.\n";
echo "📄 Routes manquantes directes : storage/app/route-audit/missing-routes.txt\n";
echo "📄 Routes optionnelles manquantes : storage/app/route-audit/optional-missing-routes.txt\n";
echo "📄 Routes dynamiques : storage/app/route-audit/dynamic-route-calls.txt\n";
echo PHP_EOL;

echo "===== ROUTES DIRECTES MANQUANTES =====\n";
echo $missingText . PHP_EOL;

echo "===== ROUTES OPTIONNELLES MANQUANTES =====\n";
echo $optionalText . PHP_EOL;

echo "===== ROUTES DYNAMIQUES À VÉRIFIER =====\n";
echo $dynamicText . PHP_EOL;
