# Mobile native deployment — CleanUx

Date : 2026-05-21

CleanUx est prête pour wrap Capacitor iOS + Android. Le code est en place :
- `capacitor.config.ts` (root)
- `resources/js/capacitor/index.js` (bridge JS pour App, Push, Geolocation)
- `public/manifest.webmanifest` + `public/sw.js` (PWA fonctionnelle)
- `PushService::providerFor($token)` route APNs/FCM par platform (livré Phase B)

**Pré-requis non-livrables en code** (acheter/configurer ce qui suit) :

## 1. Comptes développeur

| Plateforme | Coût | Délai validation |
|------------|------|------------------|
| Apple Developer Program | 99 €/an | 1-2 jours (vérification ID Apple) |
| Google Play Console | 25 € one-shot | Quelques heures |
| DUNS Number (Apple, société) | Gratuit Dun&Bradstreet | 5-7 jours |
| Firebase project (FCM) | Gratuit | Immédiat |

## 2. Installation tooling local

```bash
# Wraps Capacitor
npm install --save @capacitor/core @capacitor/cli @capacitor/ios @capacitor/android
npm install --save @capacitor/push-notifications @capacitor/geolocation @capacitor/app @capacitor/splash-screen

# Init projet (si pas déjà fait)
npx cap init "CleanUx" "com.cleanux.app" --web-dir=public

# Add platforms
npx cap add ios
npx cap add android

# Sync code après chaque modification web assets
npx cap sync
```

## 3. iOS — Xcode setup (requiert macOS)

```bash
npx cap open ios
```

Dans Xcode :
1. **Signing & Capabilities** :
   - Bundle ID : `com.cleanux.app`
   - Team : ton Apple Developer Team
   - Automatic signing ON
2. **Add capabilities** :
   - Push Notifications
   - Background Modes : Location updates, Background fetch, Remote notifications
   - Sign in with Apple (optionnel)
3. **Build** : Product → Archive → Distribute App → App Store Connect

### APNs setup
1. Apple Developer Portal → Certificates → Identifiers → APNs Authentication Key (.p8)
2. Download `.p8` → place secure server side
3. Remplir `.env` :
   ```
   APNS_KEY_PATH=/secure/AuthKey_XXXX.p8
   APNS_KEY_ID=XXXXXXXXXX
   APNS_TEAM_ID=YYYYYYYYYY
   APNS_BUNDLE_ID=com.cleanux.app
   APNS_ENVIRONMENT=production
   ```
4. CleanUx route déjà APNs via `App\Services\Push\PushService::providerFor()` quand `$token->platform === 'ios'`

## 4. Android — Android Studio setup

```bash
npx cap open android
```

### FCM setup
1. Firebase Console → Add Android app `com.cleanux.app`
2. Download `google-services.json` → place `android/app/`
3. Récupère le **Server Account JSON** (admin SDK) → upload secure
4. Remplir `.env` :
   ```
   FCM_CREDENTIALS_PATH=/secure/firebase-service-account.json
   FCM_PROJECT_ID=cleanux-prod
   ```

### Build AAB signé
1. Génère keystore :
   ```bash
   keytool -genkey -v -keystore cleanux-release.jks -alias cleanux -keyalg RSA -keysize 2048 -validity 10000
   ```
2. `android/app/build.gradle` :
   ```gradle
   signingConfigs {
       release {
           storeFile file('cleanux-release.jks')
           storePassword System.getenv('KEYSTORE_PASS')
           keyAlias 'cleanux'
           keyPassword System.getenv('KEY_PASS')
       }
   }
   ```
3. `./gradlew bundleRelease` → `.aab` dans `app/build/outputs/bundle/release/`

## 5. App Store submission (iOS)

1. App Store Connect → create app
2. Provide :
   - **Name** : CleanUx
   - **Subtitle** (30 chars max) : "Services pro à la demande"
   - **Description** (4000 chars max)
   - **Keywords** (100 chars max) : `nettoyage,services,prestataire,multi-metier,babysitting,peinture`
   - **Privacy policy URL** : https://cleanux.com/privacy-policy
   - **Support URL** : https://cleanux.com/aide
   - **Category** : Lifestyle / Productivity
   - **Age rating** : 4+ (Family-friendly)
3. **Screenshots requis** :
   - iPhone 6.7" (1290×2796) — minimum 3
   - iPhone 6.5" (1242×2688)
   - iPad Pro 12.9" (2048×2732) si universal
4. **Privacy Nutrition Label** :
   - Data linked : Email, Phone, Address, Location, Payment Info, Photos
   - Data not linked : Diagnostics, Analytics (anonymized)
   - Tracking : NO (sauf intégration FB/Google SDK)
5. **Submit for Review** : 1-7 jours moyenne

## 6. Play Store submission (Android)

1. Play Console → create app
2. Fill :
   - Privacy policy URL
   - Data safety form
   - Content rating questionnaire
3. **Release tracks** :
   - **Internal testing** (100 testeurs max, lien direct)
   - **Closed alpha** (testeurs invités email)
   - **Open beta** (lien public)
   - **Production rollout** : 1% → 10% → 50% → 100%
4. Upload `.aab` signé
5. Review automatique 2-4h moyenne

## 7. Deep linking

### iOS Universal Links
1. Apple Developer → App ID → Associated Domains
2. Server-side : `https://app.cleanux.com/.well-known/apple-app-site-association`
   ```json
   {
     "applinks": {
       "apps": [],
       "details": [{ "appID": "TEAMID.com.cleanux.app", "paths": ["*"] }]
     }
   }
   ```
3. Xcode → Signing & Capabilities → Add Associated Domains : `applinks:app.cleanux.com`

### Android App Links
1. `AndroidManifest.xml` :
   ```xml
   <intent-filter android:autoVerify="true">
     <action android:name="android.intent.action.VIEW" />
     <category android:name="android.intent.category.DEFAULT" />
     <category android:name="android.intent.category.BROWSABLE" />
     <data android:scheme="https" android:host="app.cleanux.com" />
   </intent-filter>
   ```
2. `https://app.cleanux.com/.well-known/assetlinks.json`

Le bridge JS `resources/js/capacitor/index.js` écoute déjà `appUrlOpen` event et navigate au path.

## 8. Monitoring crash mobile

- **Sentry React Native SDK** pour crashes JS (Capacitor webview) — déjà branchable via Sentry Laravel installé
- **Firebase Crashlytics** pour crashes natifs iOS/Android

```bash
npm install --save @sentry/capacitor @sentry/browser
```

## 9. Planning recommandé

| Semaine | Action |
|---------|--------|
| S1 | Setup Apple/Google accounts + DUNS |
| S2 | Capacitor wrap local iOS + Android |
| S3 | Tests device réel + push APNs/FCM (Phase B routing déjà fait) |
| S4 | Assets + deep linking + screenshots |
| S5 | TestFlight closed (50 testeurs invités) |
| S6 | Soumission Apple review + Play closed alpha |
| S7 | Production rollout staged 1% → 100% |

## 10. Coûts opérationnels annuels

| Item | Coût |
|------|------|
| Apple Developer | 99 €/an |
| Google Play | 25 € one-shot |
| FCM | Gratuit (jusqu'à 100M push/mois) |
| APNs (via Apple Developer) | Gratuit |
| Sentry (déjà installé Phase 2) | 26 €/mois plan Team |
| Capacitor Cloud Build (optionnel) | 35-100 €/mois |

## Note importante

**Sans Xcode (macOS) et Android Studio, le wrap natif ne peut pas être lancé en CI/Linux.** Cette étape nécessite obligatoirement :
- Mac avec Xcode pour iOS
- Linux/Mac/Windows avec Android Studio pour Android

Si tu n'as pas accès à un Mac, plusieurs options :
1. Louer un Mac Mini cloud : MacInCloud (~30€/mois), MacStadium
2. GitHub Actions macOS runner (gratuit pour repos publics)
3. Ionic Appflow / Capacitor Cloud Build (35-100€/mois)
