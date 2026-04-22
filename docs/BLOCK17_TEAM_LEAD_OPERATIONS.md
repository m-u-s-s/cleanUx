# Bloc 17 — Chef d’équipe opérationnel complet

## Objectif
Ajouter le cockpit du chef d’équipe terrain avec :
- répartition fine des segments
- statut membre par membre
- progression collective
- demande de renfort
- clôture d’intervention globale

## Fichiers inclus
- `app/Models/MissionTaskSegmentAssignment.php`
- `app/Models/MissionMemberStatus.php`
- `app/Models/MissionReinforcementRequest.php`
- `app/Services/Missions/TeamLeadOperationsService.php`
- `app/Livewire/Employe/TeamLeadOperationsCenter.php`
- `resources/views/livewire/employe/team-lead-operations-center.blade.php`
- `database/migrations/2026_04_24_190000_create_team_lead_operation_tables.php`
- `tests/Feature/TeamLeadOperationsCenterTest.php`
- `tests/Unit/TeamLeadOperationsServiceTest.php`

## Snippets à brancher manuellement
### Route employé
```php
Route::get('coordination-chef-equipe', \App\Livewire\Employe\TeamLeadOperationsCenter::class)
    ->name('team.operations');
```

### Navigation employé
Ajouter un lien vers `route('employe.team.operations')` dans le menu employé.

## Commandes après intégration
```bash
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan test tests/Feature/TeamLeadOperationsCenterTest.php
php artisan test tests/Unit/TeamLeadOperationsServiceTest.php
php artisan test
```
