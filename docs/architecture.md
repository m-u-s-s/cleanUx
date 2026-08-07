# Brio Architecture — C4 Diagrams

## Level 1 — System Context

```
+------------------+         books service          +-------------------+
|                  |  --------------------------->  |                   |
|   Client         |                                |                   |
|   (Personal /    |  <---------------------------  |                   |
|    Company)      |    confirmation / live track   |                   |
+------------------+                                |                   |
                                                    |    C l e a n U x  |
+------------------+         accepts mission        |                   |
|                  |  --------------------------->  |    (SaaS OS for   |
|   Provider       |                                |  multi-trade      |
|   (Independent / |  <---------------------------  |  services)        |
|    Company)      |    dispatch / QR / payout      |                   |
+------------------+                                |                   |
                                                    |                   |
+------------------+         manages platform       |                   |
|                  |  --------------------------->  |                   |
|   Admin /        |                                |                   |
|   Zone Mgr       |  <---------------------------  |                   |
+------------------+    analytics / alerts          +-------------------+
                                                          |   |   |   |
              +-------------------------------------------+   |   |   |
              |           +-------------------------------+   |   |   |
              v           v                               |   v   |   |
     +---------+   +-----------+                  +------+   |   v   |
     | Stripe  |   |  Twilio   |                  |  FCM /   | ECB/  |
     | Payments|   |  SMS      |                  |  APNs    | Open  |
     +---------+   +-----------+                  +------+   | Exch  |
                                                             +-------+
              +--------+    +----------+    +----------+
              | Google |    |  Onfido/ |    |  Hiscox/ |
              | Maps / |    |  Veriff  |    |  Wakam   |
              | Mapbox |    |  (KYC)   |    | (Insur.) |
              +--------+    +----------+    +----------+
```

**External systems:**

| System | Purpose |
|--------|---------|
| Stripe | Payment intents, refunds, Connect transfers, subscriptions |
| Twilio | SMS OTP + notifications |
| Google Maps / Mapbox | Geocoding, autocomplete, distance matrix |
| FCM / APNs | Mobile push notifications |
| Onfido / Veriff / SumSub | KYC identity verification |
| Hiscox / Wakam | Intervention insurance |
| ECB / OpenExchangeRates | FX rate feeds |

---

## Level 2 — Container Diagram

```
+----------------------------------------------------------+
|                     Brio System                       |
|                                                          |
|  +------------------+      +-------------------------+  |
|  |  Web Application |      |  REST / JSON API        |  |
|  |  Laravel 11 +    |      |  Laravel (same app,     |  |
|  |  Livewire 3      |      |  routes/api.php)        |  |
|  |  Blade / Vite    |      |  Sanctum auth           |  |
|  |                  |<---->|                         |  |
|  | Admin, B2B,      |      | Mobile clients,         |  |
|  | Provider,        |      | B2B integrations,       |  |
|  | Client portals   |      | Webhooks inbound        |  |
|  +------------------+      +-------------------------+  |
|           |                          |                   |
|           +----------+  +-----------+                   |
|                      v  v                               |
|               +---------------+                         |
|               |   MySQL 8     |  Primary datastore       |
|               |  (production) |  All domain tables       |
|               +---------------+                         |
|                      |                                  |
|               +---------------+                         |
|               |  Redis 7      |  Cache, queues,         |
|               |               |  rate limiting,         |
|               |               |  session                |
|               +---------------+                         |
|                      |                                  |
|               +---------------+                         |
|               |  Queue Worker |  Laravel Horizon /      |
|               |  (Redis)      |  php artisan queue:work |
|               +---------------+                         |
|                      |                                  |
|               +---------------+                         |
|               | WebSocket     |  Laravel Reverb         |
|               | Server        |  (Pusher protocol)      |
|               | (Reverb)      |  Presence, live ETA,    |
|               +---------------+  chat, broadcasts       |
|                                                          |
|  +-----------------------+  +------------------------+  |
|  | Mobile Client App     |  | Mobile Provider App    |  |
|  | React Native / Expo   |  | React Native / Expo    |  |
|  | (iOS + Android)       |  | (iOS + Android)        |  |
|  | Phase 1 — B2C clients |  | Phase 2 — Providers    |  |
|  +-----------------------+  +------------------------+  |
+----------------------------------------------------------+
```

---

## Level 3 — Component: Booking Flow

```
Client submits booking form
        |
        v
[BookingController::store()]
  - Validate trade form answers
  - Run TradePricingEngine::estimate()     <-- ServiceCatalog > ZonePricing > TradeDefault
  - Create Booking (status: pending)
        |
        v
[MatchingV2Engine::dispatch()]
  - Score providers (availability, rating, zone, skills)
  - Create mission_dispatch_attempts rows
  - Broadcast dispatch events via Reverb
        |
        v
[Provider receives dispatch]
  - Accepts or rejects within SLA
        |
      accept
        |
        v
[Booking status: confirmed]
  - Stripe pre-auth PaymentIntent created
  - SMS/Push notification to client
  - Calendar entry created
        |
        v
[Provider en-route]
  - TripTrackingService::startSession()
  - Live ETA broadcast every ping
  - Client sees map via ClientLiveTrackingMap
        |
        v
[Provider arrives — QR scan start]
  - Booking status: in_progress
  - Quality checklist unlocked
        |
        v
[Mission execution]
  - Quality inspection (checklist items, photos)
  - Chat v2 available (client <-> provider)
        |
        v
[Provider QR scan end]
  - Booking status: completed
  - PaymentIntent captured
  - TipService::suggestionsForBooking()
  - LoyaltyEngine::creditBooking()
  - RatingRequest dispatched (48h reveal)
  - ProviderBadgeEngine::evaluate()
  - ProviderWallet credited (net of commission)
        |
        v
[Post-booking]
  - NPS survey after 2h
  - Rating revealed after 48h (blind Uber style)
  - Invoice generated (AccountingV2)
  - Webhooks outbound to B2B subscribers
```

---

## Key Architectural Decisions

See `docs/decisions/` for full ADRs. Summary:

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Framework | Laravel 11 + Livewire 3 | Full-stack SSR with reactive components, no SPA overhead for admin/B2B |
| Mobile | React Native + Expo (monorepo /mobile) | Native performance for B2C terrain flows; ADR 2026-05-24 |
| Realtime | Laravel Reverb (Pusher protocol) | Self-hosted, no external dependency for websockets |
| Auth | Laravel Sanctum | SPA + mobile token auth, simple and well-tested |
| Queue | Redis + Laravel Horizon | Reliable async for payments, notifications, webhooks |
| Payments | Stripe | PaymentIntents pre-auth model, Connect for provider payouts |
| Search | MySQL LIKE + Haversine | Sufficient for current scale; Typesense/Meilisearch if >500k records |
