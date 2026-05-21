# Changelog — CleanUx

## [2026-05-21] Sprint A + B + Cleanup post-Laravel-11

### Added (Phase B — completing partial modules)
- ProductionBootstrapSeeder wire 14 V2 seeders (idempotent, schema-conditional)
- UTM capture persistance + AnalyticsEvent hook + User first-touch attribution
- NPS module : `Api\Client\NpsController`, `Client\NpsSurvey` Livewire, `Admin\Nps\NpsCenter` avec score calc
- UserSafety admin moderation UI : `Admin\Safety\SafetyCenter` (reports + blocks tabs, resolution modal)
- Provider Badges admin CRUD : `Admin\Badges\BadgesCenter` (catalog + awards) + `Api\Provider\BadgesController` (mine + evaluate)
- PushService multi-platform routing : APNs (iOS), FCM (Android/Web), fallback Mock

### Added (Phase A — hot fixes pré-prod)
- Stripe Connect : `connect_webhook_secret`, `connect_country` env, `connect_refresh_url`, `connect_return_url`
- 5 nouveaux crons : `stripe:reconcile`, `audit:purge`, `marketing:dispatch-steps`, `marketing:recompute-segments`, `fx:refresh-rates`
- `throttle:auth` middleware sur `/auth/login` + `/auth/register`
- `LoyaltyRedemption::redeem` envoie auto email voucher via EmailV2Service (soft-fail)
- `BookingObserver::saved` → `ProviderBadgeEngine::evaluate()` post-completion
- `CaptureUtm` middleware enregistré dans web group

### Changed
- Laravel 10.50 → **11.53** (Symfony 7, 0 CVE)
- PHPUnit 10 → 11
- Sanctum 3 → 4, Jetstream 4 → 5, Collision 7 → 8
- PHP version standardisée 8.3 partout (CI/composer/supervisor/systemd)
- Twilio webhook HMAC SHA1 vraie vérification (avant : skipped)
- `config/broadcasting.php` doublon `default` fusionné
- `LitigesClient` $selected + $selectedId promus en properties publiques Livewire

### Fixed
- CI `verify_php85.sh` script référencé mais inexistant → retiré
- Laravel 11 `CallbackEvent::withoutOverlapping()` requires `name()` BEFORE
- SQLite FK constraint stricter dans Laravel 11 : `messages.sender_id` migration drops FK+index avant column
- Carbon 3 signed `diffInMinutes` dans `CampaignEngineTest`
- Cancellation v2 : `tryStripeRefund` skeleton → vraie implémentation Stripe\Refund::create

### Removed (cleanup)
- 151 fichiers `D` (cleanux-fixes-may-2026/, cleanux-multitrade-may-2026/, cleanux-phase{11,12,13}/, zips, regression-*.log)
- 5 controllers orphans : MissionQualityExport, EmployeeMissionQr, ExportRendezVous, FeedbackExport, MissionReport
- 10+ Livewire orphans : ExecutiveDashboard, MissionAdvancedSearch, MissionQualityCenter, AutomationMissionGenerationCenter, IncidentsQualiteCenter, MissionProfitabilityCenter, OperationalQualityCenter, OperationsAlertsCenter, ClientSegmentationCenter, ConversationBox
- 17 blade orphans associés

### Security
- CORS env-driven (`CORS_ALLOWED_ORIGINS`)
- TrustProxies + TrustHosts middleware enabled
- SecurityHeaders middleware (HSTS prod-only + X-Frame + Permissions-Policy)
- Sanctum tokens expiration 90j default
- Stripe Connect country dynamique (plus hardcoded BE)
- 7 CVE Symfony Mailer/Mime/Routing patched via Laravel 11

## [2026-05-20] Sprints 0-9 prod-readiness

### Added (Modules #44-50)
- BookingCheckout (Stripe Elements + SetupIntent + 3DS handleCardAction)
- SavedPaymentMethods UI Livewire
- ProfileEdit UI Livewire
- ProviderEarningsDashboard avec drilldown daily/weekly/monthly + chart
- ClientLiveTrackingMap Leaflet (polling 10s + trail polyline)
- UserSafety module (Block/Report) + admin Livewire
- NPS module skeleton

### Added (Compliance EU)
- CGV (legal/terms.blade.php), Privacy Policy, Cookies policy, Mentions légales
- Cookie banner (Alpine.js localStorage + cookie 90j)
- HealthCheckController `/health` + `/health/deep` (DB+Cache+Queue+Storage)
- Sentry config + Spatie Backup config
- `.env.production.example` 180+ vars
- Capacitor `capacitor.config.ts` + JS bridge

## [2026-05-19—2026-05-20] Modules v2 livrés (#1-43)

50 modules au total. Voir `docs/` et memory pour détails par module.

### Modules principaux livrés
Promotions, Ratings, Matching v2, Stripe Hardening, i18n v2, Disputes, Search v2, KYC, GDPR, Loyalty (+ Redemption Marketplace), SMS v2, Push v2, Realtime v2, Analytics v2, Availability v2, Risk v2, Marketing v2, Insurance v2, FX v2, Audit v2, Notif Prefs v2, Quality v2, Cancellation v2, Onboarding v2, Pricing v2, Contracts v2, Webhooks v2 outbound, Geolocation v2, API Tokens v2, Chat v2, Subscriptions v2, Accounting v2, Tenancy v2, KYB v2, Fleet v2, Tips, Trip Tracking, Provider Badges, Booking Favorites, Presence v2, Email v2.
