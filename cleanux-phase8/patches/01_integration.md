# Phase 8 — Patches manuels d'intégration

## 1. Composer — installer minishlink/web-push

```bash
composer require minishlink/web-push
```

## 2. Générer les clés VAPID

```bash
php artisan migrate
php artisan webpush:vapid
```

Copier les 3 lignes affichées dans `.env` :

```ini
VAPID_PUBLIC_KEY=BNcRd...
VAPID_PRIVATE_KEY=tBHIt...
VAPID_SUBJECT=mailto:contact@cleanux.local
```

> ⚠ **Une fois en prod, NE JAMAIS régénérer ces clés** — toutes les
> subscriptions de tes utilisateurs deviendraient invalides.

## 3. config/services.php — section webpush

Ajouter à la fin de `config/services.php` :

```php
'webpush' => [
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject'     => env('VAPID_SUBJECT', 'mailto:contact@cleanux.local'),
],
```

## 4. routes/web.php — endpoints push

Ajouter dans le groupe `auth` :

```php
use App\Http\Controllers\Push\PushSubscriptionController;

Route::middleware('auth')->prefix('push')->group(function () {
    Route::post('/subscribe',   [PushSubscriptionController::class, 'subscribe']);
    Route::post('/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);
    Route::post('/test',        [PushSubscriptionController::class, 'test']);
});

// Public (pas besoin d'auth pour récupérer la clé publique)
Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);
```

## 5. resources/js/app.js — imports

Ajouter à la fin :

```js
import './pwa';
import './push-notifications';
```

Puis :

```bash
npm run build
```

## 6. Layouts — meta tags PWA

Dans **chaque layout** (`app.blade.php`, `client-company.blade.php`, `provider-company.blade.php`, `guest.blade.php`), ajouter dans le `<head>`, **avant** `@vite` :

```blade
{{-- Phase 8 — PWA --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#2563eb">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="CleanUx">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
```

## 7. Layout — Bottom navigation mobile

Dans les layouts client et fournisseur, ajouter **juste avant** `</body>` :

```blade
<x-mobile-bottom-nav />
<x-pwa-install-prompt />
```

> Vérifier que ton layout a bien `class="pb-20 sm:pb-0"` sur le `<div>` principal
> pour ne pas que la bottom nav cache le contenu sur mobile.

## 8. Page Profil — toggle des notifs

Dans `resources/views/profile/show.blade.php` (ou la page Paramètres) :

```blade
<div class="space-y-4">
    <x-push-toggle />
    {{-- ... autres préférences ... --}}
</div>
```

## 9. Icônes PWA — à générer

Le manifest référence des icônes dans `/icons/` (tailles 72, 96, 128, 144, 152, 192, 384, 512 + 2 maskable). Tu peux :

**Option A — Outil en ligne (recommandé)** :
1. Va sur https://realfavicongenerator.net
2. Upload ton logo (carré, ≥512x512, fond transparent)
3. Configure les couleurs (`#2563eb` theme color)
4. Télécharge le pack et extrait dans `public/icons/`

**Option B — Manuellement** depuis un logo carré :

```bash
# Avec ImageMagick
mkdir -p public/icons
for size in 72 96 128 144 152 192 384 512; do
    convert logo.png -resize ${size}x${size} public/icons/icon-${size}.png
done
# Maskable = avec marge de sécurité (safe area)
convert logo.png -resize 154x154 -gravity center -extent 192x192 -background "#2563eb" public/icons/icon-192-maskable.png
convert logo.png -resize 410x410 -gravity center -extent 512x512 -background "#2563eb" public/icons/icon-512-maskable.png
# Badge (gris monochrome 72x72)
convert logo.png -resize 72x72 -colorspace Gray public/icons/badge-72.png
```

## 10. (Optionnel) Brancher webpush sur les notifications existantes

Pour activer push sur tes notifications existantes (ex: `EmployeArriveNotification`),
ajouter `WebPushChannel` dans `via()` et implémenter `toWebPush()` :

```php
// app/Notifications/EmployeArriveNotification.php

use App\Notifications\Channels\WebPushChannel;

public function via($notifiable): array
{
    return ['database', 'mail', WebPushChannel::class];
}

public function toWebPush($notifiable): array
{
    return [
        'title' => 'Votre employé est arrivé',
        'body'  => "Mission {$this->mission->rendezVous?->booking_reference}",
        'url'   => '/dashboard/client/rendezvous',
        'tag'   => 'mission-arrived-' . $this->mission->id,
        'requireInteraction' => true,
    ];
}
```

Voir `BookingReminderNotification.php` livré comme exemple complet.

## 11. nginx — servir le service worker

Le SW doit être servi avec scope `/`. Aucune config spéciale nginx n'est nécessaire
tant que `/sw.js` est dans `public/`. Mais pour éviter le cache :

```nginx
location = /sw.js {
    add_header Cache-Control "no-store, no-cache, must-revalidate";
    expires 0;
}

location = /manifest.webmanifest {
    add_header Cache-Control "public, max-age=3600";
}
```

## 12. Tests

```bash
php artisan migrate
php artisan test --filter=Phase8Test
```

12 tests doivent passer :
- 6 sur `PushSubscription` model (hash, recordFailure/Success, scopes, toWebPushArray)
- 6 sur le controller (subscribe, validation, update, unsubscribe, public-key, scope user)

## 13. Test runtime

1. **Servir en HTTPS** (obligatoire pour service worker, sauf localhost)
   ```bash
   php artisan serve   # localhost:8000 — OK pour dev
   ```

2. Ouvrir Chrome DevTools → Application → Service Workers → vérifier "activated"

3. Aller sur la page profil → cliquer "Activer les notifications"
   - Permission popup OS → accepter
   - Notification "Activation réussie"

4. Cliquer "Envoyer un test" → notif système doit apparaître sous 1-2s

5. Tester l'install PWA :
   - Chrome desktop : icône ⊕ dans la barre d'URL
   - Chrome Android : "Ajouter à l'écran d'accueil" dans le menu
   - iOS Safari : Partager → Sur l'écran d'accueil (instructions affichées via le composant)

6. Tester offline :
   - DevTools → Network → "Offline"
   - Naviguer → page déjà visitée s'affiche
   - Page jamais visitée → fallback `/offline.html`
