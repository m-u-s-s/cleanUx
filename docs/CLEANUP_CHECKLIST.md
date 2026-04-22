# CleanUx — nettoyage final

## À vérifier
- composants Livewire non utilisés
- vues orphelines
- routes sans écran réel
- imports inutilisés
- anciennes colonnes héritées encore lues directement
- seeders démo vs production bien séparés
- notifications anciennes jamais consommées
- rôles historiques (`societe`) encore présents dans des conditions UI
- gros composants Livewire à découper

## Commandes utiles
```bash
php artisan livewire:verify
php artisan livewire:missing-views
php artisan livewire:unused
php artisan livewire:unused-includes
php artisan app:audit-platform-integrity
php artisan app:cleanup-report
```

## Nettoyage recommandé
1. supprimer les vues Livewire non liées à aucune route
2. supprimer les composants jamais inclus ni routés
3. fusionner les vieux dashboards admin doublons
4. centraliser les constantes de statuts
5. créer des enums pour rôles, statuts, priorités
6. déplacer la config métier dans `config/cleanux.php`
7. garder une séparation claire entre références structurées et snapshots
8. continuer le refactor des très gros composants Livewire

## Bloc 4 — faux positifs et orphelins réels

### 1) Faux positif `livewire:missing-views`
Les composants suivants utilisent volontairement une vue partagée :
- `App\Livewire\Admin\EditRecurringBooking`
- `App\Livewire\Client\EditRecurringBooking`

Vue partagée réelle :
- `resources/views/livewire/recurring/edit-recurring-booking.blade.php`

Le check `livewire:missing-views` doit donc tenir compte des vues déclarées explicitement via `view('...')`, et pas seulement de la convention `app/Livewire/...` → `resources/views/livewire/...`.

### 2) Composants réellement orphelins à supprimer
Après application des blocs mission employé et client, les composants admin suivants restent sans route et sans inclusion Blade :
- `app/Livewire/Admin/ExecutiveDashboard.php`
- `app/Livewire/Admin/MissionAdvancedSearch.php`
- `app/Livewire/Admin/MissionQualityCenter.php`
- `resources/views/livewire/admin/executive-dashboard.blade.php`
- `resources/views/livewire/admin/mission-advanced-search.blade.php`
- `resources/views/livewire/admin/mission-quality-center.blade.php`

### 3) Script de suppression
```bash
php scripts/cleanup_orphan_livewire_components.php
php scripts/cleanup_orphan_livewire_components.php --apply
```

### 4) Vérification après nettoyage
```bash
php artisan livewire:missing-views
php artisan livewire:unused-includes
php artisan optimize:clear
php artisan test
```


## Bloc 9 — consolidation finale

### Commande d'audit rapide
```bash
php artisan app:consolidation-final-check
```

Cette commande scanne les dossiers `app`, `routes`, `resources/views` et `tests` pour repérer :
- rôles encore écrits en littéral
- statuts booking encore écrits en littéral
- statuts mission encore écrits en littéral
- priorités encore écrites en littéral
- marqueurs `TODO` / `FIXME` / `XXX`

### Cible de consolidation
- `User::*` pour les rôles
- `BookingStatus::*` pour les statuts rendez-vous
- `MissionStatus::*` pour les statuts mission
