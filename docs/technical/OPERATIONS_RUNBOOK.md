# CleanUx — Operations Runbook

## Nettoyer les caches

    php artisan optimize:clear

## Vérifier les routes

    php artisan route:list

## Lancer les tests

    php artisan test

## Readiness

    php artisan app:consolidation-final-check
    php artisan app:production-health-check
    php artisan app:go-live-readiness-report

## Après modification Blade

    php artisan optimize:clear
    php artisan test tests/Feature/LivewireViewIntegrityTest.php tests/Feature/OptimizedDashboardExperienceSmokeTest.php

## Après modification booking

    php artisan test tests/Feature/RecurringBookingTest.php tests/Feature/ZoneAwareReservationTest.php tests/Feature/ZoneAwareStructuredReservationTest.php

## Après modification permissions

    php artisan test tests/Feature/AdminRouteAccessTest.php tests/Feature/ClientRouteAccessTest.php tests/Feature/EmployeRouteAccessTest.php

## Nettoyage avant commit

    rm -f p2*.patch
    rm -f phase2*.php
    rm -f phase2*.sh

    git restore storage/framework/testing/disks/local/ops/heartbeat.json 2>/dev/null || true
    git restore storage/framework/views 2>/dev/null || true
    git restore storage/logs/laravel.log 2>/dev/null || true

    git status
