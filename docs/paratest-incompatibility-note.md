# ParaTest — Incompatibilité actuelle

Date : 2026-05-20.

## Problème

Le projet utilise `phpunit/phpunit:10.x` et `phpunit/php-code-coverage:10.1.16`. Les versions récentes de `brianium/paratest` (v7.21+) exigent `phpunit/php-code-coverage:^11` ou `^14`, qui sont incompatibles avec le PHPUnit 10.x du projet.

Les versions de ParaTest compatibles PHPUnit 10 (v7.4-v7.20) requièrent PHP `~8.2 || ~8.3 || ~8.4` — mais le projet tourne en **PHP 8.5** qui est plus récent.

## Conséquence

L'option `php artisan test --parallel` n'est PAS disponible aujourd'hui. La régression complète tourne en single-process (~45-50 min).

## Solutions possibles (Phase 2)

### Option 1 — Downgrade PHP 8.5 → 8.4
Risque : faible, mais perd les nouveautés 8.5. Ouvre ParaTest v7.4-v7.20 compatible.

### Option 2 — Upgrade PHPUnit 10 → 11
Risque : moyen, beaucoup d'assertions changent de signature. Quelques tests à adapter. Ouvre ParaTest v7.21+ compatible.

```bash
composer require --dev phpunit/phpunit:^11 phpunit/php-code-coverage:^11 -W
# Puis ajuster les tests selon les breaking changes PHPUnit 11
composer require --dev brianium/paratest:^7.21
```

### Option 3 — Diviser la régression en batches
Sans ParaTest, on peut paralléliser manuellement en CI :
```yaml
strategy:
  matrix:
    suite: [unit, integration, modules-v2, legacy]
steps:
  - run: php artisan test --testsuite=${{ matrix.suite }}
```
Plus complexe à configurer mais 0 changement dépendances.

### Option 4 — Attendre upgrade Laravel 12 / PHPUnit 11
Quand l'équipe upgrade Laravel et PHPUnit, ParaTest devient automatiquement compatible.

## Recommandation

À court terme (J0-J30) : rester en single-thread, optimiser la lenteur via filtrage `--filter` sur les modules touchés en local. Régression complète uniquement en CI nightly.

À moyen terme (J30+) : option 2 (upgrade PHPUnit 11) au prochain refacto majeur, pour débloquer ParaTest.

## Workaround actuel

Pour valider un module spécifique sans full régression :
```bash
php artisan test tests/Feature/WebhooksV2/ tests/Feature/AccountingV2/ tests/Feature/SubscriptionsV2/
```

Le tier de tests d'intégration cross-modules (`tests/Feature/Integration/`) est complet et rapide (~30s pour 16 tests).
