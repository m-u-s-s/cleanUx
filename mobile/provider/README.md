# CleanUx Provider — React Native (Expo)

Provider/employee field app for the CleanUx multi-service marketplace.

## Quick start

```bash
cd mobile/provider
npm install
npx expo start
```

## Architecture

```
src/
├── api/          # Axios client (shared from client)
├── auth/         # AuthProvider for provider role
├── presence/     # 4-state heartbeat (online/busy/break/offline)
├── missions/     # Dispatch inbox + accept/decline + lifecycle
├── tracking/     # GPS sender (expo-location) + live position push
├── inspection/   # Checklist + quality inspection
├── earnings/     # Wallet + transactions + Stripe Connect
├── chat/         # Threads + messages + Reverb live
├── notifications/# List + mark read
├── push/         # Device token registration
├── realtime/     # Pusher/Reverb client
├── storage/      # expo-secure-store wrapper
├── sentry/       # Crash reporting
├── theme/        # Design tokens (shared from client)
├── ui/           # 13 reusable components (shared from client)
├── navigation/   # Stack + tabs (Dashboard/Missions/Earnings/Profile)
├── screens/      # All screen components
└── config/       # Environment config
```

## Screens

- **Dashboard** — greeting + PresenceToggle + KPIs
- **Missions** — inbox (accept/decline) + detail + field page
- **Mission Field** — checklist + GPS tracking + complete action
- **Earnings** — wallet balance + transactions + Stripe Connect onboard
- **Availability** — weekly slots + iCal export
- **Badges** — unlocked/locked grid
- **KYC** — verification status + start
- **Disputes** — litige list
- **Ratings** — received reviews
- **Chat** — threads + messages (Reverb live)
- **Notifications** — list + mark read
- **Profile** — quick access to all features
- **Onboarding** — multi-step wizard
- **Login** — auth screen
