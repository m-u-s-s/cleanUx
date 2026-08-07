# Brio — Stores submission runbook (Apple App Store + Google Play)

Procédure complète pour builder et soumettre les apps natives iOS + Android via **Expo / EAS**
(EAS Build + EAS Submit), sans Xcode/Android Studio.

> **Deux apps distinctes**, chacune son listing store + son bundle id, sous l'organisation EAS `m-u-s-s` :
>
> | App | Dossier | Slug EAS | Bundle id iOS / package Android |
> |-----|---------|----------|----------------------------------|
> | **Brio** (client) | `mobile/client` | `brio-client` | `com.brio.client` |
> | **Brio Pro** (prestataire) | `mobile/provider` | `brio-provider` | `com.brio.provider` |
>
> Toutes les commandes `eas …` se lancent **depuis le dossier de l'app** (`cd mobile/client` ou
> `cd mobile/provider`). Répète chaque étape pour les deux apps.

## Prérequis

- [ ] `eas-cli` installé (`npm i -g eas-cli`) + connecté (`eas login`, compte `m-u-s-s`)
- [ ] `app.json` + `eas.json` présents dans chaque app (déjà configurés : profils `development` /
      `preview` / `production` + bloc `submit`)
- [ ] Build interne testé sur device réel : `eas build --profile preview --platform all` puis install
      via le lien EAS / TestFlight / Internal App Sharing
- [ ] CGV + Privacy Policy publiées sur le site web (URL prod stable)
- [ ] Sentry actif + DSN renseigné en prod (`@sentry/react-native`, cf. `mobile/shared/src/sentry`)
- [ ] Tous les HOT FIX 1-5 sécurité validés (CORS restrictif, TrustProxies, etc.)
- [ ] `eas.json` → `submit.production.ios` : remplacer `PLACEHOLDER_APPLE_ID` / `PLACEHOLDER_ASC_APP_ID`
      / `PLACEHOLDER_TEAM_ID` par les vraies valeurs (cf. §1)

## 0. EAS Build & Submit — le flux

Pas de build local : EAS compile dans le cloud et gère la signature.

```bash
cd mobile/client            # (puis répéter dans mobile/provider)

# Build production (iOS .ipa + Android .aab), credentials gérés par EAS
eas build --platform all --profile production

# Soumission aux stores (lit eas.json -> submit.production)
eas submit --platform ios --profile production
eas submit --platform android --profile production
```

- **Credentials** : `eas credentials` gère le certificat/provisioning iOS et le keystore Android
  (EAS-managed = recommandé ; pas de `keytool` ni de signing Xcode à la main).
- **Versions** : le profil `production` a `autoIncrement: true` → le build number iOS / `versionCode`
  Android s'incrémentent automatiquement. Le `version` (marketing) vit dans `app.json` (`1.0.0`).
- **OTA (mises à jour JS sans review store)** : `eas update --branch production` publie un correctif
  JS/assets via `expo-updates` — utile pour les hotfix qui ne touchent pas le natif (voir §6).

## 1. Apple App Store

### Comptes & accès
- Apple Developer Program : **99 $/an** ([signup](https://developer.apple.com/programs/enroll/))
- App Store Connect : créer une équipe ; récupérer le **Team ID** (→ `eas.json` `appleTeamId`)
- Créer les **deux** apps dans App Store Connect (Brio + Brio Pro) → récupérer chaque
  **ASC App ID** (→ `eas.json` `ascAppId`)
- DUNS Number requis pour une société
- Recommandé : créer une **App Store Connect API Key** (Users & Access → Integrations) pour que
  `eas submit` soumette sans mot de passe interactif

### Configuration (app.json, pas Xcode)
Tout ce qui était dans Xcode se déclare dans `mobile/<app>/app.json` :
- `expo.ios.bundleIdentifier` : `com.brio.client` / `com.brio.provider`
- Capabilities via `app.json` :
  - Push : `expo-notifications` (plugin) + entitlement APNs géré par `eas credentials`
  - Background : `expo.ios.infoPlist.UIBackgroundModes` = `["location", "fetch", "remote-notification"]`
    (le suivi GPS de mission utilise `expo-location` background + `expo-task-manager`)
  - Associated Domains (deep links) : `expo.ios.associatedDomains` = `["applinks:app.brio.com"]`

### Assets à fournir (par app)
- App Icon : 1024×1024 PNG (sans alpha) — déclaré dans `app.json` (`expo.icon`)
- Splash : `expo-splash-screen` (config `app.json`)
- Screenshots requis :
  - iPhone 6.7" (iPhone 15 Pro Max) : 1290×2796 — minimum 3 screenshots
  - iPhone 6.5" (iPhone 11 Pro Max) : 1242×2688
  - iPad Pro 12.9" : 2048×2732 (si app universal)

### Métadonnées App Store Connect
- Nom : **Brio** (client) / **Brio Pro** (prestataire)
- Sous-titre (30 chars max) : "Services pro à la demande" / "Vos missions, votre planning"
- Description (4000 chars max) : préparer le rédactionnel marketing
- Keywords (100 chars max) : `nettoyage,services,prestataire,marketplace,peinture,babysitting`
- URL support : https://brio.com/aide
- URL marketing : https://brio.com
- Privacy policy URL : https://brio.com/privacy-policy
- Catégorie principale : Services / Productivity
- Age rating : 4+ (sauf si chat ouvert)

### App Privacy (Apple Privacy Nutrition)
- Data linked to user : Name, Email, Phone, Physical Address, Location, Payment Info, User Content
- Data not linked : Analytics (anonymized), Diagnostics
- Tracking : selon usage (probablement non si pas de SDK pub tiers)

### Submission
```bash
cd mobile/client   # puis mobile/provider
eas build --platform ios --profile production     # produit le .ipa (signé par EAS)
eas submit --platform ios --profile production     # upload vers App Store Connect
```
Puis dans App Store Connect : compléter "App Information" + "Pricing and Availability", attacher le
build (TestFlight d'abord), **Submit for Review** (timing : 24h-7j). `eas submit` pousse le binaire ;
TestFlight est dispo automatiquement pour le beta interne.

## 2. Google Play Store

### Comptes & accès
- Google Play Developer Console : **25 $ one-shot** ([signup](https://play.google.com/console/signup))
- Créer les **deux** apps (packages `com.brio.client` / `com.brio.provider`)
- Créer un **service account** (Google Cloud → IAM) avec accès Play Console → télécharger la clé JSON
  → la référencer dans `eas.json` `submit.production.android.serviceAccountKeyPath`

### Build & Submit (EAS, pas Android Studio)
Pas de `keytool` ni de `app/build.gradle` à éditer : EAS gère le keystore.
```bash
cd mobile/client   # puis mobile/provider
eas build --platform android --profile production   # produit le .aab signé
eas submit --platform android --profile production   # upload sur le track "internal" (eas.json)
```
`applicationId`, `versionCode`, `versionName` viennent de `app.json` (`expo.android.package`, `version`,
`autoIncrement`). Le `.aab` n'a pas besoin d'être manipulé localement.

### Play Console setup (par app)
1. Renseigner : Privacy policy URL, catégorie (Lifestyle / Productivity), contact email,
   **Data safety form** (équivalent Privacy Nutrition)
2. Releases / tracks :
   - **Internal testing** (jusqu'à 100 testeurs) — cible de `eas submit` (`track: internal`)
   - **Closed testing** (alpha/beta, testeurs invités)
   - **Production** rollout staged 1% → 10% → 50% → 100%

### Promotion
Promouvoir le build d'Internal → Closed → Production se fait dans la Play Console (ou
`eas submit --profile production` en changeant le `track`).

## 3. Push notifications

Le module **Push v2** du backend Laravel envoie directement via APNs/FCM (pas le service Expo Push) ;
côté app on récupère le **device token natif** (`expo-notifications` → `getDevicePushTokenAsync`).

### APNs (iOS)
1. Apple Developer Portal → Keys → créer une **APNs Auth Key (.p8)**
2. Côté serveur Laravel : `.p8` + `APNS_KEY_PATH`, `APNS_TEAM_ID`, `APNS_KEY_ID` dans `.env`
   (le module Push v2 utilise `ApnsPushProvider`)
3. L'entitlement push de l'app est posé par `expo-notifications` + `eas credentials`

### FCM (Android)
1. Firebase Console → ajouter une app Android par package (`com.brio.client`, `com.brio.provider`)
2. Télécharger `google-services.json` → le référencer dans `app.json`
   (`expo.android.googleServicesFile = "./google-services.json"`)
3. Côté serveur : `FCM_CREDENTIALS_PATH` + `FCM_PROJECT_ID` dans `.env`

## 4. Deep linking (app.json + .well-known servis par Laravel)

### iOS Universal Links
1. Apple Developer → App ID → activer **Associated Domains**
2. `app.json` : `expo.ios.associatedDomains = ["applinks:app.brio.com"]`
3. Server-side (Laravel) : servir `https://app.brio.com/.well-known/apple-app-site-association` :
   ```json
   {
     "applinks": {
       "apps": [],
       "details": [
         { "appID": "TEAMID.com.brio.client", "paths": ["*"] },
         { "appID": "TEAMID.com.brio.provider", "paths": ["*"] }
       ]
     }
   }
   ```

### Android App Links
1. `app.json` : `expo.android.intentFilters` avec `autoVerify: true`, scheme `https`,
   host `app.brio.com`
2. Server-side : servir `https://app.brio.com/.well-known/assetlinks.json` (un objet par package +
   l'empreinte SHA-256 du certificat de signature, récupérable via `eas credentials`)

## 5. Monitoring crash mobile

- **Sentry React Native** (`@sentry/react-native`) — crashs JS **et** natifs (pas un webview).
  Init dans `mobile/shared/src/sentry`. Upload des sourcemaps au build via le plugin Sentry / un hook EAS.
- DSN par environnement (`APP_ENV` propagé via les profils `eas.json`).
- Crashlytics optionnel si besoin de stack natives plus fines.

## 6. EAS Update (OTA) — hotfix sans review store

Pour un correctif **JS/assets uniquement** (pas de changement natif : pas de nouvelle lib native, pas de
permission, pas de bump de version native) :
```bash
cd mobile/client   # ou mobile/provider
eas update --branch production --message "hotfix: ..."
```
Les utilisateurs reçoivent la mise à jour au prochain lancement, **sans repasser par App Review / Play
Review**. Un changement natif (nouvelle dépendance native, permission, SDK Expo) impose un **nouveau
build + resubmit** (§1/§2).

## 7. Coûts opérationnels annuels

| Item | Coût |
|------|------|
| Apple Developer | 99 €/an |
| Google Play (one-shot) | 25 € |
| FCM | Gratuit jusqu'à 100M push/mois |
| APNs | Gratuit |
| **EAS Build/Submit/Update** | Plan gratuit (builds limités/file d'attente) ou **Production ~99 $/mois** (builds prioritaires, plus de concurrence) ; facturable à l'usage |
| Sentry Team plan | 26 €/mois (10K events) |

## 8. Planning recommandé

| Semaine | Action |
|---------|--------|
| S1 | Setup comptes Apple/Google + DUNS + service account Play + APNs key |
| S2 | `eas build --profile production` (client + provider) — credentials EAS-managed |
| S3 | Tests device réel (TestFlight + Play Internal) + push APNs/FCM (device tokens) |
| S4 | Deep linking (`.well-known` Laravel + `app.json`) + assets/screenshots stores |
| S5 | `eas submit` → TestFlight beta + Play Internal track (× 2 apps) |
| S6 | Closed testing (testeurs invités) |
| S7 | Submit for Review (Apple ~24-72h, Google ~quelques heures) |
| S8 | Production rollout staged 1% → 100% + canal `eas update` production prêt pour les hotfix |
