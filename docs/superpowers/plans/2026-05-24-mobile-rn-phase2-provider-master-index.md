# CleanUx Mobile React Native — Phase 2 Provider : Plan Maître

> **For agentic workers:** Index document. Pour chaque sprint, dispatchez les tasks via `superpowers:subagent-driven-development`.

**Goal Phase 2:** Livrer `mobile/provider/` — app terrain React Native/Expo pour les providers (indépendants + employés de sociétés). Parcours : onboarding → online/presence → recevoir dispatch → accepter → en route → arrivé → mission (QR + checklist) → fin (QR) → earnings → payouts.

**Architecture:** Même stack que Phase 1 client (Expo SDK 56, TypeScript 6, React Navigation 7, TanStack Query, Pusher/Reverb, expo-secure-store). Modules partagés copiés depuis `mobile/client/` (theme, ui, storage, api client). Modules spécifiques provider dans `src/`.

**Réutilisation depuis Phase 1 :**
- `src/theme/` — copie intégrale (mêmes tokens)
- `src/ui/` — copie intégrale (13 composants)
- `src/storage/` — copie (secureStore)
- `src/api/client.ts` + `src/api/types.ts` — copie (même backend, même intercepteurs)
- `src/realtime/` — copie (même Reverb)
- `src/sentry/` — copie
- `src/config/` — copie (env.ts)
- `src/ErrorBoundary.tsx` — copie

---

## Découpage en 8 sprints

| # | Sprint | Durée | Statut |
|---|---|---|---|
| P0 | Bootstrap provider (copie shared + scaffold) | 1j | ⏳ |
| P1 | Auth provider + Presence v2 (4-état heartbeat) | 2-3j | ⏳ |
| P2 | Mission dispatch (inbox + accept/decline + lifecycle) | 3-4j | ⏳ |
| P3 | Mission field (QR scan + checklists + trip tracking GPS sender) | 3-4j | ⏳ |
| P4 | Earnings + wallet + payouts | 2j | ⏳ |
| P5 | Availability + calendar + badges | 2j | ⏳ |
| P6 | Remaining (KYC, disputes, ratings, onboarding, fleet, chat, notifs) | 3-4j | ⏳ |
| P7 | EAS Build + hardening + README | 1j | ⏳ |

---

## API Provider (vérifié dans routes/api.php)

### Presence
- `POST /api/provider/presence/online`
- `POST /api/provider/presence/heartbeat`
- `POST /api/provider/presence-v2/heartbeat`

### Dispatch
- `GET /api/provider/assignments/inbox`
- `POST /api/provider/assignments/{id}/accept`
- `POST /api/provider/assignments/{id}/decline`

### Mission lifecycle
- `POST /api/provider/missions/{id}/start`
- `POST /api/provider/missions/{id}/arrive`
- `POST /api/provider/missions/{id}/complete`

### Trip tracking (provider sends GPS)
- `POST /api/provider/bookings/{id}/tracking/start`
- `POST /api/tracking/{session}/ping`
- `POST /api/tracking/{session}/in-mission`
- `POST /api/tracking/{session}/end`

### Live ETA/Position
- `POST /api/provider/missions/{id}/live/position`
- `POST /api/provider/missions/{id}/live/eta`

### Earnings
- `GET /api/provider/wallet/balance`
- `GET /api/provider/wallet/transactions`
- `POST /api/provider/wallet/withdraw`

### Badges
- `GET /api/provider/badges`
- `POST /api/provider/badges/evaluate`

### Availability
- `GET /api/provider/availability`
- `POST /api/provider/availability/slots`
- `GET /api/provider/availability/ical`

### Inspection/Quality
- `GET /api/provider/missions/{id}/inspections`
- `POST /api/provider/missions/{id}/inspections`

### Disputes
- `GET /api/provider/disputes`
- `POST /api/provider/disputes/{id}/respond`

### KYC
- `POST /api/provider/kyc/start`
- `GET /api/provider/kyc/status`

### Ratings
- `GET /api/provider/ratings/me`
- `POST /api/provider/bookings/{id}/rating`

### Onboarding
- `POST /api/provider/onboarding/start`
- `GET /api/provider/onboarding/progress`
- `POST /api/provider/onboarding/profile`
- `POST /api/provider/onboarding/documents`

### Stripe Connect (Sprint 0)
- `GET /api/provider/stripe-connect/status`
- `POST /api/provider/stripe-connect/onboard`
- `GET /api/provider/stripe-connect/payouts`
