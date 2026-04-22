# Block 16 — Charge réelle équipe/partenaire + génération automatisée

## Objectif
Transformer l’orchestration visible (blocs 11 à 15) en orchestration réellement opérable :
- snapshots de charge équipe et partenaire
- recommandation de ressources selon la charge
- génération automatique de lots depuis les work orders approuvés
- matérialisation des missions depuis les segments planifiés

## Nouvelles briques
- `FieldTeamLoadSnapshot`
- `ServicePartnerLoadSnapshot`
- `OperationalLoadCalculator`
- `EnterpriseWorkOrderMissionGeneratorService`
- centre admin `AutomationMissionGenerationCenter`

## Flux cible
1. Un work order B2B est approuvé.
2. Le générateur crée ou réutilise un `MissionBatch`.
3. Les jours et segments sont planifiés.
4. La charge équipe/partenaire est recalculée.
5. Les missions opérationnelles sont matérialisées automatiquement.

## Commandes recommandées après intégration
```bash
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan test tests/Feature/AdminAutomationMissionGenerationCenterTest.php
php artisan test tests/Feature/EnterpriseWorkOrderMissionGeneratorServiceTest.php
php artisan test
```
