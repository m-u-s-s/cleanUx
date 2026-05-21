<?php

/**
 * Patches Laravel 11 vendor files for PHP 8.5 deprecation warnings.
 *
 * Specifically : `PDO::MYSQL_ATTR_SSL_CA` deprecated in PHP 8.5 in favor of
 * `Pdo\Mysql::ATTR_SSL_CA`. Laravel 11.53 hasn't been patched yet.
 *
 * Idempotent : si déjà patché, no-op. Si vendor pas installé, no-op.
 *
 * Auto-exécuté via composer.json post-install-cmd + post-update-cmd.
 */

$file = __DIR__ . '/../vendor/laravel/framework/config/database.php';

if (! file_exists($file)) {
    fwrite(STDERR, "[patch] vendor file absent, skip\n");
    exit(0);
}

$content = file_get_contents($file);
if ($content === false) {
    fwrite(STDERR, "[patch] cannot read $file\n");
    exit(1);
}

// Si déjà patché, no-op
if (str_contains($content, 'PHP 8.5 deprecation-safe')) {
    echo "[patch] Already patched, skip\n";
    exit(0);
}

// Remplace `PDO::MYSQL_ATTR_SSL_CA` par une expression PHP 8.5-safe
// On utilise une closure qui choisit la bonne constante selon la version PHP.
$replacement = '/* PHP 8.5 deprecation-safe */ (PHP_VERSION_ID >= 80500 && class_exists("Pdo\\\\Mysql") ? \\Pdo\\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA)';

$patched = str_replace(
    'PDO::MYSQL_ATTR_SSL_CA',
    $replacement,
    $content,
    $count,
);

if ($count === 0) {
    fwrite(STDERR, "[patch] no occurrence of PDO::MYSQL_ATTR_SSL_CA found (vendor may have been updated)\n");
    exit(0);
}

if (file_put_contents($file, $patched) === false) {
    fwrite(STDERR, "[patch] cannot write $file\n");
    exit(1);
}

echo "[patch] Patched {$count} occurrence(s) of PDO::MYSQL_ATTR_SSL_CA in $file\n";
exit(0);
