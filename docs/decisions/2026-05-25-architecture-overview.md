# ADR — Architecture Overview

**Date:** 2026-05-25
**Status:** Active

## Stack
- **Backend:** Laravel 11 (PHP 8.2+), Sanctum auth, Stripe payments, Reverb WebSocket
- **Web:** Livewire 3 + Blade + Tailwind CSS 3
- **Mobile Client:** React Native (Expo SDK 56), TypeScript 6
- **Mobile Provider:** React Native (Expo SDK 56), TypeScript 6
- **DB:** SQLite (dev) / MySQL 8 (prod)
- **Queue:** Redis (prod) / sync (dev)
- **Cache:** Redis (prod) / file (dev)
- **Real-time:** Laravel Reverb (WebSocket)
- **Payments:** Stripe (PaymentIntent + Connect)
- **Push:** FCM (Android) + APNs (iOS) via expo-notifications
- **CI/CD:** GitHub Actions + EAS Build

## Key Decisions
1. **Monorepo** — Laravel + 2 RN apps in single repo for atomic changes
2. **API-first** — Mobile consumes REST API; Web uses Livewire (server-side)
3. **Sanctum tokens** (90-day, rotation grace 5min) — no OAuth complexity
4. **Stripe Connect Express** — providers get paid directly, platform takes commission
5. **Reverb over Pusher** — self-hosted, no per-message cost
6. **Expo over bare RN** — OTA updates, EAS Build, faster iteration

## Module Structure
50+ domain modules, each with: Service, Controller, Model, Migration, Test.
No bounded contexts yet — monolith. Extract to packages when team grows.

## Known Trade-offs
- Web + Mobile = double UI implementation (Livewire != React Native)
- 30% mobile code duplication (client/provider) — shared package planned
- 125 migrations including 20+ fixes — schema cleanup needed
