# CleanUx — Stores submission runbook (Apple App Store + Google Play)

Procédure complète pour soumettre l'app native iOS + Android aux stores après wrap Capacitor.

## Prérequis

- [ ] Capacitor configuré (`capacitor.config.ts` présent, `npm run cap:sync` OK)
- [ ] Builds locaux iOS + Android testés sur device réel
- [ ] CGV + Privacy Policy publiées sur le site web (URL prod stable)
- [ ] Sentry actif + DSN renseigné en prod
- [ ] Tous les HOT FIX 1-5 sécurité validés (CORS restrictif, TrustProxies, etc.)

## 1. Apple App Store

### Comptes & accès
- Apple Developer Program : **99$/an** ([signup](https://developer.apple.com/programs/enroll/))
- App Store Connect : créer une équipe
- DUNS Number requis pour société

### Configuration Xcode
1. Ouvrir le projet : `npx cap open ios`
2. Onglet **Signing & Capabilities** :
   - Bundle Identifier : `com.cleanux.app`
   - Team : sélectionner ton Apple Developer team
   - Automatic signing activé
3. Capabilities :
   - Push Notifications (pour APNs)
   - Background Modes : Location updates, Background fetch, Remote notifications
   - Sign in with Apple (optionnel)

### Assets à fournir
- App Icon : 1024×1024 PNG (sans alpha)
- Launch Screen : `LaunchScreen.storyboard`
- Screenshots requis :
  - iPhone 6.7" (iPhone 15 Pro Max) : 1290×2796 — minimum 3 screenshots
  - iPhone 6.5" (iPhone 11 Pro Max) : 1242×2688
  - iPad Pro 12.9" : 2048×2732 (si app universal)

### Métadonnées App Store Connect
- Nom de l'app : CleanUx
- Sous-titre (30 chars max) : "Services pro à la demande"
- Description (4000 chars max) : préparer rédactionnel marketing
- Keywords (100 chars max) : `nettoyage,services,prestataire,marketplace,béton,babysitting`
- URL support : https://cleanux.com/aide
- URL marketing : https://cleanux.com
- Privacy policy URL : https://cleanux.com/privacy-policy
- Catégorie principale : Services / Productivity
- Age rating : 4+ (sauf si chat ouvert)

### App Privacy (Apple Privacy Nutrition)
- Data linked to user : Name, Email, Phone, Physical Address, Location, Payment Info, User Content
- Data not linked : Analytics (anonymized), Diagnostics
- Tracking : selon usage (probablement non si pas de FB SDK)

### Submission
1. Build → Archive (Product menu)
2. Distribute App → App Store Connect → Upload
3. Wait pour processing (~30min)
4. Compléter "App Information" + "Pricing and Availability"
5. Submit for Review (timing : 24h-7j en moyenne)

## 2. Google Play Store

### Comptes & accès
- Google Play Developer Console : **25$ one-shot** ([signup](https://play.google.com/console/signup))
- Bundle id : `com.cleanux.app`

### Configuration Android Studio
1. Ouvrir le projet : `npx cap open android`
2. `app/build.gradle` :
   - `applicationId = "com.cleanux.app"`
   - `versionCode = 1`
   - `versionName = "1.0.0"`
3. Génère keystore signé :
   ```bash
   keytool -genkey -v -keystore cleanux-release.jks -alias cleanux -keyalg RSA -keysize 2048 -validity 10000
   ```
4. Configure signing dans `app/build.gradle` :
   ```gradle
   signingConfigs {
       release {
           storeFile file('cleanux-release.jks')
           storePassword '...'
           keyAlias 'cleanux'
           keyPassword '...'
       }
   }
   ```

### Build
```bash
cd android
./gradlew bundleRelease
```
Le bundle `.aab` est dans `app/build/outputs/bundle/release/`.

### Play Console setup
1. Créer l'app dans Console
2. Renseigner :
   - Privacy policy URL
   - App category : Lifestyle / Productivity
   - Contact email
   - Data safety form (équivalent Privacy Nutrition)
3. Releases :
   - **Internal testing** track first (jusqu'à 100 testeurs)
   - **Closed alpha** track (testeurs invités)
   - **Production** rollout 1% → 10% → 50% → 100%

### Submission
1. Upload `.aab` dans Internal testing
2. Test sur device réel via lien d'invitation
3. Promote vers Closed alpha (testeurs externes)
4. Promote vers Production rollout staged

## 3. Push notifications

### APNs (iOS)
1. Apple Developer Portal → Certificates → Create APNs Auth Key (.p8)
2. Stocker `.p8` dans serveur Laravel + setup `APNS_KEY_PATH`, `APNS_TEAM_ID`, `APNS_KEY_ID` dans `.env`
3. Push v2 module utilise déjà ApnsPushProvider

### FCM (Android)
1. Firebase Console → Add Android app `com.cleanux.app`
2. Download `google-services.json` → place dans `android/app/`
3. Récupère Server Key + Credentials JSON
4. Setup `FCM_CREDENTIALS_PATH` + `FCM_PROJECT_ID` dans `.env`

## 4. Deep linking

### iOS Universal Links
1. Apple Developer → App ID → Activate Associated Domains
2. Server-side: créer `https://app.cleanux.com/.well-known/apple-app-site-association` :
   ```json
   {
     "applinks": {
       "apps": [],
       "details": [{ "appID": "TEAMID.com.cleanux.app", "paths": ["*"] }]
     }
   }
   ```
3. Dans Xcode → Signing & Capabilities → Add Associated Domains : `applinks:app.cleanux.com`

### Android App Links
1. `android/app/src/main/AndroidManifest.xml` :
   ```xml
   <intent-filter android:autoVerify="true">
     <action android:name="android.intent.action.VIEW" />
     <category android:name="android.intent.category.DEFAULT" />
     <category android:name="android.intent.category.BROWSABLE" />
     <data android:scheme="https" android:host="app.cleanux.com" />
   </intent-filter>
   ```
2. Server-side: `https://app.cleanux.com/.well-known/assetlinks.json`

## 5. Monitoring crash mobile

- **Sentry React Native SDK** pour crashes JS (Capacitor webview)
- **Firebase Crashlytics** : iOS/Android natifs
- Setup dans `resources/js/capacitor/index.js`

## 6. Coûts opérationnels annuels

| Item | Coût |
|------|------|
| Apple Developer | 99 €/an |
| Google Play (one-shot) | 25 € |
| FCM | Gratuit jusqu'à 100M push/mois |
| APNs | Gratuit (via FCM proxy) |
| Capacitor Cloud Build (optionnel) | 35-100 €/mois |
| Sentry Team plan | 26 €/mois (10K events) |

## 7. Planning recommandé

| Semaine | Action |
|---------|--------|
| S1 | Setup Apple/Google accounts + DUNS |
| S2 | Capacitor wrap iOS + Android local builds |
| S3 | Tests device réel + push APNs/FCM |
| S4 | Deep linking + assets stores |
| S5 | Submission Apple TestFlight + Play Internal track |
| S6 | Closed alpha (testeurs invités) |
| S7 | Soumission review (Apple ~24-72h, Google ~2h) |
| S8 | Production rollout staged 1% → 100% |
