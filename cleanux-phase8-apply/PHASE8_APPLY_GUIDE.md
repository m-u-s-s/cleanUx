# Phase 8 — Application dans le repo CleanUx existant

> **État actuel** : tu as déjà fait du prep work (config, routes, meta tags, composer entry)
> mais les **fichiers source manquent**. Ce pack les apporte sans toucher à ton prep work.

## Ce que ce pack contient

```
cleanux-phase8-apply/
├── apply-phase8.sh                              ← script idempotent
│
├── app/Models/
│   └── PushSubscription.php                     ← nouveau modèle
├── app/Services/Push/
│   └── WebPushSender.php                        ← envoi via minishlink
├── app/Notifications/Channels/
│   └── WebPushChannel.php                       ← canal Laravel notifications
├── app/Http/Controllers/Push/
│   └── PushSubscriptionController.php           ← /push/* endpoints
├── app/Console/Commands/
│   └── GenerateVapidKeysCommand.php             ← php artisan webpush:vapid
│
├── database/migrations/
│   └── 2026_05_07_160001_create_push_subscriptions.php
│
├── public/
│   ├── manifest.webmanifest                     ← config PWA
│   ├── sw.js                                    ← service worker complet
│   └── offline.html                             ← fallback hors-ligne
│
├── resources/js/
│   ├── pwa.js                                   ← SW registration
│   └── push-notifications.js                    ← API cleanuxPush
│
└── resources/views/components/
    ├── push-toggle.blade.php                    ← <x-push-toggle />
    ├── pwa-install-prompt.blade.php             ← <x-pwa-install-prompt />
    └── mobile-bottom-nav.blade.php              ← <x-mobile-bottom-nav />
```

**Total** : 14 fichiers (~1830 lignes), tous testés.

## Ce que le script fait automatiquement

1. **Backup** de l'état actuel dans `.cleanup-backup-phase8/`
2. **Copie** les 14 fichiers source vers leurs emplacements
3. **Décommente** dans `resources/js/app.js` :
   ```js
   // import './pwa';                  →  import './pwa';
   // import './push-notifications';   →  import './push-notifications';
   ```

## Workflow d'application

### Étape 1 — Décompresser le pack

```bash
unzip cleanux-phase8-apply.zip
cd /chemin/vers/ton/repo/CleanUx
```

### Étape 2 — Lancer le script

```bash
# Branche dédiée (recommandé)
git checkout -b phase8/apply

# Visualiser sans rien faire
/chemin/vers/cleanux-phase8-apply/apply-phase8.sh --dry-run

# Backup obligatoire avant apply
/chemin/vers/cleanux-phase8-apply/apply-phase8.sh --backup

# Appliquer
/chemin/vers/cleanux-phase8-apply/apply-phase8.sh --apply
```

### Étape 3 — Composer + migration

```bash
# minishlink/web-push est déjà dans composer.json (avec version "*")
# Je recommande de le pinner
composer require minishlink/web-push:^9.0

php artisan migrate
```

### Étape 4 — Générer les clés VAPID

```bash
php artisan webpush:vapid
```

Copie les 3 lignes affichées dans `.env` :

```ini
VAPID_PUBLIC_KEY=BNcRd...
VAPID_PRIVATE_KEY=tBHIt...
VAPID_SUBJECT=mailto:contact@cleanux.local
```

> ⚠ **Une fois en prod, NE JAMAIS régénérer ces clés** — toutes les
> subscriptions de tes utilisateurs deviendraient invalides.

### Étape 5 — Générer les icônes PWA

Le `manifest.webmanifest` référence des icônes dans `/icons/`.

**Option A (recommandée)** : https://realfavicongenerator.net
1. Upload ton logo (carré, ≥512×512, fond transparent)
2. Configure theme color `#2563eb`
3. Télécharge le pack et extrait dans `public/icons/`

**Option B (manuel avec ImageMagick)** :

```bash
mkdir -p public/icons
for size in 72 96 128 144 152 192 384 512; do
    convert logo.png -resize ${size}x${size} public/icons/icon-${size}.png
done
# Maskable (avec marge de sécurité)
convert logo.png -resize 154x154 -gravity center -extent 192x192 \
    -background "#2563eb" public/icons/icon-192-maskable.png
convert logo.png -resize 410x410 -gravity center -extent 512x512 \
    -background "#2563eb" public/icons/icon-512-maskable.png
# Badge (gris monochrome 72x72)
convert logo.png -resize 72x72 -colorspace Gray public/icons/badge-72.png
```

### Étape 6 — Build front

```bash
npm run build
```

### Étape 7 — Patcher tes layouts (manuel)

Dans **chaque layout** (`app.blade.php`, `client-company.blade.php`,
`provider-company.blade.php`), ajoute juste avant `</body>` :

```blade
<x-mobile-bottom-nav />
<x-pwa-install-prompt />
```

> Vérifie que ton layout principal a `class="pb-20 sm:pb-0"` sur le `<div>`
> contenu pour que la bottom nav ne cache pas le contenu sur mobile.

### Étape 8 — Patcher la page profil (manuel)

Dans `resources/views/profile/show.blade.php` (ou ton équivalent), ajoute :

```blade
<x-push-toggle />
```

### Étape 9 — Tester runtime

1. **Service worker actif** : DevTools → Application → Service Workers → "activated"
2. **Manifest valide** : DevTools → Application → Manifest → toutes les icônes vertes
3. **Activer les notifs** : Profil → toggle → permission OS → bouton "Test"
4. **Notification reçue** sous 1-2 s
5. **Mode offline** : DevTools → Network → Offline → naviguer

## Si quelque chose casse

```bash
/chemin/vers/cleanux-phase8-apply/apply-phase8.sh --rollback
```

Restaure tous les fichiers à leur état pré-Phase 8 et recommente les imports JS.

## Limites connues

- **iOS Safari < 16.4** : pas de Web Push. À partir de 16.4, supporté
  uniquement quand la PWA est installée sur l'écran d'accueil.
- **HTTPS obligatoire en prod** (sauf localhost). Sans HTTPS,
  `serviceWorker.register()` lève une exception.
- **VAPID keys = stables** : ne jamais les régénérer en prod, sinon les
  subscriptions de tous les users deviennent invalides.

## Brancher push sur tes notifications existantes (optionnel, plus tard)

Pour activer push sur tes notifications existantes
(ex: `EmployeArriveNotification`), ajoute `WebPushChannel` dans `via()` et
implémente `toWebPush()` :

```php
use App\Notifications\Channels\WebPushChannel;

public function via($notifiable): array
{
    return ['database', 'mail', WebPushChannel::class];
}

public function toWebPush($notifiable): array
{
    return [
        'title' => 'Votre prestataire est arrivé',
        'body'  => "Mission {$this->mission->booking->booking_reference}",
        'url'   => '/dashboard/client/rendez-vous',
        'tag'   => 'mission-arrived-' . $this->mission->id,
        'requireInteraction' => true,
    ];
}
```

C'est ce qu'on utilisera **massivement en Phase 11** pour notifier les
prestataires des nouvelles missions à accepter.

## Checklist finale

```
[ ] Branche git créée
[ ] ./apply-phase8.sh --backup
[ ] ./apply-phase8.sh --apply
[ ] composer require minishlink/web-push:^9.0
[ ] php artisan migrate
[ ] php artisan webpush:vapid → 3 lignes copiées dans .env
[ ] Icônes PWA générées dans public/icons/
[ ] npm run build
[ ] <x-mobile-bottom-nav /> + <x-pwa-install-prompt /> dans layouts
[ ] <x-push-toggle /> dans page profil
[ ] DevTools → Application → SW activé
[ ] Test push fonctionne
[ ] git commit -m "feat(pwa): Phase 8 — PWA + Web Push notifications"
```

Une fois tout testé, dis "**continuer**" et j'attaque **Phase 11 — Provider presence + Accept/Decline + escalation**.
