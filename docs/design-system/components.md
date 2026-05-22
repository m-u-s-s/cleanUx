# Composants — Catalogue POC

Tous dans `resources/js/components/`.

## Atoms (réutilisables partout)

| Composant | Fichier | Props clés |
|---|---|---|
| `Avatar` | `atoms/Avatar.vue` | `name`, `size` |
| `Tag` | `atoms/Tag.vue` | `variant` (primary/urgent/success/neutral), slot |
| `PulseDot` | `atoms/PulseDot.vue` | `variant` (urgent/success/primary) |
| `BtnPrimary` | `atoms/BtnPrimary.vue` | `disabled`, `fullWidth`, slot, `@click` |
| `BtnSecondary` | `atoms/BtnSecondary.vue` | `disabled`, slot, `@click` |

## Client (métier mobile)

| Composant | Fichier | Props clés | Émet |
|---|---|---|---|
| `AdaptiveHero` | `client/AdaptiveHero.vue` | `eyebrow`, `title`, `meta`, `tags[]` | `primary-action`, `secondary-action` |
| `QuickActionGrid` | `client/QuickActionGrid.vue` | `actions[]` | `action(id)` |
| `ServiceTile` | `client/ServiceTile.vue` | `emoji`, `name` | `select(name)` |
| `StatusCardScroller` | `client/StatusCardScroller.vue` | `cards[]` | `select(id)` |
| `FabUrgence` | `client/FabUrgence.vue` | — | `trigger` |
| `BottomNav` | `client/BottomNav.vue` | `items[]`, `activeId` | `navigate(id)` |
| `BottomActionSheet` | `client/BottomActionSheet.vue` | slot | — |
| `EtaGlassCard` | `client/EtaGlassCard.vue` | `etaMinutes`, `distanceKm`, `providerName`, `steps[]`, `currentStep` | `dismiss` |
| `StatusTimeline` | `client/StatusTimeline.vue` | `steps[]`, `currentId` | — |
| `QrScanCta` | `client/QrScanCta.vue` | `title`, `subtitle`, `disabled` | `scan` |

## Communication Vue → Livewire

Les composants dispatchent des `window.CustomEvent` que le bridge `resources/js/livewire-bridge.ts` (Task 25) intercepte et forward via `Livewire.dispatch`.

Événements :
- `cleanux:client-action` — actions home (quick actions, navigation, services)
- `cleanux:mission-scan` — déclenche scan QR
- `cleanux:mission-call` — déclenche appel provider
