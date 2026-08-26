<?php

/*
 * Démarre `artisan serve` avec l'opcache coupé. Le drapeau doit atteindre le processus ENFANT
 * que `ServeCommand` lance : seul `PHP_INI_SCAN_DIR` y parvient, `php -d` reste sur le parent.
 */

chdir(dirname(__DIR__));

putenv('PHP_INI_SCAN_DIR='.__DIR__.DIRECTORY_SEPARATOR.'php');

$arguments = array_map('escapeshellarg', array_slice($_SERVER['argv'], 1));

passthru(escapeshellarg(PHP_BINARY).' artisan serve '.implode(' ', $arguments), $code);

exit($code);
