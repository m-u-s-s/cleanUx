# CleanUx — Release Readiness Checklist

## 1. Vérification Git

Commande :

    git status

Résultat attendu :

    nothing to commit, working tree clean
    Your branch is up to date with 'origin/main'

## 2. Nettoyage Laravel

Commande :

    php artisan optimize:clear

## 3. Tests release

Commande :

    php artisan test tests/Feature/ProductionHealthCheckCommandTest.php tests/Feature/GoLiveReadinessReportCommandTest.php tests/Feature/ConsolidationFinalCheckCommandTest.php tests/Feature/AdminRouteAccessTest.php tests/Feature/OptimizedDashboardExperienceSmokeTest.php

## 4. Suite complète

Commande :

    php artisan test

Résultat attendu :

    200 passed
    4 skipped

## 5. Vérifications navigateur

À vérifier manuellement :

- Page publique
- Login
- Dashboard admin
- Dashboard client
- Dashboard employé
- Prise de rendez-vous
- Documents financiers client
- Missions employé
- Litiges client
- Incidents employé
- Centre B2B
- Centre finance
- Centre international
- Gouvernance / readiness

## 6. Production

Avant production :

- APP_ENV=production
- APP_DEBUG=false
- APP_URL en HTTPS
- Mail configuré
- Queue configurée
- Scheduler configuré
- Storage link créé
- Base de données sauvegardée
