# CleanUx — Runbook de mise en production v2

Date : 2026-05-20. Version cible : 35 modules v2 livrés.

Ce document décrit la procédure pour activer en production les modules v2 livrés.
**Lire ce document en entier avant d'exécuter quoi que ce soit.**

---

## 0. Pré-requis

### Infrastructure
- PHP 8.3+
- MySQL 8 ou PostgreSQL 14+ (SQLite uniquement dev/tests)
- Redis (cache + sessions + queue)
- Queue worker actif (`php artisan queue:work --queue=default,webhooks,stripe-webhooks`)
- Reverb WebSocket server actif (broadcast realtime)
- Scheduler cron actif (`* * * * * cd /path && php artisan schedule:run`)
- Stockage objets S3-like recommandé pour `documents` (KYB, Contracts, Fleet, Chat attachments)

### Secrets à configurer dans `.env`
```env
# Stripe (subscriptions + payments)
CASHIER_KEY=pk_live_...
CASHIER_SECRET=sk_live_...
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_...

# KYB providers (au moins un)
INSEE_API_KEY=...                # France
COMPANIES_HOUSE_API_KEY=...      # UK
KVK_API_KEY=...                  # Pays-Bas

# Geolocation (au moins un)
GOOGLE_MAPS_API_KEY=...
# ou
MAPBOX_ACCESS_TOKEN=...

# FX (optionnel — fallback mock)
OPENEXCHANGE_API_KEY=...

# Sentry (recommandé)
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
```

---

## 1. Ordre des migrations

```bash
php artisan migrate --force
```

Les migrations sont versionnées par timestamp. L'ordre est garanti. Migration optionnelle :

```bash
# Activer le multi-tenancy (Phase 2 — facultatif)
php artisan migrate --path=database/migrations/2026_05_20_020001_add_tenant_id_to_users_optional.php --force
```

**Si tu utilisais déjà la table `customer_credits` en prod** :
```bash
# Migration additive — ne casse pas l'existant
php artisan migrate --path=database/migrations/2026_05_20_030001_align_customer_credits_schema.php --force
```

---

## 2. Seeders à exécuter manuellement

⚠️ **Ces seeders ne sont PAS dans `DatabaseSeeder` global** — exécution manuelle uniquement.

```bash
# Onboarding journeys (templates client/provider)
php artisan db:seed --class=Database\\Seeders\\OnboardingJourneysSeeder --force

# Cancellation policies (default fees structure)
php artisan db:seed --class=Database\\Seeders\\CancellationPoliciesSeeder --force

# Contract templates (TOS / NDA / provider agreement)
php artisan db:seed --class=Database\\Seeders\\ContractTemplatesSeeder --force

# API token scopes catalog (18 scopes)
php artisan db:seed --class=Database\\Seeders\\ApiTokenScopesSeeder --force

# Tenants (main + acme-demo)
php artisan db:seed --class=Database\\Seeders\\TenantsSeeder --force

# Subscription plans (3 demo plans cleaning + maintenance)
php artisan db:seed --class=Database\\Seeders\\SubscriptionPlansV2Seeder --force

# Webhook demo endpoint (suspended — à activer après config)
php artisan db:seed --class=Database\\Seeders\\WebhookEndpointsSeeder --force

# Fleet demo data
php artisan db:seed --class=Database\\Seeders\\FleetDemoSeeder --force

# KYB demo entities
php artisan db:seed --class=Database\\Seeders\\BusinessEntitiesDemoSeeder --force
```

---

## 3. Activation features par flag

Édite `.env` ou via `config/` selon préférence ops :

### Tier 1 — Activer dès J1
```env
WEBHOOKS_V2_ENABLED=true
WEBHOOKS_DRIVER=real

API_TOKENS_V2_ENABLED=true
SUBSCRIPTIONS_V2_ENABLED=true
SUBSCRIPTIONS_BILLING_DRIVER=stripe

GEO_PROVIDER=google  # ou mapbox
KYB_IDENTITY_PROVIDER=insee  # ou companies_house selon région
KYB_VAT_PROVIDER=vies  # gratuit, sans API key
```

### Tier 2 — Activer après validation manuelle (~J7)
```env
# Postage compta auto sur bookings
ACCOUNTING_AUTO_POST=true

# Auto-approve KYB si score < 30 ET identity OK ET sanctions clear
KYB_AUTO_APPROVE=true

# Cron horaires (déjà configurés dans app/Console/Kernel.php)
```

### Tier 3 — Activation Tenancy v2 (~J14, optionnel)
```bash
# 1. Migrer la colonne tenant_id (optionnelle) sur users
php artisan migrate --path=database/migrations/2026_05_20_020001_add_tenant_id_to_users_optional.php --force

# 2. Backfill tous les users existants avec tenant=main
php artisan tenancy:backfill --tenant=main --tables=users

# 3. Activer le trait BelongsToTenant sur User (manuel — édit code)
# Dans app/Models/User.php :
#   use App\Concerns\BelongsToTenant;
#   class User extends Authenticatable {
#       use BelongsToTenant;
#       ...

# 4. Tester en staging avec un 2e tenant
```

---

## 4. Vérification post-déploiement

### Healthchecks
```bash
curl -fsS https://cleanux.example/api/health
curl -fsS https://cleanux.example/api/ping
```

### Smoke tests admin
- [ ] `/admin/webhooks-v2` : créer endpoint test, envoyer un test ping → vérifier delivery
- [ ] `/admin/subscriptions-v2` : créer un plan, souscrire un test user
- [ ] `/admin/accounting-v2` : lister entries (vide acceptable), tester export CSV période en cours
- [ ] `/admin/kyb-v2` : voir 2 entities demo, lancer "run verifications" sur l'une
- [ ] `/admin/fleet-v2` : voir vans demo + équipements + scan expiring certs
- [ ] `/admin/tenancy-v2` : voir tenant main + acme-demo
- [ ] `/admin/api-tokens-v2` : créer un token de test avec scope `read:bookings`
- [ ] `/admin/chat-v2` : créer un thread depuis booking + envoyer message

### Cron jobs actifs
```bash
php artisan schedule:list
```

Doit lister entre autres :
- `subscriptions:tick` daily 03:00
- `accounting:close-previous-month` monthly day 6 04:00
- `fleet:scan-expiring` daily 05:00

### Webhooks B2B fonctionnels
```bash
# Créer une booking via API → vérifier webhook delivery sur ton endpoint test
curl -X POST https://cleanux.example/api/v2/bookings -H "Authorization: Bearer ..." -d '...'
# Puis :
php artisan tinker
>>> \App\Models\WebhookDelivery::latest()->take(5)->get(['id', 'event_id', 'status', 'attempt'])
```

---

## 5. Modules infrastructure-ready, à brancher ultérieurement

Ces modules sont livrés et testés mais nécessitent une activation explicite ou un développement supplémentaire :

| Module | État | Action requise |
|--------|------|----------------|
| Webhooks v2 | ✅ Wiring auto via Observers (A1) | Activation `auto_post_enabled` + créer endpoints partenaires B2B |
| Subscriptions v2 | ✅ Cron tick actif (A2) | Activer plan Stripe + lier `subscriptions_v2` aux flows Booking |
| Accounting v2 | ✅ Auto-post (A3) | Vérifier mapping comptes PCG, activer `auto_post_enabled` après validation compta |
| Fleet v2 | ✅ Cron scanner (A5) | Onboarder véhicules + équipements + certifications réelles |
| Geolocation v2 | ✅ GeoMatchingEnhancer (A6, opt-in) | Brancher dans MatchingScoreEngine si besoin scoring distance |
| Chat v2 | ✅ Auto-create bookings (A8) | Développer UI client (broadcast déjà actif) |
| Tenancy v2 | ⚠️ Backfill optionnel (A9) | Activer si besoin multi-tenancy / white-label |
| KYB v2 | ⚠️ Infrastructure | Onboarder partenaires B2B + sanctions provider réel (ComplyAdvantage) |

---

## 6. Wiring webhooks events branchés (récap)

Liste exhaustive des events émis automatiquement après mise en prod :

| Event | Source | Localisation |
|-------|--------|--------------|
| `booking.created` | BookingObserver::created | A1 |
| `booking.scheduled` | BookingObserver::saved | A1 |
| `booking.assigned` | BookingObserver::saved | A1 |
| `booking.started` | BookingObserver::saved | A1 |
| `booking.completed` | BookingObserver::saved | A1 |
| `booking.cancelled` | BookingObserver::saved | A1 |
| `payment.succeeded` | StripeWebhookEventProcessor | 1a |
| `payment.failed` | StripeWebhookEventProcessor | 1a |
| `payment.refunded` | StripeWebhookEventProcessor | 1a |
| `contract.signed` | ContractService::signDocument | A1 |
| `provider.kyc_approved` | EmitKycApprovedWebhook listener | 1b |
| `dispute.opened` | DisputeService::open | A1 |
| `dispute.resolved` | DisputeService::transitionStatus | A1 |
| `subscription.created` | SubscriptionEngine::subscribe | A1 |
| `subscription.cancelled` | SubscriptionEngine::cancel | A1 |
| `inspection.completed` | QualityInspectionService::submit | 1c |

---

## 7. Plan de rollback

### Rollback flag-only (rapide, sans downtime)
```bash
# Désactiver un module en cas de problème
echo "WEBHOOKS_V2_ENABLED=false" >> .env
php artisan config:cache
```

### Rollback migration (lent, avec downtime)
```bash
php artisan down --message="Maintenance — rollback en cours"
php artisan migrate:rollback --step=N
# Restore DB depuis snapshot si nécessaire
php artisan up
```

### Rollback application code
```bash
git revert <commit-sha-deployment>
git push origin main
# CI redéploie automatiquement
```

---

## 8. Monitoring & alerting recommandés

### Métriques à surveiller
- `webhook_deliveries.status='failed'` count par 5min → alert si > 50
- `webhook_endpoints.consecutive_failures` → alert si > 10 pour un endpoint
- `subscription_cycles.billing_status='failed'` per day → alert si > 10
- `business_sanctions_checks.status='match'` → alert immédiat (review humaine)
- `fleet_certifications` expirant dans 7j → email admin daily
- Queue worker `webhooks` job age > 5min → alert
- `accounting_periods.is_closed` pas synchro avec calendar → alert mensuel

### Sentry breadcrumbs intégrés
- `BusinessEventEmitter::emit()` → tags `event_code`, `source_type`
- `CriticalActionAuditor::record()` → severity automatique
- Webhook delivery failures → captured via Log warning

---

## 9. Tests E2E à valider en staging avant prod

- [ ] Cycle booking complet : create → paid → completed → webhook + accounting + chat archive
- [ ] Cycle subscription : subscribe → trial → first charge → past_due → recovery → cancel
- [ ] KYB B2B : create entity → run verifications → run sanctions → approve → webhook
- [ ] Fleet : create vehicle + cert insurance → assign provider → return damaged → maintenance log auto
- [ ] Contract : render → sign → PDF generate → webhook + audit
- [ ] Dispute : open → events → resolve → webhook + audit
- [ ] API token : create with scope `read:bookings` → call `/api/v2/bookings` → log usage
- [ ] Webhook delivery : booking.created → endpoint receives signed payload → verify HMAC
- [ ] Subscriptions tick cron : artisan `subscriptions:tick --dry-run` puis tick réel
- [ ] Period close cron : artisan `accounting:close-previous-month --force`
- [ ] Fleet scan cron : artisan `fleet:scan-expiring --show`
- [ ] GDPR erasure : créer user avec entités KYB/Fleet/Chat → erasure → vérifier anonymisation

---

## 10. Contacts & ressources

- Architecture détaillée : `C:\Users\mmdar\.claude\projects\c--Users-mmdar-Desktop-code-work-CleanUx\memory\MEMORY.md`
- Module-by-module : `memory/{module}_v2_module.md`
- Tests : `tests/Feature/{Module}V2/` (1380 tests, 0 failure)
- Code : `app/Services/{Module}V2/`, `app/Http/Controllers/Api/{Module}V2Controller.php`, `app/Livewire/Admin/{Module}V2/`

---

## Annexe A — Activation rapide (one-liner)

Pour staging/preview, séquence minimale :
```bash
php artisan migrate --force \
 && php artisan db:seed --class=Database\\Seeders\\OnboardingJourneysSeeder --force \
 && php artisan db:seed --class=Database\\Seeders\\ContractTemplatesSeeder --force \
 && php artisan db:seed --class=Database\\Seeders\\ApiTokenScopesSeeder --force \
 && php artisan db:seed --class=Database\\Seeders\\TenantsSeeder --force \
 && php artisan db:seed --class=Database\\Seeders\\SubscriptionPlansV2Seeder --force \
 && php artisan db:seed --class=Database\\Seeders\\CancellationPoliciesSeeder --force \
 && php artisan config:cache \
 && php artisan route:cache \
 && php artisan view:cache
```

## Annexe B — Tests régressions

Pour valider après changement :
```bash
# Tests du module touché
php artisan test tests/Feature/{Module}V2/

# Régression complète (long : ~45min en single-thread)
php artisan test

# Régression parallèle (recommandé une fois ParaTest installé)
composer require --dev brianium/paratest
php artisan test --parallel
```
