# Maintenance guide — CleanUx

## Commandes utiles

Nettoyer Laravel :

php artisan optimize:clear

Lancer tous les tests :

php artisan test

Lancer les tests principaux :

php artisan test tests/Feature/AdminRouteAccessTest.php
php artisan test tests/Feature/ClientRouteAccessTest.php
php artisan test tests/Feature/EmployeRouteAccessTest.php
php artisan test tests/Feature/OptimizedDashboardExperienceSmokeTest.php

## Zones sensibles

À modifier avec prudence :

- routes admin/client/employé ;
- composants Livewire ;
- exports PDF/CSV ;
- notifications ;
- booking ;
- finance ;
- B2B / Enterprise ;
- permissions admin ;
- zone-scoped admin.

## Règle Livewire importante

Chaque composant Livewire doit avoir un seul élément racine.

Correct :

<div>
    contenu
</div>

Incorrect :

<div>contenu</div>
<section>autre contenu</section>

## Fichiers à ne pas committer

- storage/logs/*
- storage/framework/views/*
- storage/framework/testing/*
- .env
