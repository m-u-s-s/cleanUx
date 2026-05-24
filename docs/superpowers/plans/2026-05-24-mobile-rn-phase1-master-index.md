# CleanUx Mobile React Native — Phase 1 Client : Plan Maître (Index)

> **For agentic workers:** Ce document est un **index**, pas un plan exécutable. Pour chaque sprint, ouvre le plan détaillé correspondant et exécute-le via `superpowers:subagent-driven-development`.

**Goal Phase 1:** Livrer une app React Native/Expo `mobile/client` qui couvre l'intégralité du parcours client CleanUx (booking → mission → paiement → suivi → fidélité → support) avec parité fonctionnelle de l'app web actuelle.

**Architecture Phase 1:** Monorepo `/mobile/client` à la racine du projet Laravel (workspace npm/pnpm). Expo SDK + EAS Build/Submit. React Navigation v7. TanStack Query pour cache API. Reverb client mobile pour WebSocket. expo-secure-store pour token Sanctum. Stripe React Native pour paiement. expo-camera pour QR. react-native-maps ou MapLibre pour tracking live. Theming porté 1:1 depuis `tailwind.config.js` + `resources/css/app.css`.

**Tech Stack:** React Native 0.7x via Expo SDK ~51, TypeScript strict, React 18, Reanimated 3, React Native Gesture Handler, TanStack Query, Axios, Pusher-JS (Reverb), @stripe/stripe-react-native, expo-notifications, expo-image-picker, expo-camera, expo-location, expo-secure-store, expo-blur, react-native-svg + heroicons.

**Décision stratégique (mémorisée) :** voir `memory/mobile_rn_strategy.md` — RN/Expo monorepo `/mobile`, Phase 1 client + Phase 2 provider, admin reste 100% web Livewire.

---

## Découpage en 11 sprints indépendants

Chaque sprint produit un livrable testable seul. Les plans détaillés (un fichier par sprint) sont écrits **just-in-time** au début de chaque sprint, car le contexte se précise au fur et à mesure.

| # | Sprint | Durée | Plan détaillé | Statut |
|---|---|---|---|---|
| **0** | API mobile-readiness (bloquant) | 3-5j | [`2026-05-24-mobile-rn-sprint-0-api-mobile-readiness.md`](./2026-05-24-mobile-rn-sprint-0-api-mobile-readiness.md) | ✅ Écrit, prêt à exécuter |
| 1 | Monorepo + Expo bootstrap | 1 sem | À écrire au début du Sprint 1 | ⏳ Bloqué par Sprint 0 |
| 2 | Auth + API client + Reverb WS | 1 sem | À écrire | ⏳ Bloqué par Sprint 1 |
| 3 | Design system RN library | 1.5 sem | À écrire | ⏳ Bloqué par Sprint 2 |
| 4-5 | Booking flow + Browse providers | 2 sem | À écrire (peut-être 2 plans séparés) | ⏳ |
| 6 | Live tracking + QR scan | 1 sem | À écrire | ⏳ |
| 7 | Payment Stripe RN | 1 sem | À écrire | ⏳ |
| 8 | Chat + Push + Notifications | 1 sem | À écrire | ⏳ |
| 9 | Ratings + Loyalty + Referral + AI Quote | 1 sem | À écrire | ⏳ |
| 10 | Disputes + GDPR + Profile + Tips + Insurance + NPS | 1 sem | À écrire | ⏳ |
| 11 | EAS Build + Submit + Hardening | 3-5j | À écrire | ⏳ |

---

## Sprint 0 — API Mobile-Readiness

**Pourquoi bloquant :** L'app RN ne peut pas démarrer cleanly sans :
1. Token refresh avec grace period (sinon déco sur erreur réseau)
2. Format JSON erreur unifié (sinon UI mobile cassée sur 401/422/429)
3. Endpoints Stripe Connect provider (mention en mémoire mais code absent côté API)
4. Reverb mobile auth flow (sinon WS impossible)
5. Capacitor retiré (sinon ambiguïté architecture)

**Livrable :** PR Laravel mergeable, +5 endpoints, format erreur unifié, ~15 tests Feature ajoutés, Capacitor archivé.

**Détails :** [`2026-05-24-mobile-rn-sprint-0-api-mobile-readiness.md`](./2026-05-24-mobile-rn-sprint-0-api-mobile-readiness.md)

---

## Sprint 1 — Monorepo + Expo bootstrap (preview)

**Livrable :** `/mobile/client` initialisé, Expo SDK installé, EAS configuré (dev/preview/production), TypeScript strict, React Navigation v7 (stack + tabs + modal), `theme.ts` portant les tokens depuis `tailwind.config.js`, expo-secure-store wrappé. Pas encore d'écran fonctionnel — juste un Hello World qui build sur iOS + Android.

**Préalables :** Sprint 0 mergé.

**Étapes principales :**
- Init monorepo (pnpm workspaces ou npm workspaces) avec `apps/` (à décider) ou simplement `/mobile/client` à la racine
- `npx create-expo-app mobile/client --template blank-typescript`
- Configurer `eas.json` profiles
- Installer dépendances core
- Setup React Navigation
- Porter design tokens (palette, radius, easing, fonts)
- CI : ajouter `mobile/client` au GitHub Actions (lint + type-check)
- Sentry RN basique

---

## Sprint 2 — Auth + API client + Reverb (preview)

**Livrable :** Login/Register/Logout/Refresh fonctionnels via API CleanUx, token stocké securément, intercepteurs axios (auth header, refresh-on-401, error-mapping), TanStack Query configuré, Reverb client connecté + auth via Bearer pour channels privés, OTP phone verification.

**Préalables :** Sprint 0 + Sprint 1.

---

## Sprint 3 — Design system library RN (preview)

**Livrable :** `mobile/client/src/ui/` complet avec tous les atoms (Avatar, Tag, PulseDot, Btn*, Badge, KPI Card, Stat, Icon avec heroicons), mobile patterns (BottomNav, AdaptiveHero, QuickActionGrid, FabUrgence, BottomActionSheet, EtaGlassCard avec expo-blur, StatusTimeline, QrScanCta), animations Reanimated 3 (fadeUp, softPulse, shimmer), dark mode auto via Appearance API, safe area handling, Storybook RN ou Expo demo screen.

---

## Sprint 4-5 — Booking flow + Browse providers (preview)

**Livrable :** Parcours booking 5 étapes (service → details → coordinates → scheduling → confirmation) fonctionnel end-to-end, BrowseProviders avec filtres (rating/prix/zone/trade) consommant `/api/search/providers`, AddressAutocompleteService côté mobile (GeocodingService backend), Multi-trades bundle manager, ASAP booking, recurrence templates.

---

## Sprint 6 — Live tracking + QR scan (preview)

**Livrable :** Écran "Ma mission en cours" avec carte (react-native-maps ou MapLibre), trail du provider en temps réel via Reverb (events MissionLivePosition + MissionLiveEta), QR scan avec expo-camera pour valider arrivée + fin, geofence client-side approximatif.

---

## Sprint 7 — Payment Stripe RN (preview)

**Livrable :** `@stripe/stripe-react-native` intégré, SetupIntent pour ajouter une carte, PaymentIntent pré-autorisé au booking + capture après fin de mission, SavedPaymentMethods, ApplePay + GooglePay activés, gestion 3DS native.

---

## Sprint 8 — Chat + Push + Notifications (preview)

**Livrable :** Chat v2 RN (threads, messages, attachments avec validation MIME, modération auto), WS live messages, expo-notifications avec FCM + APNs, device token enregistré via `/api/client/devices/register`, opt-in matrix (channel × category) UI mobile, notifications center.

---

## Sprint 9 — Ratings + Loyalty + Referral + AI Quote (preview)

**Livrable :** Blind reveal ratings (formulaire 5-dim après mission), LoyaltyDashboard + redemption marketplace, Referral program (codes promo + parrainage + share link OS), AI Quote Photo (expo-image-picker → upload `/api/client/ai-quote/photo` → Claude Vision result).

---

## Sprint 10 — Disputes + GDPR + Profile + Tips + Insurance + NPS (preview)

**Livrable :** Reste des écrans client : LitigesClient (workflow disputes), GDPR self-service (export + erasure), ProfileEdit avec multi-sites (sociétés), Tips (3 presets + custom), Insurance plans + claims, NPS Survey, Contracts e-signature, FavoriteEmployes (rebook 1-click), Calendrier interactif client.

---

## Sprint 11 — EAS Build + Submit + Hardening (preview)

**Livrable :** Builds EAS production iOS + Android, TestFlight + Play Console internal track, App Store + Play Store metadata (réutilise `docs/STORES_SUBMISSION_RUNBOOK.md`), Sentry RN configuré production, performance audit (Reanimated profiler + memory), crash reporting, bundle size optimization.

---

## Risques connus & mitigations

| Risque | Impact | Mitigation |
|---|---|---|
| Schéma DB fragile (17 fix migrations en mai, mémoire `audit_2026_05_21.md`) | Tests RN pourraient casser au merge backend | Tag schéma "Sprint 0 baseline" + freeze partiel migrations pendant développement RN |
| `customer_credits` désaligné (mémoire `customer_credits_schema_mismatch.md`) | Loyalty redemption RN pourrait planter | NE PAS écrire dans CustomerCredit::create depuis RN — passer par LoyaltyService |
| BelongsToTenant trait orphelin | Tenancy v2 pourrait ne pas filtrer correctement les requêtes mobile | Audit Sprint 2 du middleware tenancy mobile |
| Reverb scaling sous charge mobile | Live tracking + chat simultanés multi-users | Load test en Sprint 6, fallback polling si Reverb tombe |
| Coûts EAS + push provider | $30-100/mois | Budget vérifié avant Sprint 11 |
| Apple/Google review time (2-7j) | Décale go-live | Buffer 2 semaines après Sprint 11, soumission preview build dès Sprint 6 |

---

## Méta : pourquoi un index séparé des plans

Chaque sprint dure 1-2 semaines. Écrire 11 plans détaillés maintenant produirait du contenu spéculatif qui sera obsolète après Sprint 0 (decisions monorepo, choix bibliothèques cards/maps, format API revus). On écrit le plan détaillé au début de chaque sprint quand le contexte est frais.

**Workflow recommandé** par sprint :
1. Sprint 0 : exécuter le plan détaillé existant via `superpowers:subagent-driven-development`
2. Sprint N≥1 : utiliser `superpowers:writing-plans` pour produire `2026-XX-XX-mobile-rn-sprint-N-<topic>.md` puis exécuter
3. Mettre à jour ce master index avec le lien + statut après chaque sprint
