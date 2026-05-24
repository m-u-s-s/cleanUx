# ADR — React Native (Expo) remplace Capacitor pour le mobile

**Date:** 2026-05-24
**Statut:** Accepté
**Auteur:** m-u-s-s (avec analyse Claude Opus 4.7)

## Contexte

CleanUx avait une config Capacitor (livrée sprint 0-9, 2026-05-20) + un Client Mobile POC V2 Vue 3 islands hybride Livewire (livré 2026-05-23, feature flag `client-mobile-v2`). On souhaite à présent shipper une app mobile native sur les stores avec une UX fluide pour le client (booking, tracking, paiement) et plus tard pour le provider (terrain).

## Décision

Remplacer Capacitor par **React Native** via **Expo SDK + EAS Build/Submit**. Monorepo `/mobile/{client|provider}`. **Admin reste 100% web Livewire**.

Phasage :
- Phase 1 (3 mois) : `mobile/client` — booking, tracking, paiement, chat, ratings, fidélité, parrainage, etc.
- Phase 2 (3 mois) : `mobile/provider` — dispatch accept, QR start/end, earnings, presence, badges, fleet.
- Admin : pas de RN — desktop par usage réel.

## Conséquences

**Positives :**
- Perf native (60fps gestures, carte, listes virtualisées)
- Accès APIs natives plus large (push FCM/APNs, geoloc background, camera, biométrie)
- Review stores plus simple (vraie app vs webview)
- Écosystème RN mature + Expo OTA updates

**Négatives :**
- Codebase mobile dédiée à maintenir en parallèle du web
- Le Client Mobile POC V2 Vue (33 commits) ne se porte pas — UI à refaire en RN
- Risque de drift versions API entre web (Livewire) et mobile (RN) — mitigation : tests E2E API + Sprint 0 API mobile-readiness

## Alternatives considérées

- **Garder Capacitor** : webview reste limité (gestures, perf, accès natif) + review stores moins favorable.
- **Flutter** : écosystème plus jeune, équipe pas formée Dart.
- **PWA seule** : Apple notifications + install flow trop limités.

## Migration

Voir `docs/superpowers/plans/2026-05-24-mobile-rn-phase1-master-index.md`.

Sprint 0 (cette branche) ferme les gaps API bloquants (token refresh, JSON errors, Stripe Connect, Reverb mobile auth) + archive Capacitor.
