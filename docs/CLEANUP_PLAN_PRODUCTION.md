# CleanUx — Cleanup plan avant production

Plan d'action **destructif** identifié par l'audit de scan code. À exécuter manuellement avec backup DB préalable. **NE PAS lancer en autonome.**

## 1. Pollution Git working tree (safe, no DB impact)

```bash
# Vérifie d'abord ce que tu vas supprimer
git status

# Si OK, supprime
rm -rf cleanux-fixes-may-2026/
rm -rf cleanux-multitrade-may-2026/
rm -rf cleanux-phase11/ cleanux-phase12/ cleanux-phase13/
rm -f cleanux-phase11.zip cleanux-phase12.zip cleanux-phase13.zip
rm -f before_restore_2026-05-04_18-10.sql
rm -f .env.bak
rm -f regression-*.log
rm -f 2026_05_05_000001_create_extras_consolidated.php
rm -f database/migrations/*.bak
```

## 2. Controllers orphelins (6 confirmés par audit, no callers)

```bash
rm app/Http/Controllers/Admin/MissionQualityExportController.php
rm app/Http/Controllers/EmployeeMissionQrController.php
rm app/Http/Controllers/ExportRendezVousController.php
rm app/Http/Controllers/FeedbackExportController.php
rm app/Http/Controllers/MissionReportController.php
# FeedbackInviteController.php a un test — câbler une route ou supprimer + test
```

## 3. Livewire orphelins (15 flagged)

Voir `docs/CLEANUP_CHECKLIST.md` pour les 7 déjà identifiés ; supprimer après vérification :
- app/Livewire/Admin/AutomationMissionGenerationCenter.php
- app/Livewire/Admin/ClientSegmentationCenter.php
- app/Livewire/Admin/ExecutiveDashboard.php
- app/Livewire/Admin/GestionEntreprises.php
- app/Livewire/Admin/GestionZones.php (si tests aussi sans usage)
- app/Livewire/Admin/IncidentsQualiteCenter.php
- app/Livewire/Admin/MissionAdvancedSearch.php
- app/Livewire/Admin/MissionProfitabilityCenter.php
- app/Livewire/Admin/MissionQualityCenter.php
- app/Livewire/Admin/OperationalQualityCenter.php
- app/Livewire/Admin/OperationsAlertsCenter.php
- app/Livewire/Admin/OrganizationContractsManager.php
- app/Livewire/Conversations/ConversationBox.php
- app/Livewire/Provider/ProviderPayoutsPage.php (si remplacé par Wallet)

## 4. Blade orphelins (7)

```bash
rm resources/views/livewire/admin/governance/layout-stack.blade.php
rm resources/views/livewire/admin/placeholder.blade.php
rm resources/views/livewire/admin/simple-center.blade.php
rm resources/views/livewire/client/litiges/create-form.blade.php
rm resources/views/livewire/client/litiges/kpis.blade.php
rm resources/views/livewire/client/litiges/list.blade.php
rm resources/views/livewire/client/recurring-series-edit.blade.php
```

## 5. Schema unification provider FK (HAUTE PRIORITÉ — backup DB requis)

```sql
-- ÉTAPE 1 : backfill toutes les variantes vers provider_user_id
UPDATE bookings SET provider_user_id = COALESCE(provider_user_id, employe_id, assigned_employee_id, assigned_provider_user_id);
UPDATE missions SET lead_provider_user_id = COALESCE(lead_provider_user_id, lead_employee_id);
UPDATE provider_wallet_transactions SET provider_user_id = COALESCE(provider_user_id, user_id);

-- ÉTAPE 2 : drop les colonnes legacy (après confirmation backfill OK)
ALTER TABLE bookings
    DROP COLUMN employe_id,
    DROP COLUMN assigned_employee_id,
    DROP COLUMN assigned_provider_user_id;
ALTER TABLE missions DROP COLUMN lead_employee_id;
```

⚠ **Test impact** : nombreux Models et services lisent `employe_id` ; chercher et adapter `Grep "->employe_id\|employe_id"`.

## 6. V1 tables duplicates à drop

```sql
-- Confirmer aucun code ne les utilise via Grep avant drop
DROP TABLE IF EXISTS subscriptions;        -- remplacé par subscriptions_v2
DROP TABLE IF EXISTS subscription_items;
DROP TABLE IF EXISTS subscription_plans;   -- remplacé par v2
DROP TABLE IF EXISTS invoices;             -- remplacé par finance_invoices
DROP TABLE IF EXISTS payments;             -- remplacé par finance_payments
DROP TABLE IF EXISTS provider_favorites;   -- remplacé par booking_favorites
DROP TABLE IF EXISTS provider_availabilities; -- remplacé par availability_slots
DROP TABLE IF EXISTS currency_rates;       -- remplacé par exchange_rates
DROP TABLE IF EXISTS limites_journalieres; -- remplacé par availability_v2
DROP TABLE IF EXISTS teams;                -- legacy Jetstream
DROP TABLE IF EXISTS account_subscriptions;
```

## 7. Amount unification : decimal → cents

Pour chaque table avec colonne `amount` (decimal) en parallèle d'autre table avec `amount_cents` (int) :

```sql
ALTER TABLE finance_invoices ADD COLUMN amount_cents INT UNSIGNED DEFAULT 0;
UPDATE finance_invoices SET amount_cents = ROUND(amount * 100);
ALTER TABLE finance_invoices DROP COLUMN amount;  -- après refactor code

ALTER TABLE finance_payments ADD COLUMN amount_cents INT UNSIGNED DEFAULT 0;
UPDATE finance_payments SET amount_cents = ROUND(amount * 100);
ALTER TABLE finance_payments DROP COLUMN amount;

-- Idem pour : provider_wallet_transactions, accounting_entries, referral_rewards,
-- dispute_resolutions, customer_credit_transactions
```

## 8. Migration squash (121 → ~30)

Une fois la prod stable et backup OK :
```bash
php artisan schema:dump --prune
```
Crée un dump SQL initial + supprime toutes les migrations antérieures.

## 9. Jobs orphans

Décider pour chaque : dispatch via cron OU supprimer.
- `PurgeAuditEventsJob` → dispatch dans cron `audit:purge` quotidien
- `RefreshFxRatesJob` → dispatch dans cron `currencies:refresh` (existant — wire job)
- `DispatchCampaignStepJob` → dispatch dans cron `marketing:dispatch-due-steps`
- `RecomputeSegmentJob` → dispatch dans cron `marketing:recompute-segments` hebdomadaire

## 10. Events orphans (listeners ou drop)

Décision par domaine :
- **Disputes** : Add listener pour notifier client/provider via Email v2 + Push
- **GDPR** : Add listener `OnGdprExportReady` → email avec lien download
- **KYC** : Add listeners `KycRejected` → email + dashboard provider; `KycStarted` → admin notif
- **Loyalty** : `LoyaltyTierUpgraded` → push + email félicitations
- **Promotion** : `ReferralQualified` → email confirmation
- **Rating** : `RatingHidden` → admin audit log; `RatingReported` → admin moderation queue
- **Tasks** : drop ou wire si encore utilisé

## Validation finale

```bash
php artisan migrate
php artisan test       # 1460+ tests doivent encore passer
php artisan ops:check-providers --strict   # en prod environment
```
