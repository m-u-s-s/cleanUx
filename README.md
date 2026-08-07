# Brio

**Multi-trade marketplace** style Uber/Bolt — nettoyage, peinture, babysitting, toiturier, et 30+ métiers. Construit avec **Laravel 11**, **Livewire 3**, **Sanctum 4**, **Reverb**.

État : **50+ modules production-ready**, **2116 tests verts** (6007 assertions), **0 CVE Symfony** (Laravel 11.53), Sentry + Spatie Backup installés.

## Quick start

```bash
git clone <repo>
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=ProductionBootstrapSeeder
npm install && npm run build
php artisan serve
```

## Stack technique

- **Laravel 11.53** + PHP 8.3 + MySQL 8 / SQLite (tests)
- **Livewire 3.8** + Tailwind 3 + Alpine.js
- **Sanctum 4** (API tokens) + Jetstream 5 + Fortify
- **Reverb 1** (WebSocket) + Pusher fallback
- **Cashier 16** (Stripe) + Stripe Connect Express
- **Sentry 4.25** + Spatie Backup 9.3
- **Mobile** : apps Expo / React Native (`mobile/client` + `mobile/provider`) — Sentry RN, soumission EAS ; voir `docs/STORES_SUBMISSION_RUNBOOK.md`

## Modules livrés (50)

### Core (10)
RendezVous · Mission · Booking · Géo BE/FR · Zones/Sites · Stripe Connect · Notifications · Calendar Google · Excel exports · Health check

### Modules v2 (40)
**Trust & verification** : KYC v2 (Onfido/Veriff/SumSub) · KYB v2 (INSEE/VIES/CompaniesHouse) · Ratings v2 · Audit v2 · Risk v2 · UserSafety (Block/Report)
**Paiement & finance** : Stripe Hardening · Cancellation v2 · Subscriptions v2 · Accounting v2 (FEC FR DGFiP / Sage / QuickBooks) · FX v2 · Tips v2
**Engagement** : Loyalty + Loyalty Redemption · Provider Badges · Booking Favorites · NPS · Promotions & Parrainage · Marketing v2
**Communication** : SMS v2 (Twilio) · Push v2 (FCM/APNs multi-platform) · Realtime v2 (Reverb) · Email v2 · Chat v2 · Notification Preferences v2
**Operations** : Matching v2 · Dispatch · Quality v2 (checklists par trade) · Onboarding v2 · Pricing v2 (DSL rules) · Trip Tracking v2 (geofence + ETA) · Presence v2 · Availability v2 · Fleet v2
**B2B & infrastructure** : Webhooks outbound v2 (HMAC + retry) · API Tokens v2 (18 scopes) · Contracts v2 (eIDAS-lite) · Tenancy v2 (white-label) · Insurance v2 (Hiscox/Wakam) · Search v2 · Geolocation v2 (Google/Mapbox) · i18n v2 (fr/nl/en/es/it/de) · GDPR v2

### Phase A-B (10 hot fixes)
CGV/Privacy/Cookies/Mentions · Cookie banner · Health endpoints · UTM capture · Twilio Proxy ready · Stripe refund réel · Loyalty voucher email · Badge auto-eval post-mission · SecurityHeaders · TrustProxies

## Documentation

- `docs/CLEANUP_PLAN_PRODUCTION.md` — plan destructif DB cleanup
- `docs/PRODUCTION_RUNBOOK.md` — déploiement
- `docs/STORES_SUBMISSION_RUNBOOK.md` — Apple App Store + Google Play
- `docs/GO_LIVE_CHECKLIST.md` — checklist go-live
- `docs/sentry-integration.md` — Sentry setup
- `docs/backup-automation-guide.md` — Spatie Backup
- `docs/realtime-mobile.md` — realtime côté mobile (Expo / React Native)

## Tests

```bash
php artisan test                          # full regression (~4min sur PHPUnit 11)
php artisan test tests/Feature/Loyalty/   # un module
php artisan test --filter=Tip             # filtrer
```

## Production deploy

```bash
# 1. Sécurité
php artisan ops:check-providers --strict   # Doit return 0

# 2. Migrations + seeders
php artisan migrate --force
php artisan db:seed --class=ProductionBootstrapSeeder

# 3. Compilation assets
npm run build

# 4. Cache config
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 5. Workers (Supervisor + Systemd configs dans deploy/)
sudo systemctl restart brio-queue
sudo systemctl restart brio-scheduler
```

## Variables d'environnement

Voir `.env.production.example` (180+ vars documentées). Critiques :
- `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CONNECT_WEBHOOK_SECRET`
- `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM`
- `ONFIDO_API_TOKEN`, `ONFIDO_WEBHOOK_TOKEN`
- `FCM_CREDENTIALS_PATH`, `APNS_KEY_PATH`
- `SENTRY_LARAVEL_DSN`
- `CORS_ALLOWED_ORIGINS`, `TRUSTED_PROXIES`

## License

Propriétaire — voir `LICENSE`.
