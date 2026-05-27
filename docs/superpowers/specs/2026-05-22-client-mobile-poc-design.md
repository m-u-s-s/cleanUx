# POC A · Client Mobile — Adaptive Design System

**Date**: 2026-05-22
**Owner**: Mohamed
**Branch cible**: `feat/client-mobile-poc`
**Durée estimée**: 2 semaines
**Statut**: Design validé (brainstorming), à raffiner en plan d'implémentation

---

## 1. Objectif

Prouver la viabilité d'un **design system adaptive multi-mode** (mode clair planifié ↔ mode sombre urgent) sur le client mobile CleanUx, en mode pilote sur 2 écrans critiques avant d'étendre au reste de l'OS.

**Critère de succès**: à la fin de 2 semaines, on a livré 2 écrans (Home + Mission Live), 8-10 composants atomiques réutilisables, des design tokens, et l'adaptive switch fonctionnel. Le rendu sur device Capacitor (iOS/Android) est fluide et "Linear/Apple premium". Si OK → on étend au reste du client mobile (sprint suivant).

---

## 2. Scope

### Inclus
- **V1 — Client Home (mode clair)** : hero "Prochain RDV" + quick actions + status cards (Fidélité/Crédits/Parrainage) + services tiles + bottom nav 5 items + FAB urgence
- **V3 — Mission Live Tracking (mode sombre)** : map live + ETA glass card + timeline 5 étapes + provider info + bouton appel + QR scan CTA premium
- **Adaptive switch logic** : déclencheurs auto + manuel
- **Design tokens** complets (clair + sombre)
- **Coexistence ancien/nouveau** via feature flag Laravel Pennant

### Hors scope (phase 2+)
- **V2 — Home map-first immersive** (à planifier après validation V1/V3)
- Refonte UX provider mobile (sprint 5-6 du roadmap global)
- Refonte UX admin desktop (sprint 9-12)
- Pages publiques + landing 3D Spline (sprint 7-8)
- Éclatement des 4 components >350L (sera intégré dans les sprints concernés)

---

## 3. Architecture

### Stack frontend hybride
- **Livewire v3** : reste le moteur pour toute la logique server-driven, formulaires, CRUD, dashboards admin. Aucune réécriture.
- **Vue 3 islands** : montés via Vite + mount manuel (`createApp(...).mount('#island-id')`) sur les écrans clients mobiles refondus (Home + Mission Live). Pas de package tiers (`livewire-vue`, Inertia) — on garde le contrôle total. Communication via window events (`window.dispatchEvent` côté Vue → `wire:listen` côté Livewire) et props JSON initiales injectées par Blade.
- **Tailwind v3** : config étendue avec les nouveaux design tokens (variables CSS pour adaptive).
- **Motion One** (ou GSAP léger) : transitions inter-modes + micro-interactions.
- **Capacitor** : wrapper natif, accès caméra (QR scan), géolocalisation (live tracking provider).

### Frontière Livewire ↔ Vue island
```
┌─────────────────────────────────────────────────┐
│  Route Laravel : /dashboard/client              │
│  ↓                                              │
│  Livewire ClientDashboard.php (load data)       │
│  ↓ passe props JSON                             │
│  <div id="vue-island-client-home"               │
│       :props="json_encode($data)"></div>        │
│  ↓ monté par Vite                               │
│  Vue 3 app : <ClientHomeIsland :props=".."/>    │
│  ↓ events bubblent vers Livewire                │
│  Livewire écoute dispatch('book-service')       │
└─────────────────────────────────────────────────┘
```

Les data restent server-driven (Livewire reste source de vérité). Vue gère uniquement la présentation et les interactions UI. Pas de state SPA.

### Coexistence ancien/nouveau
- Feature flag `client-mobile-v2` via Laravel Pennant.
- Si `Feature::active('client-mobile-v2', $user)` → mount Vue island ; sinon Blade legacy.
- Activable per-user pour beta-testing.
- Rollback instant en cas de bug critique.

---

## 4. Design Tokens

### Mode clair (par défaut · planifié · retention)
```css
--bg:        #fafaf7;   /* canvas */
--surface:   #ffffff;   /* cards */
--surface-2: #f4f1ff;   /* gradient hero */
--text:      #0a0a0f;
--text-2:    #6a6a78;   /* secondary */
--text-3:    #8a8a98;   /* tertiary */
--primary:   #6366f1;   /* indigo */
--accent:    #a78bfa;
--urgent:    #ef4444;
--success:   #10b981;
--border:    rgba(0,0,0,0.04);
--shadow-card: 0 1px 3px rgba(99,102,241,0.06), 0 12px 32px -8px rgba(99,102,241,0.18);
```

### Mode sombre (mission active · urgent · nuit)
```css
--bg:        #0a0a0f;   /* canvas profond */
--surface:   #1a1a25;   /* cards */
--surface-2: #14141f;
--text:      #fafaf7;
--text-2:    #a8a8b8;
--text-3:    #6a6a78;
--primary:   #818cf8;   /* indigo plus clair pour contraste */
--accent:    #c4b5fd;
--urgent:    #ef4444;
--success:   #10b981;
--border:    rgba(255,255,255,0.06);
--shadow-card: 0 12px 32px rgba(0,0,0,0.4);
```

### Typo
- **Display** : SF Pro Display / Inter Variable, weight 800, letter-spacing -0.8px
- **Body** : Inter Variable, weight 400-600
- **Mono** : JetBrains Mono (codes QR, prix, IDs)

### Motion
- Transitions inter-modes : `cubic-bezier(0.32, 0.72, 0, 1)` (Apple ease), durée 380ms
- Spring physics pour cards (haptic feel) : `Motion.spring({ stiffness: 280, damping: 32 })`
- Pulse urgence : 1.6s infinite (déjà mockupé)
- Pas d'animation > 800ms (évite la sensation de lenteur mobile)

---

## 5. Composants Atomiques

8 composants Vue 3 SFC à créer dans `resources/js/components/client/`:

| Composant | Rôle | Réutilisé par |
|---|---|---|
| `AdaptiveHero.vue` | Card hero adaptive (clair gradient indigo / sombre glass) | Home + Mission Live |
| `QuickActionGrid.vue` | Grid 4 items avec icône + label | Home |
| `StatusCardScroller.vue` | Row horizontale swipable (Fidélité, Crédits, Parrainage) | Home + autres dashboards futurs |
| `ServiceTile.vue` | Tile métier (emoji + label) | Home + browse providers futur |
| `FabUrgence.vue` | Floating Action Button rouge thumb-zone | Home + écrans nav principaux |
| `BottomNav.vue` | Nav 5 items blur backdrop | Toutes les pages client mobile |
| `BottomActionSheet.vue` | Sheet glissante bas avec handle | Mission Live + futures fiches |
| `EtaGlassCard.vue` | Card glassmorphic ETA + timeline | Mission Live |
| `StatusTimeline.vue` | 5 steps horizontal avec dots | Mission Live + tracking historique |
| `QrScanCta.vue` | CTA gradient haptic pour scan caméra | Mission Live |

Composants atomiques génériques (déjà partiellement existants Blade) :
- `<Avatar>`, `<Tag>`, `<BtnPrimary>`, `<BtnSecondary>`, `<PulseDot>` — wrappés en Vue avec props adaptive.

---

## 6. Adaptive Switch Logic

### Déclencheurs automatiques (mode sombre activé si)
1. **Mission en cours** : booking avec `status IN ('en_route','arrived','in_mission')` → sombre auto pendant toute la durée
2. **Urgence déclenchée** : booking créé avec `priority='urgent'` ET pas encore complété → sombre
3. **Contexte nuit + service urgent** : `now()` entre 21h et 6h ET service dans liste {serrurier, plombier, vitrier} → sombre

### Déclencheur manuel
- Toggle pill discret en top bar (icône lune/soleil) — uniquement quand aucun trigger auto actif
- Persisté côté user (storage exact à confirmer en Open Question #1 — colonne dédiée ou JSON `user_settings`) — défaut `auto`

### Implémentation
```php
// app/Services/Theme/AdaptiveThemeResolver.php
public function resolveForUser(User $user): string
{
    $pref = $user->theme_preference ?? 'auto';
    if ($pref !== 'auto') return $pref;

    if ($user->hasActiveMission()) return 'dark';
    if ($user->hasUrgentBookingPending()) return 'dark';
    if ($this->isNightWithUrgentServiceContext()) return 'dark';

    return 'light';
}
```

Passé en prop initiale au Vue island. Re-evaluation à chaque mount + sur changement d'état mission (Reverb event).

### Transitions visuelles
- Cross-fade 380ms via CSS variables (toutes les couleurs sont des `var(--...)` → root class switch suffit)
- View Transitions API si supportée (Chrome/Safari mobile recent)
- Haptic feedback Capacitor au switch (impact 'light')

---

## 7. Data Flow

### Home (V1)
```
ClientDashboard.php (Livewire)
  → loadDashboardData() : prochainRdv, loyaltyPoints, crédits, parrainages, servicesNearby
  → render(view) → blade contient <div id="client-home-island" :props="...">
  → Vue island mount avec props
  → User taps quick action → emit Livewire event 'book-service'
  → Livewire handler dispatch booking flow
```

### Mission Live (V3)
```
MissionLiveTracking.php (Livewire) - déjà existant, refondu
  → loadMission(id) : mission, provider, eta, tripTrackingSession
  → Reverb subscribe channel 'mission.{id}'
  → Events MissionLivePosition / MissionLiveEta poussés
  → Vue island re-render via prop update
  → User tap "QR scan" → ouvre Capacitor camera plugin
  → scan → POST /api/missions/{id}/qr-validate
  → Livewire push event UI confirmation
```

### Reverb push (remplace `wire:poll` en usage normal)
Le live tracking n'utilise PLUS `wire:poll.10s` comme mécanisme principal. Tout passe par Reverb channels :
- `mission.{id}` : position provider, ETA, status changes
- `user.{id}.notifications` : push notifications inline

`wire:poll.30s` reste disponible UNIQUEMENT comme fallback automatique si la connexion WebSocket Reverb tombe (voir Section 8 Error Handling).

---

## 8. Error Handling

### Vue island fail (JS bundle non chargé / erreur)
- Fallback automatique sur Blade legacy via `@if(!Feature::active('client-mobile-v2'))`
- Console error reporté à Sentry avec contexte user/route
- L'utilisateur ne voit jamais d'écran blanc

### Reverb disconnect
- Banner discret "Connexion temps-réel rétablie..." quand reconnect
- Fallback `wire:poll.30s` activé automatiquement pendant déconnexion
- Buffer events 60s côté serveur (déjà supporté par Reverb)

### Capacitor camera unavailable
- Modal alternative : saisie manuelle du code 6 chiffres (chemin déjà supporté)
- Bouton de demande de permission caméra si denied

### Adaptive switch fail
- Si `AdaptiveThemeResolver` jette une exception → default `light` + log Sentry
- Le switch est non-bloquant : le user voit toujours quelque chose

---

## 9. Testing

### Unit tests Vue
- Vitest + Vue Test Utils
- Tester chaque composant atomique en mode clair ET sombre (props `theme: 'light'|'dark'`)
- Snapshot tests pour layouts critiques

### Tests Livewire (PHPUnit existant)
- Tester que `ClientDashboard.php` passe les bonnes props JSON au Vue island
- Tester `AdaptiveThemeResolver::resolveForUser()` avec 8 scenarios (chaque trigger + combinaisons)

### Tests E2E (Playwright)
- Scenario 1 : User loggé voit V1 si feature flag activé
- Scenario 2 : Switch clair → sombre quand mission devient active
- Scenario 3 : QR scan flow complet (mock caméra)
- Scenario 4 : Fallback Blade si JS bundle échoue
- Run sur viewport mobile (iPhone 13 Pro 390x844)

### Test perf mobile (Lighthouse mobile)
- FCP < 1.5s sur 4G simulé
- LCP < 2.5s
- Bundle Vue < 80KB gzip (pas de Three.js dans ce POC)
- Pas de layout shifts (CLS < 0.1)

### Test sur device réel
- iPhone (iOS 17+) via Capacitor
- Android (Samsung Galaxy A series — entry level) — la cible la plus difficile

---

## 10. Coexistence & Migration

### Feature flag
```php
// dans AppServiceProvider ou FeatureServiceProvider
Feature::define('client-mobile-v2', fn ($user) =>
    $user->id && in_array($user->id, config('beta.client_mobile_v2_users', []))
);
```

### Rollout
1. **Sem 1 jour 1-5** : dev complet, tests en local
2. **Sem 2 jour 1-3** : beta interne (5-10 users beta)
3. **Sem 2 jour 4-5** : feedback + ajustements
4. Phase suivante : rollout progressif 10% → 50% → 100% en 2 semaines après le POC

### Migration des features actuellement cachées
Le POC EXPOSE explicitement les features actuellement invisibles depuis le dashboard client :
- **Fidélité** (StatusCardScroller) → route `/fidelite/recompenses` accessible en 1 tap
- **Crédits** (StatusCardScroller) → route `/finance` 
- **Parrainage** (StatusCardScroller) → route `/parrainage`
- **Urgent** (FabUrgence + QuickActionGrid) → ouvre flow `PrendreRendezVous` avec `priority=urgent` pré-rempli

---

## 11. Deliverables (fin de POC)

À la fin des 2 semaines, le repo doit contenir :

- [ ] `resources/js/components/client/` avec 8-10 composants Vue SFC + tests Vitest
- [ ] `resources/css/tokens.css` avec design tokens clair + sombre + transitions
- [ ] `tailwind.config.js` étendu (semantic colors via CSS variables)
- [ ] `app/Services/Theme/AdaptiveThemeResolver.php` + tests unitaires
- [ ] `app/Livewire/ClientDashboard.php` refondu (passe props au Vue island)
- [ ] `app/Livewire/Client/MissionLiveTracking.php` refondu
- [ ] `resources/views/livewire/client-dashboard.blade.php` : mount point Vue
- [ ] Feature flag `client-mobile-v2` défini
- [ ] 2 écrans démontrables sur device réel iOS + Android
- [ ] Tests Playwright pour les 4 scenarios principaux
- [ ] Doc dans `docs/design-system/` : tokens, composants, usage
- [ ] Démo vidéo 60s du switch adaptive sur device réel

---

## 12. Risks & Mitigations

| Risque | Impact | Mitigation |
|---|---|---|
| Bundle Vue trop lourd (>150KB) | Mobile slow | Tree-shaking strict, lazy mount des islands non visibles, code splitting |
| Switch mode pendant interaction casse l'UX | User confusion | Transitions cubic-bezier 380ms, lock pendant input actif |
| Reverb disconnect en zone urbaine sous-sol | Pas de live tracking | Fallback `wire:poll.30s` + banner discret |
| Capacitor camera permission refusée | QR scan bloqué | Modal manuelle 6 chiffres (chemin déjà existant) |
| Designers non-tech ne peuvent pas tweaker tokens | Goulet d'étranglement | Tokens en `tokens.css` simple, doc claire |
| Vue island casse en cas de pré-rendu SSR Livewire | Écran blanc | Fallback Blade auto via feature flag |
| Pas de testeur mobile dispo | Bugs device-spécifiques | TestFlight + Android Internal Track dès la sem 1 |

---

## 13. Out-of-scope (à clarifier en phase 2)

- **V2 home map-first** : nécessite décision sur le mécanisme de switch V1 ↔ V2 (swipe ? toggle ?)
- **Mode "compagnon"** B2B multi-sites (interface adaptée organisations) : extension future de V1
- **3D Spline pages publiques** : sprint 7-8
- **Voice search "Que cherches-tu ?"** : nécessite intégration Anthropic API + permission micro
- **Personnalisation par utilisateur** (couleurs custom, layouts) : pas dans le scope MVP

---

## 14. Open Questions (à confirmer pendant l'implémentation)

1. **Persistance préférence thème** : ajouter colonne `users.theme_preference` ou utiliser `user_settings` JSON existant ?
2. **Bundle Vue séparé** par rôle (client / provider / admin) ou un seul bundle partagé ?
3. **Migration progressive** : on garde les routes legacy `/dashboard` ET on ajoute `/dashboard/v2` en parallèle pendant la transition, ou on remplace en place avec feature flag ?
4. **Test device** : tu as accès à un iPhone + Android réel ? Sinon BrowserStack pour les tests cross-device ?
5. **Beta testeurs** : 5-10 users beta — qui ? Tes proches ou des clients existants opt-in ?

Ces questions sont à trancher au moment du plan d'implémentation détaillé, pas bloquantes pour le design.
