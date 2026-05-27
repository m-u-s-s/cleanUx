# Changelog

All notable changes to CleanUx are documented here.

## [Unreleased]

### Added
- **Design System** — Live component library at `/design-system` (15 sections)
- **Dark mode** — Toggle in nav, persisted via API, full cu-*/ui-* dark overrides
- **QR End Code flow** — End verification code generated at arrival, validated at completion
- **Admin Dashboard** — 6 KPIs (bookings, missions, providers, revenue, payouts, webhook failures)
- **WebSocket push tracking** — Echo `mission.{id}` channel, 15s polling fallback
- **Form Requests** — 11 classes wired into 8 controllers
- **Event Listeners** — DisputeOpened + RatingSubmitted notifications
- **Offline-first mobile** — offlineStorage, offlineQueue, useNetworkStatus
- **62 factories** covering all critical domain models
- **32 mobile tests** — screens, hooks, offline modules
- **Scribe API docs** — 154 annotations on 9 controllers
- **CSS modular architecture** — 8 modules (tokens, base, tool-mode, vitrine-mode, motion, a11y, premium, fullcalendar)
- **ESLint + Prettier** — with lint-staged
- **ARIA accessibility** — labels on layouts, toast role=alert
- **Booking model concerns** — HasLegacyBookingAliases, HasBookingPricing
- **preventLazyLoading** — N+1 detection (warnings in dev/staging)
- **CHANGELOG.md** — this file

### Changed
- CI pipeline fully blocking (coverage 70%, PHPStan L6, security audits)
- Sanctum token expiry 90d → 30d
- Backup enabled by default
- Antivirus scanning enabled by default for uploads

### Fixed
- XSS on contract-sign.blade.php (strip_tags whitelist)
- MissionAssignmentFactory SQLite column mismatch

### Security
- Rate limiting: 10 granular rules (auth, OTP, chat, uploads, API)
- CSP headers (configurable via env)
- GDPR: export, erasure, signed URLs, grace period

## [1.0.0] — 2026-05-20

### Added
- Core marketplace: booking, matching, dispatch, mission lifecycle, payments
- Stripe Connect: pre-auth, capture, refund, webhooks, provider payouts
- 50+ modules: Loyalty, Disputes, KYC/KYB, Insurance, GDPR, Accounting, Chat, etc.
- React Native mobile app (33 screens)
- PWA support with manifest and shortcuts
- Multi-tenancy architecture
- Real-time broadcasting via Reverb
- 1700+ passing tests
