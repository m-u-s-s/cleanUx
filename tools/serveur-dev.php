<?php

/*
 * Démarre `artisan serve` avec l'opcache coupé. Le drapeau doit atteindre le processus ENFANT
 * que `ServeCommand` lance : seul `PHP_INI_SCAN_DIR` y parvient, `php -d` reste sur le parent.
 */

chdir(dirname(__DIR__));

putenv('PHP_INI_SCAN_DIR='.__DIR__.DIRECTORY_SEPARATOR.'php');

$arguments = array_slice($_SERVER['argv'], 1);

/*
 * TOUTES LES INTERFACES PAR DÉFAUT, parce que le mobile en dépend.
 *
 * `artisan serve` écoute sur 127.0.0.1. Metro, lui, écoute sur 0.0.0.0 : l'application se charge
 * donc normalement dans l'émulateur, et SEULS les appels d'API échouent. On cherche alors le
 * défaut du côté de l'authentification, du compte ou du jeton — jamais du côté du réseau.
 *
 * `mobile/client/.env` vise l'IP Wi-Fi de la machine : c'est la seule adresse qui marche à la
 * fois depuis l'émulateur et depuis un téléphone du même réseau. Encore faut-il que le serveur
 * y réponde.
 *
 * Le serveur devient joignable depuis le réseau local — c'est le but, et c'est un serveur de
 * développement. `--host=...` passé explicitement reste prioritaire.
 */
$hoteDejaChoisi = (bool) array_filter(
    $arguments,
    static fn (string $argument): bool => str_starts_with($argument, '--host'),
);

if (! $hoteDejaChoisi) {
    $arguments[] = '--host=0.0.0.0';
}

passthru(
    escapeshellarg(PHP_BINARY).' artisan serve '.implode(' ', array_map('escapeshellarg', $arguments)),
    $code,
);

exit($code);
