# PROMPT — Register mobile provider « Vérifié en un éclair » (cleanUx)

> Méga-prompt autonome, prêt à coller dans une nouvelle session d'agent IA (Claude Code ou équivalent)
> ouverte sur le repo cleanUx. Il encode l'analyse concurrentielle Uber/Heetch
> (`docs/ANALYSE-REGISTER-PROVIDER-UBER-HEETCH.md`) et l'état réel du code au 2026-07-29.
> Tout ce qui est cité ci-dessous (fichiers, endpoints, colonnes, statuts) a été vérifié dans le repo.

---

## LE PROMPT (copier tout ce qui suit)

Tu travailles sur **cleanUx**, marketplace multi-métiers style Uber/Bolt (nettoyage, peinture, babysitting, 30+ métiers) : Laravel 11 + Sanctum, apps mobiles Expo/React Native dans `mobile/provider` et `mobile/client` avec code partagé dans `mobile/shared`. 2116 tests verts — ils doivent le rester.

**Mission : transformer l'inscription mobile provider en un parcours « Vérifié en un éclair » — le niveau de vérification d'Uber (KYC biométrique, registres officiels, statut par document, monitoring) avec la vitesse de Heetch (compte en 60 secondes, activation < 48 h) et une UX de 2027 : une question par écran, tout est pré-rempli, tout est asynchrone, rien ne bloque jamais l'utilisateur.**

La quasi-totalité des briques backend existe déjà. Ton travail est à 80 % du **câblage** de modules production-ready jamais branchés au mobile, à 20 % de la correction de bugs qui rendraient le parcours infranchissable. Ne réinvente rien : réutilise.

### 0. Corrections préalables OBLIGATOIRES (le parcours est cassé sans elles)

1. **Collision de seeders sur le journey `provider_default`** : `database/seeders/ProviderOnboardingJourneySeeder.php` (5 étapes : `profile_complete`, `contract_sign`, `kyc_check`, `document_upload`, `skill_declare` — celles que le mobile connaît via `STEP_COMPONENTS` dans `mobile/provider/src/screens/onboarding/ProviderOnboardingScreen.tsx:24-30`) et `database/seeders/OnboardingJourneysSeeder.php:44-90` (7 étapes aux codes différents, avec `steps()->delete()` destructif, appelé par `ProductionBootstrapSeeder:56`) écrivent le même code de journey. Unifie : un seul seeder canonique, un seul jeu de codes d'étapes, aligné avec les composants mobiles ; l'autre seeder délègue ou disparaît.
2. **`PayoutsSetupValidator`** (`app/Services/OnboardingV2/Validators/`) lit `provider_profiles.stripe_account_id`, `stripe_details_submitted`, `stripe_payouts_enabled` — **ces colonnes n'existent pas**. Les vraies colonnes sont `stripe_connect_account_id`, `stripe_connect_status`, `stripe_connect_onboarded_at` (migration `2026_05_04_000003_create_organization_tables.php:171-173`). Corrige le validateur.
3. **Écrans morts** : `mobile/provider/src/screens/OnboardingScreen.tsx` appelle `POST /provider/onboarding/complete` (route inexistante) — supprime-le et retire-le du `RootNavigator`. `mobile/provider/src/screens/KYCScreen.tsx` lit `status.verified`, champ absent de la réponse de `KycController::status()` (qui renvoie `has_verification`, `status`, `decision`, `provider_verification_status`) — corrige ou fusionne avec la nouvelle étape KYC.
4. **Email de vérification jamais envoyé** : `ApiAuthController::register()` (`app/Http/Controllers/Api/Auth/ApiAuthController.php:104-155`) ne dispatche jamais `Registered` alors que `User implements MustVerifyEmail`. Dispatche l'événement (l'email doit partir en asynchrone, sans bloquer la réponse d'inscription).

### 1. Phase « 60 secondes chrono » — création du compte

Refonds le `RegisterForm` de `mobile/provider/src/screens/LoginScreen.tsx` en wizard plein écran, **une question par écran**, barre de progression persistante, transitions fluides, sauvegarde locale automatique de chaque réponse (reprise exacte si l'app est tuée) :

1. **Téléphone d'abord** (pattern Uber/Heetch) : saisie E.164 avec sélecteur pays (BE/FR par défaut), puis **OTP SMS 6 chiffres**. Le module existe intégralement côté backend (`app/Services/Sms/PhoneVerificationService.php`, Twilio + mock, codes hashés, rate-limits dans `config/sms.php`) mais ses routes sont **client uniquement** (`routes/api/client.php:126-127`). Crée les routes miroir provider `POST /provider/phone/verify-request` et `/verify-confirm` (throttle `otp`) accessibles pendant l'onboarding (hors garde `provider.approved`, comme les routes `/provider/kyc/*` — voir les exclusions `withoutMiddleware` dans `routes/api/provider.php:130-149`). L'OTP doit aussi être demandable AVANT la création du compte (route publique throttlée liée au register) pour vérifier le téléphone dès l'écran 1.
2. **Prénom + nom** (un écran), **email** (validation format réelle, pas `includes('@')`), **mot de passe** (min 8, indicateur de force, un seul champ + œil de visibilité — supprime le champ « confirmer »).
3. **Indépendant ou société ?** (les deux cartes `KindChoice` existantes). Si société : raison sociale + **numéro d'entreprise (SIRET/BCE/TVA) avec masque par pays** — il sera vérifié en phase 2, mais valide le format immédiatement côté client ET dans `RegisterRequest` (`app/Http/Requests/Api/Auth/RegisterRequest.php` : aujourd'hui `vat_number` est `nullable|max:32` sans aucun contrôle).
4. **Métier** : le `TradePicker` existant (`GET /api/trades`) + `TradeQuestions` (`provider_form_schema`) — mais valide enfin `trade_answers` côté serveur contre le schema du trade (aujourd'hui : aucune validation).
5. **CGU** + Turnstile (existants), puis `POST /api/auth/register` → l'utilisateur est DANS l'app en mode restreint. Objectif chronométré : < 60 secondes de l'ouverture à l'écran d'accueil.

### 2. Phase « Cockpit de vérification » — le dossier se remplit tout seul

Remplace la liste d'étapes actuelle de `ProviderOnboardingScreen` par un **cockpit** : un hub visuel façon checklist de mission spatiale où chaque vérification est une carte avec état temps réel (`à faire → en cours → vérifié ✓ / à corriger + motif`), pourcentage global, et notification push à chaque changement. Tout reste propulsé par le moteur Onboarding v2 existant (`GET /v2/onboarding/me`, `POST /v2/onboarding/steps/{id}/complete`, validations serveur systématiques via `app/Services/OnboardingV2/OnboardingEngine.php`) — tu ajoutes/étends des étapes et leurs validateurs, tu ne crées pas un deuxième moteur.

Cartes du cockpit :

- **Identité biométrique (KYC)** — l'équivalent du Real-Time ID Check d'Uber. Le backend Onfido est prêt (`app/Services/Kyc/`, `POST /provider/kyc/start`, checks `document + facial_similarity + watchlist_aml`, auto-approve sur `clear` → `verification_status='verified'`). Côté mobile, aujourd'hui, le `hosted_flow_url` retourné par `/kyc/start` **n'est jamais ouvert**. Ouvre-le (WebBrowser/deep-link Expo), gère le retour par deep-link + polling de `GET /provider/kyc/status` (et push quand le webhook Onfido tombe), affiche les états réels (`pending/in_review/clear/consider/rejected`). Selfie + liveness + lecture du document en < 3 minutes, statut « Identité vérifiée ✓ » quasi temps réel.
- **Entreprise (KYB) — uniquement si société.** Le module complet existe et n'est **jamais appelé** : `app/Services/KybV2/` (INSEE pour SIRET/SIREN, VIES pour TVA, CompaniesHouse, screening sanctions, risk score), endpoints `GET|POST /v2/kyb/me/entities` (`routes/api/v2-shared.php:44-50`). À l'inscription société, crée la `BusinessEntity` (aujourd'hui `createProviderIdentity()` crée l'`OrganizationAccount` mais aucune BusinessEntity) et lance la vérification. **Effet waouh obligatoire : l'utilisateur tape son SIRET/BCE, l'app pré-remplit raison sociale et adresse depuis le registre officiel** — il confirme d'un tap au lieu de taper.
- **Documents intelligents.** `DocumentsStep` (`mobile/provider/src/screens/onboarding/steps.tsx:194`) ne permet qu'un `identity_card` codé en dur. Étends au modèle complet `ProviderOnboardingDocument` (types `identity_card`, `passport`, `residence_permit`, `tax_id`, `insurance`, `diploma`, `criminal_record`, `other` ; statuts `pending_review → approved/rejected` ; remplacement versionné déjà géré). La liste des documents exigés doit être **dynamique par métier** (utilise `trades.requires_insurance_proof` et `ProviderTradeCertification` : un électricien doit fournir sa certification, un babysitter son extrait de casier, etc.). Capture caméra plein écran avec cadre de guidage, recto/verso quand pertinent, réutilise `documentPicker.ts` (validation mime/taille existante). **L'assurance est critique** : `ProviderOnboardingService::approveOnboarding()` exige un document `insurance` approuvé — aujourd'hui impossible à déposer depuis le mobile, c'est une impasse à lever.
- **Contrat signé, pas simulé.** `ContractStep` affiche un texte codé en dur (`steps.tsx:99-109`). Branche Contracts v2 : template `provider_agreement` seedé (`database/seeders/ContractTemplatesSeeder.php`), rendu + signature + PDF via `/v2/contracts/*` (`routes/api/v2-shared.php:27-33`), signature persistée (`ContractSignature`) avec piste d'audit. Signature par tap « Je signe » après scroll complet du contrat.
- **Paiements (Stripe Connect Express)** — aujourd'hui orphelin dans `MoreScreen`. Intègre-le comme carte du cockpit : `POST /provider/stripe-connect/onboard` → ouverture de l'Account Link → **deep-link de retour vers l'app** → re-check `GET /provider/stripe-connect/status`. Un provider vérifié doit pouvoir être payé.
- **Profil public** : photo de profil (contraintes affichées façon Uber : visage visible, fond neutre), bio, **zones d'intervention** (`service_zone_ids` — l'endpoint `POST /provider/onboarding/skills` les accepte, aucun écran ne les envoie). C'est la carte la plus légère : mets-la en premier pour un quick win psychologique.

Règles du cockpit : tout est **asynchrone et non bloquant** (on peut remplir les cartes dans n'importe quel ordre sauf `depends_on` du moteur), chaque carte annonce son temps estimé (« ~2 min »), et le pré-remplissage est roi (téléphone déjà saisi → `ProfileStep` pré-rempli au lieu de l'écrasement silencieux actuel de `PUT /provider/profile`).

### 3. Phase « En orbite » — l'attente qui n'en est pas une

Quand le dossier est complet mais que `provider_profiles.status` vaut encore `pending` (approbation admin via `ProviderRegistrationsCenter`), l'app affiche aujourd'hui un dashboard **entièrement en erreur** : le middleware `EnsureProviderIsApproved` renvoie 403 `{error_code: 'provider_pending_approval'}` sur toute la surface métier et ce code n'est intercepté **nulle part** (`mobile/shared/src/api/client.ts`). Corrige :

- Intercepteur global du code `provider_pending_approval` → écran dédié **« Dossier en orbite »** : timeline visuelle du dossier, promesse de délai (« généralement < 48 h », pattern Heetch), résumé de chaque vérification passée, et contenu utile pendant l'attente (le `WalkthroughScreen` existant, jamais présenté : c'est sa place — tutoriel de l'app, conseils pour bien démarrer, complétion du profil public).
- **Push notification à l'approbation** (Push v2 FCM/APNs existant) : « 🚀 Vous êtes en ligne ! » → l'app bascule sur le vrai dashboard.
- Pour l'admin : l'approbation doit rester un simple `approve()` — vérifie que le pont entre le moteur v2 et la voie legacy (`syncOnboardingV2Completed`) reste cohérent, et signale dans ta synthèse toute divergence restante entre les deux moteurs d'onboarding (audit `docs/audits-2026-06-24/AUDIT-05-PRODUCT-OWNER-METIER.md` §3.3).

### 4. Exigences transverses

- **i18n** : tous les nouveaux textes dans les 6 langues du projet (fr/nl/en/es/it/de), fr et nl parfaits en priorité (marché BE/FR).
- **Accessibilité** : labels, tailles de touche ≥ 44 pt, support lecteur d'écran, dark mode via le système de thème existant.
- **Tests** : chaque écran/étape a ses tests Jest à l'image des existants (`mobile/provider/__tests__/screens/Login.register.test.tsx` — 13 cas, `OnboardingDocuments.test.tsx`) ; côté Laravel, tests Feature pour chaque nouvelle route et chaque validateur corrigé. **Les 2116 tests existants doivent rester verts.**
- **Sécurité** : aucun secret en dur, OTP throttlés, documents sur le disque `private` (existant), données KYC chiffrées (le modèle `KycVerification` chiffre déjà `result_summary`/`metadata` — ne contourne pas ça).
- **Conventions** : `testID` sur tout élément interactif (pattern `register-*` existant), code partagé dans `mobile/shared`, mapping camelCase→snake_case dans les hooks API (voir `mobile/shared/src/auth/useRegister.ts`).
- **Livraison** : travaille par commits atomiques (corrections §0, puis phase par phase), avec à la fin une synthèse listant ce qui est branché, ce qui reste mocké (ex. Onfido en mode mock sans `ONFIDO_API_TOKEN` — le binding auto-upgrade dans `app/Providers/KycServiceProvider.php`), et les variables d'environnement à configurer en production.

**Cap final** : un artisan doit pouvoir créer son compte pendant sa pause café, être vérifié biométriquement avant d'avoir fini son expresso, voir son SIRET se pré-remplir tout seul, signer son contrat d'un pouce, et recevoir le push « Vous êtes en ligne ! » le lendemain — pendant qu'en coulisses tournent Onfido, l'INSEE, VIES, le screening sanctions, Stripe Connect et la revue documentaire. La confiance d'Uber, la vitesse de Heetch, la fluidité d'une app de 2027.

---

## Fin du prompt

### Notes d'utilisation (hors prompt)

- Lancer le prompt dans une session avec accès complet au repo ; il est autoporteur mais l'agent gagnera à relire `docs/ANALYSE-REGISTER-PROVIDER-UBER-HEETCH.md` et `docs/superpowers/plans/2026-05-24-mobile-rn-phase2-provider-master-index.md`.
- Le périmètre est volontairement large : pour une exécution en plusieurs sessions, découper dans l'ordre §0 (corrections), §1 (compte 60 s), §2 (cockpit), §3 (orbite) — chaque section est livrable indépendamment.
- Vérifications volontairement exclues (pas de brique existante) : background check criminel automatisé type Checkr (le type de document `criminal_record` existe et suffit à ce stade) et re-vérification biométrique aléatoire post-activation (itération future — le socle KYC la permettra).
