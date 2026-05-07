# Phase 8 — Mobile / PWA / Push notifications

> **Objectif** : transformer CleanUx en **app mobile installable** :
> - PWA installable depuis le navigateur (Android, iOS, desktop)
> - Service Worker pour mode offline + cache
> - Web Push notifications (alertes même app fermée)
> - Bottom navigation mobile pour clients
> - Toggle activation/désactivation push depuis le profil
>
> **Durée d'application** : 1h30.

---

## 1. Architecture livrée

```
public/
├── manifest.webmanifest              ← NEW (config PWA)
├── sw.js                             ← NEW (service worker complet)
└── offline.html                      ← NEW (fallback hors-ligne)

app/Models/
└── PushSubscription.php              ← NEW

app/Notifications/
├── BookingReminderNotification.php   ← NEW (exemple webpush complet)
└── Channels/
    └── WebPushChannel.php            ← NEW (canal Laravel)

app/Services/Push/
└── WebPushSender.php                 ← NEW (wrapper minishlink)

app/Http/Controllers/Push/
└── PushSubscriptionController.php    ← NEW (subscribe/unsubscribe/test/key)

app/Console/Commands/
└── GenerateVapidKeysCommand.php      ← NEW (php artisan webpush:vapid)

resources/js/
├── pwa.js                            ← NEW (SW registration + install)
└── push-notifications.js             ← NEW (subscribe/unsubscribe API)

resources/views/components/
├── mobile-bottom-nav.blade.php       ← NEW (nav mobile fixe en bas)
├── pwa-install-prompt.blade.php      ← NEW (bandeau install Android+iOS)
└── push-toggle.blade.php             ← NEW (UI activation/désactivation)

database/migrations/
└── 2026_05_07_160001_create_push_subscriptions.php

tests/Feature/Push/
└── Phase8Test.php  (12 tests)

patches/
└── 01_integration.md
```

## 2. Comment ça fonctionne — vue d'ensemble

### PWA (Progressive Web App)

```
[User] visite https://cleanux.com sur mobile/desktop
    ↓
[Browser] charge manifest.webmanifest → détecte que c'est une PWA installable
    ↓
[Layout] enregistre /sw.js (resources/js/pwa.js)
    ↓
[Service Worker] pré-cache /, /offline.html, /manifest.webmanifest, /favicon.ico
    ↓
[User] clique sur le bandeau "Installer CleanUx" (composant pwa-install-prompt)
    ↓
[Chrome/Edge/Android] popup native d'installation → app installée comme une app native
[iOS Safari] instructions manuelles affichées (Apple ne supporte pas le prompt natif)
    ↓
[App standalone] mode plein écran sans barre d'URL, raccourci sur l'écran d'accueil
```

### Web Push

```
[User] dans Profil → clique "Activer les notifications" (composant push-toggle)
    ↓
[JS push-notifications.js] requestPermission() → popup OS native
    ↓
[Browser] crée une PushSubscription locale (endpoint, p256dh, auth)
    ↓
[JS] POST /push/subscribe avec ces clés → stocké dans push_subscriptions table
    ↓
─────────────────────────────────────────────────────────────────────────────
plus tard…
    ↓
[Server] BookingReminderNotification dispatched
    ↓
[Laravel] via() retourne ['database', 'mail', WebPushChannel::class]
    ↓
[WebPushChannel] appelle WebPushSender->sendToUser($user, $payload)
    ↓
[WebPushSender] récupère subscriptions actives + signe avec VAPID + envoie via minishlink
    ↓
[Push service Mozilla/Google/Apple] → relais → device de l'utilisateur
    ↓
[Service Worker] event 'push' → showNotification(title, options)
    ↓
[OS] affiche la notif système
    ↓
[User] clique → SW event 'notificationclick' → focus tab existant ou ouvre URL
```

## 3. Stratégie de cache (Service Worker)

| Ressource | Stratégie | Comportement |
|---|---|---|
| **HTML pages** | network-first | Toujours frais quand connecté, fallback cache → fallback offline.html |
| **CSS/JS/fonts/images** | cache-first | Cache hit immédiat, refresh en arrière-plan |
| **Livewire / API / Reverb / streaming** | bypass | Jamais cachées (live data) |
| **/login, /logout** | bypass | Jamais cachées (state-sensitive) |

`CACHE_VERSION = 'cleanux-v1'` — bump pour invalider tout après une grosse MAJ.

## 4. Sécurité

- **HTTPS obligatoire** en prod (sauf localhost). Sans HTTPS, `serviceWorker.register()` lève une exception.
- **VAPID** : authentification cryptographique du serveur d'envoi. Sans clés VAPID configurées, le service `WebPushSender` log un warning et ne fait rien.
- **Scoping par user** : un user ne peut désinscrire QUE ses propres endpoints (test 12).
- **CSRF** : toutes les routes `/push/*` sont protégées par CSRF + auth.
- **Endpoints invalides** : auto-désactivés après HTTP 404/410 ou 5 échecs.

## 5. Application — étapes

### Étape 1 — Décompresser

```bash
unzip cleanux-phase8.zip
rsync -av cleanux-phase8/app/         app/
rsync -av cleanux-phase8/database/    database/
rsync -av cleanux-phase8/public/      public/
rsync -av cleanux-phase8/resources/   resources/
rsync -av cleanux-phase8/tests/       tests/
```

### Étape 2 — Composer + migrate + clés VAPID

```bash
composer require minishlink/web-push
php artisan migrate
php artisan webpush:vapid
```

Copier les 3 lignes affichées dans `.env`.

### Étape 3 — Patches manuels

Voir `patches/01_integration.md` pour le détail. En résumé :

1. `config/services.php` : section `webpush` (3 clés VAPID)
2. `routes/web.php` : 4 endpoints `/push/*`
3. `resources/js/app.js` : 2 imports
4. **Layouts** (app, client-company, provider-company, guest) :
   - `<link rel="manifest">`, `<meta theme-color>`, `<meta apple-...>`, `<link apple-touch-icon>` dans le `<head>`
   - `<x-mobile-bottom-nav />` + `<x-pwa-install-prompt />` avant `</body>`
5. **Page profil** : `<x-push-toggle />`
6. **Générer les icônes PWA** dans `public/icons/` (voir patches §9)

### Étape 4 — Build

```bash
npm run build
```

### Étape 5 — Tests

```bash
php artisan test --filter=Phase8Test
```

12 tests doivent passer.

### Étape 6 — Démo runtime

1. **Service worker actif** : DevTools → Application → Service Workers → "activated and is running"
2. **Manifest valide** : DevTools → Application → Manifest → toutes les icônes vertes
3. **Installer la PWA** :
   - Chrome desktop : icône ⊕ à droite de l'URL
   - Android : menu → "Installer l'app"
   - iOS Safari : Partager → Sur l'écran d'accueil
4. **Activer les notifs** : Profil → toggle → permission OS → "Test"
5. **Notification reçue** sous 1-2 s
6. **Mode offline** : DevTools → Network → Offline → naviguer → page déjà visitée OK, page non visitée → offline.html

## 6. Tests inclus (12)

**PushSubscription model :**
- `endpoint_hash` consistent (même endpoint → même hash sha256)
- `recordFailure` increment
- `recordFailure` auto-disable après 5 échecs
- `recordSuccess` reset le compteur
- Scopes `active` + `forUser`
- `toWebPushArray()` format correct pour minishlink

**Controller :**
- Auth required pour `/push/subscribe`
- Subscribe crée la subscription en DB
- Validation des champs requis (URL endpoint, keys p256dh/auth)
- Re-subscribe avec même endpoint update (pas de doublon)
- Unsubscribe désactive
- `/push/public-key` retourne config
- User ne peut désinscrire que ses propres subscriptions

## 7. Limites et problèmes connus

- **iOS Safari < 16.4** : ne supporte PAS Web Push pour PWA non-installées. À partir de Safari 16.4 (mars 2023), supporte uniquement quand la PWA est installée via "Sur l'écran d'accueil".
- **iOS** : pas d'event `beforeinstallprompt` → on doit afficher des instructions manuelles (composant `pwa-install-prompt` le gère).
- **Pas de groupement de notifs** : chaque notif est indépendante. Pour grouper (ex: "3 nouveaux messages"), implémenter côté serveur.
- **Pas de notifications silencieuses** (chaque push = notif visible). C'est une contrainte des push services pour empêcher le tracking.
- **Service Worker en dev** : peut cacher les anciennes versions agressivement. Pour reset : DevTools → Application → Storage → "Clear site data".
- **Cache Tailwind** : le service worker peut cacher l'ancienne version du CSS après une MAJ. Solution : bump `CACHE_VERSION` dans `sw.js` à chaque déploiement.

## 8. Stats Phase 8

| Composant | Lignes |
|---|---|
| `manifest.webmanifest` | ~95 |
| `sw.js` | ~210 |
| `offline.html` | ~95 |
| `pwa.js` | ~110 |
| `push-notifications.js` | ~210 |
| `PushSubscription` model | ~85 |
| `WebPushSender` service | ~155 |
| `WebPushChannel` | ~50 |
| `PushSubscriptionController` | ~100 |
| `BookingReminderNotification` | ~75 |
| `GenerateVapidKeysCommand` | ~35 |
| 1 migration | ~55 |
| `mobile-bottom-nav` blade | ~70 |
| `pwa-install-prompt` blade | ~145 |
| `push-toggle` blade | ~155 |
| Tests | ~250 |
| Patches + guide | ~370 |
| **Total Phase 8** | **~2265 lignes** |

## 9. Checklist de PR

```
[ ] Fichiers Phase 8 copiés (rsync)
[ ] composer require minishlink/web-push
[ ] php artisan migrate
[ ] php artisan webpush:vapid → clés copiées dans .env
[ ] config/services.php : section webpush
[ ] routes/web.php : 4 endpoints /push/*
[ ] resources/js/app.js : import './pwa' + import './push-notifications'
[ ] Layouts : link manifest + meta theme-color + apple-touch-icon
[ ] Layouts : <x-mobile-bottom-nav /> + <x-pwa-install-prompt />
[ ] Page profil : <x-push-toggle />
[ ] Icônes PWA générées dans public/icons/
[ ] npm run build
[ ] php artisan test --filter=Phase8Test → 12 tests verts
[ ] Démo : SW activé dans DevTools
[ ] Démo : install PWA fonctionne
[ ] Démo : activation notif → test → notif reçue
[ ] Démo : mode offline → fallback offline.html
[ ] Démo : bottom nav visible sur mobile, cachée sur desktop
[ ] Servir en HTTPS en prod
```

Suggestion de commit :

```
feat(mobile): Phase 8 — PWA + Web Push notifications + mobile UI

NEW PWA:
- public/manifest.webmanifest with 9 icon sizes + 3 shortcuts
- public/sw.js: cache-first for assets, network-first for HTML, offline fallback,
  bypass for /livewire /api /broadcasting /assistant/stream
- public/offline.html standalone with auto-reload on reconnect
- resources/js/pwa.js: SW registration, beforeinstallprompt detection,
  navigate messages from SW

NEW PUSH:
- PushSubscription model + table with sha256 endpoint dedup
- WebPushSender service wrapping minishlink/web-push, auto-deactivate
  on HTTP 404/410, increment failure_count, disable after 5 failures
- WebPushChannel Laravel notification channel
- PushSubscriptionController (subscribe/unsubscribe/test/public-key)
- resources/js/push-notifications.js (cleanuxPush API: subscribe, unsubscribe,
  testNotification, getStatus, hasActiveSubscription)
- BookingReminderNotification example with toWebPush()
- artisan webpush:vapid command to generate VAPID keys

NEW MOBILE UI:
- mobile-bottom-nav component (sm:hidden, fixed bottom, safe-area-inset)
- pwa-install-prompt component (handles Android native + iOS instructions)
- push-toggle component (4 states: unsupported/denied/default/granted)

TESTS: 12 tests (PushSubscription model behavior, controller endpoints,
auth scoping, validation, dedup)
```

## 10. Suite possible Phase 8.1

- **Push topics** : permettre à l'user de choisir les types de notifs (RDV, factures, marketing)
- **Notification batching** : "3 nouveaux RDV" plutôt que 3 notifs séparées
- **Geo-trigger** : push quand le prestataire est à <500m de l'adresse
- **Native app** via Capacitor (envelopper la PWA en app iOS/Android sur les stores)
- **Background sync** : permettre de créer des bookings offline, synchro à la reconnexion
- **Push images** : envoyer une photo (ex: photo "avant/après" d'une mission)
