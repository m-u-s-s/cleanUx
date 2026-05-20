# Mobile PWA → Native via Capacitor — Guide

Date : 2026-05-20.

## État actuel

La PWA CleanUx est déjà fonctionnelle :
- `public/manifest.webmanifest` (nom, theme color, scope, 7 icônes)
- `public/sw.js` service worker (precache + runtime cache + web push)
- `public/offline.html` page fallback
- `public/icons/` (72/96/128/144/152/192/192-maskable/384/512/512-maskable)
- Component `<x-pwa-install-prompt />` (Alpine, beforeinstallprompt)

**Pour iOS Safari + Android Chrome, l'app est installable telle quelle** (Add to Home Screen).

## Pour aller en native via Capacitor

Capacitor wrap la PWA dans une vraie app iOS/Android, débloque les permissions natives (push APNs, géolocalisation background, biométrie, etc.).

### Installation Capacitor

```bash
npm install --save @capacitor/core @capacitor/cli @capacitor/ios @capacitor/android
npx cap init "CleanUx" "com.cleanux.app" --web-dir=public
```

Cette commande crée `capacitor.config.ts` :
```ts
import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.cleanux.app',
  appName: 'CleanUx',
  webDir: 'public',
  server: {
    // Pour dev/staging — pointe vers le serveur Laravel
    url: 'https://staging.cleanux.com',
    cleartext: false,
  },
  ios: {
    contentInset: 'always',
    backgroundColor: '#ffffff',
  },
  android: {
    backgroundColor: '#ffffff',
    allowMixedContent: false,
  },
};

export default config;
```

### Ajouter platforms

```bash
npx cap add ios
npx cap add android
```

Crée `ios/` et `android/` dans le repo. À committer si on veut versionner les builds natifs.

### Plugins natifs recommandés

```bash
npm install @capacitor/push-notifications      # APNs/FCM unifié
npm install @capacitor/geolocation              # GPS background pour ETA tracking
npm install @capacitor/camera                   # Upload photos Quality v2
npm install @capacitor/filesystem               # Téléchargements PDFs contracts/invoices
npm install @capacitor/app                      # Deep links bookings
npm install @capacitor/network                  # Détection online/offline pour UX offline-first
npm install @capacitor/share                    # Share natif (booking confirmation, etc.)
npm install @capacitor/biometric                # FaceID/TouchID pour login
```

### Deep links handling

Dans `app/Console/Kernel.php` ou un fichier JS dédié :
```js
// resources/js/capacitor-deeplinks.js
import { App } from '@capacitor/app';

App.addListener('appUrlOpen', (event) => {
  // event.url = "cleanux://bookings/123" ou "https://cleanux.com/bookings/123"
  const url = new URL(event.url);
  const path = url.pathname;
  if (path.startsWith('/bookings/')) {
    window.location.href = path;   // Navigation interne
  }
});
```

Configurer dans `android/app/src/main/AndroidManifest.xml` :
```xml
<intent-filter>
  <action android:name="android.intent.action.VIEW" />
  <category android:name="android.intent.category.DEFAULT" />
  <category android:name="android.intent.category.BROWSABLE" />
  <data android:scheme="https" android:host="cleanux.com" />
</intent-filter>
```

### Push notifications natives (intégration Push v2 module)

Le module Push v2 actuel envoie via FCM (Android Chrome) avec endpoint Web Push. Pour iOS native, il faut :
1. Enregistrer le bundle Apple Developer + APNs auth key
2. Envoyer via Firebase Cloud Messaging (FCM) qui sait router vers APNs
3. Mettre à jour `app/Services/Push/Providers/FcmPushProvider.php` pour utiliser le device_token natif au lieu du Web Push endpoint

### Build & deploy

```bash
# Sync web assets vers iOS/Android
npx cap sync

# Ouvrir Xcode pour build iOS (.ipa)
npx cap open ios

# Ouvrir Android Studio pour build APK/AAB
npx cap open android
```

### Stores submission

**App Store (iOS)** :
- Apple Developer Program : $99/an
- App Store Connect setup
- Screenshots iPhone 6.7" + iPad 12.9"
- Privacy policy URL
- Review process : 1-7j

**Play Store (Android)** :
- Google Play Developer : $25 one-shot
- Internal testing track recommandé avant prod
- Closed alpha → open beta → production rollout 1%→100%

### Coûts opérationnels

| Item | Coût/an |
|------|---------|
| Apple Developer | $99 |
| Google Play (one-shot) | $25 |
| FCM (Push) | Gratuit |
| APNs (via FCM) | Gratuit |
| Capacitor Cloud build (optionnel) | $35-100/mois |

### Plan de release recommandé

**J1** : PWA + install prompt (déjà fait, 0 effort restant)
**Semaine 1-2** : Capacitor wrap basique iOS/Android, tester offline mode
**Semaine 3-4** : Push natif APNs/FCM via @capacitor/push-notifications
**Semaine 5-6** : Deep links + share + geolocation background
**Semaine 7-8** : Beta closed testing, fix UX mobile
**Semaine 9+** : Submission stores + release publique

### Alternatives à Capacitor

- **Ionic** : framework UI au-dessus de Capacitor (overkill si on garde Livewire)
- **PWA seule** : suffit pour 80% des features. Manque : push iOS, geolocation background, biométrie
- **React Native / Flutter** : rewrite complet UI → effort >> Capacitor
- **TWA (Trusted Web Activity)** : Android only, alternative à Capacitor pour Android (pas iOS)

**Recommandation** : Capacitor reste le meilleur ratio coût/bénéfice pour shipper sur les 2 stores rapidement sans rewrite.

### Tests E2E mobile

```bash
# Tests Capacitor avec Appium ou WebdriverIO
npm install --save-dev @wdio/cli @wdio/local-runner
npx wdio config
# Configure pour iOS Simulator + Android Emulator
npx wdio run wdio.conf.ts
```

### Monitoring crash mobile

- Sentry React Native SDK pour crashes JS
- Firebase Crashlytics pour crashes natifs (iOS/Android)

## Roadmap conservative (sans Capacitor)

Si tu préfères rester PWA pure (90% des cas usage couverts) :

1. **Améliorer le service worker** : background sync pour upload offline, retry automatique des mutations échouées
2. **Ajouter shortcuts manifest** :
   ```json
   "shortcuts": [
     {"name": "Nouveau RDV", "url": "/dashboard/client/rendez-vous/nouveau"},
     {"name": "Mes contrats", "url": "/dashboard/client/contrats"},
     {"name": "Mon profil", "url": "/dashboard/client/profil"}
   ]
   ```
3. **Push notifications déjà actives** via Push v2 module + Web Push API.
4. **Offline-first patterns** : IndexedDB pour cache des bookings récents + Livewire à brancher
5. **Web Share API** pour partage bookings (déjà supporté en standard)

Le coût de ne pas faire Capacitor : pas d'app dans les stores (les utilisateurs doivent passer par "Add to Home Screen" depuis Safari/Chrome). Acceptable au début, frein à scale ensuite.
