# Brio Client — React Native (Expo)

Mobile client app for the Brio multi-service marketplace.

## Quick start

```bash
cd mobile/client
npm install
npx expo start
```

## Environment

Copy `.env.example` to `.env` and fill in:

| Variable | Required | Description |
|---|---|---|
| `EXPO_PUBLIC_API_URL` | Yes | Laravel API base URL |
| `EXPO_PUBLIC_STRIPE_PUBLISHABLE_KEY` | Yes | Stripe publishable key (pk_test_* or pk_live_*) |
| `EXPO_PUBLIC_SENTRY_DSN` | No | Sentry DSN for crash reporting |

## Build

```bash
# Development (simulator)
eas build --profile development --platform ios

# Preview (internal distribution)
eas build --profile preview --platform all

# Production
eas build --profile production --platform all
```

## Submit

```bash
eas submit --profile production --platform ios
eas submit --profile production --platform android
```

## Test

```bash
npm test           # Jest
npm run typecheck  # TypeScript
```

## Architecture

```
src/
├── api/          # Axios client + interceptors + types
├── auth/         # AuthProvider + TanStack Query hooks
├── booking/      # Booking state + wizard hooks
├── chat/         # Chat threads + messages + Reverb live
├── loyalty/      # Loyalty account + rewards
├── notifications/# Notification list + mark read
├── payment/      # Stripe hooks (PaymentSheet)
├── phone/        # OTP verification
├── push/         # Expo push token registration
├── ratings/      # Submit rating mutation
├── realtime/     # Pusher/Reverb client + useChannel
├── storage/      # expo-secure-store wrapper
├── sentry/       # Crash reporting init
├── theme/        # Design tokens (colors, spacing, typography, etc.)
├── tracking/     # GPS tracking + live position
├── navigation/   # React Navigation (stack + tabs + booking wizard)
├── screens/      # All screen components
├── ui/           # Reusable UI components (13 atoms + patterns)
└── config/       # Environment validation
```

## Screens (Phase 1)

- **Home** — greeting + book CTA
- **Explore** — browse providers with filters
- **Bookings** — list with status badges + pull-to-refresh
- **Booking Detail** — status + actions (track/QR start/end/pay/rate/tip)
- **Booking Wizard** — 5-step flow (service → details → address → scheduling → confirm)
- **Mission Tracking** — live map with provider trail + ETA
- **QR Scan** — camera barcode scanner for mission start/end
- **Payment Checkout** — Stripe PaymentSheet
- **Saved Payment Methods** — add/delete cards
- **Chat List + Chat** — threads + messages with Reverb live
- **Notifications** — list with mark-all-read
- **Loyalty** — tier + points + rewards catalogue
- **Referral** — share code via native Share
- **AI Quote** — photo upload for AI estimation
- **Ratings** — 5-dimension star form
- **Disputes** — litige list
- **GDPR** — data export + erasure
- **Profile Edit** — name + phone
- **Tips** — 3 preset percentages
- **NPS** — 0-10 score picker
- **Login** — placeholder (Sprint 2+ wired auth)
- **Profile** — quick access to all features

## CI/CD

- **PR checks**: TypeScript + Jest run automatically on PRs touching `mobile/`
- **Manual build**: Go to Actions → "Mobile EAS Build" → Run workflow (choose app/profile/platform)
- **Tag build**: Push `mobile-client-v1.0.1` tag to trigger automatic preview build
- **Store submit**: Go to Actions → "Mobile Store Submit" → Run workflow

### Required secrets

Set these in GitHub repo Settings → Secrets → Actions:
- `EXPO_TOKEN` — generate at https://expo.dev/settings/access-tokens
