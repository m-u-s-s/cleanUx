# CleanUx — checklist de consolidation finale

Cette checklist sert à verrouiller la plateforme après les blocs de correction et de refactor.

## 1. Vérification technique immédiate
```bash
php artisan optimize:clear
php artisan test
php artisan app:audit-platform-integrity --fail-on-issues
php artisan app:production-health-check --strict
php artisan app:consolidation-final-check
```

## 2. Points à valider avant merge / déploiement
- les pages `admin/pays`, `dashboard/employe/missions` et `dashboard/client/rendez-vous` s'affichent sans erreur
- le workflow mission complet fonctionne : en route → arrivé → code début → démarrage → code fin → clôture
- les dashboards admin / client / employé chargent sans doublons de toasts ou notifications
- aucun composant Livewire orphelin critique ne reste branché côté routes
- la séparation `RendezVous = booking` / `Mission = exécution terrain` reste respectée

## 3. Conventions désormais attendues
- utiliser `App\Models\User::*` pour les rôles
- utiliser `App\Support\Domain\BookingStatus::*` pour les statuts booking
- utiliser `App\Support\Domain\MissionStatus::*` pour les statuts mission
- éviter d'introduire de nouveaux littéraux métier dans les composants et services centraux

## 4. Nettoyage résiduel
Le rapport `app:consolidation-final-check` n'est pas forcément à zéro immédiatement.
Il sert surtout à identifier les zones qui restent à homogénéiser, sans bloquer le produit.

Ordre recommandé :
1. `app/Livewire`
2. `app/Services`
3. `app/Console/Commands`
4. `resources/views`
5. `tests`

## 5. Contrôle prêt prod
Avant un vrai déploiement :
```bash
php artisan app:seed-platform production --force
php artisan app:audit-platform-integrity --fail-on-issues
php artisan app:production-health-check --strict
php artisan schedule:list
```

## 6. Régression minimale à tester manuellement
- réservation publique standard
- réservation entreprise / multisite
- modification / annulation client
- suivi mission client
- workspace mission employé
- centre finance admin
- centre qualité / incidents
- admin pays / zones / services
