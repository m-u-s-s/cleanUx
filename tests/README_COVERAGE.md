# Dossier tests CleanUx

Ce dossier couvre maintenant non seulement le ZIP d’origine, mais aussi les **stabilisations blocs 1 à 8**.

## Couverture historique déjà présente
- pages publiques et redirections dashboard par rôle
- accès admin / client / employé aux routes protégées
- endpoint API `/api/user`
- feedbacks et exports
- helpers `User`, `RendezVous`, `Parametre`
- relations domaine (géographie, zones, organisation)
- notifications métier

## Couverture ciblée ajoutée pour les blocs 1 à 8
- rendu de la page admin `pays`
- composant `CountryOperationsCenter`
- workspace mission employé avec sélection explicite
- panneau mission côté client branché dans `MesRendezVousClient`
- rendu Livewire des pages réparées / vues partagées
- intégrité des routes d’édition des séries récurrentes

## Important
Ces tests supposent que les patchs blocs 1 à 7 ont été appliqués :
- workspace mission employé
- suivi mission client
- centre pays admin
- nettoyage Livewire
- refactors booking / dashboard / mission

## Lancement conseillé
```bash
composer dump-autoload
php artisan optimize:clear
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

## Commande de ciblage pratique
```bash
php artisan test tests/Feature/AdminCountriesPageTest.php
php artisan test tests/Feature/EmployeMissionWorkspaceTest.php
php artisan test tests/Feature/ClientMissionTrackingPanelTest.php
php artisan test tests/Feature/LivewireViewIntegrityTest.php
```
