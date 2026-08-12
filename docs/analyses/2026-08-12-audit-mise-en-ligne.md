# Audit de mise en ligne CleanUx — rapport go / no-go (2026-08-12)

Produit par une équipe d'audit multi-agents (ultracode) : 8 auditeurs seniors en parallèle (routes web, mobile, données, chemin réservation→dispatch, paiements, sécurité, realtime/notifs, exploitation/tests), puis une passe de **revue adversariale** où chaque finding a été soit confirmé en reproduisant la preuve dans le code, soit réfuté. Lecture seule — rien n'a été modifié. **55 findings confirmés + 13 oublis rattrapés**, ici dédupliqués et regroupés par cause.

## VERDICT : NO-GO en l'état

Il existe **6 blocages durs** dont trois cassent le déploiement lui-même (`config:cache`/`route:cache` échouent, donc aucun déploiement ne finit) et trois cassent des parcours utilisateur ou perdent de l'argent. En plus : le **déploiement mute la base (migrations destructives) AVANT l'étape qui plante** — un déploiement « en échec » laisse quand même la prod à moitié migrée. À corriger avant toute tentative de lancement.

Effort estimé pour atteindre un go : les 6 blockers + le paquet « déploiement » + le paquet « argent » sont chacun petits à moyens, mais nombreux. Compter un sprint focalisé, pas un correctif ponctuel.

---

## 🔴 BLOCKERS (empêchent le lancement ou perdent de l'argent)

**B1 — Le déploiement ne se termine jamais : `config:cache` plante sur une closure.**
`config/sentry.php:37-61` définit `before_send => function($event){...}`. `php artisan config:cache` (deploy.yml:60) ne peut pas sérialiser une closure → l'étape échoue à chaque déploiement. *Fix : remplacer la closure par une classe invokable `[App\Sentry\BeforeSend::class,'__invoke']` ; smoke-test `config:cache` en CI.*

**B2 — Idem `route:cache` : des routes à action Closure ne sont pas sérialisables.**
`routes/api/public.php:22` (/health), `routes/admin.php:126,140,148,156`, `routes/public.php:44,70,127,131,135,139,157`, `routes/api.php:54`. *Fix : convertir ces closures en contrôleurs invokables — seule façon d'autoriser `route:cache` en prod.*

**B3 — L'ordre de déploiement mute la base sur un déploiement qui échoue.** *(aggrave B1)*
`deploy.yml:59-61` sous `set -e` : `migrate --force` (l.59) s'exécute **avant** `config:cache` (l.60) qui plante toujours (B1). Au premier déploiement, la base — migrations destructives comprises — est mutée alors que le job est rapporté en échec. *Fix : mettre `config:cache`/`route:cache` avant `migrate`, ou valider la config avant toute migration ; sauvegarde préalable obligatoire.*

**B4 — Paiement client mobile : encaisse 100 % sur la plateforme, ne paie jamais le prestataire, jamais rattaché au booking.**
`BookingPaymentController.php:50-69`, `PaymentCheckoutScreen.tsx:57-66`, `StripeWebhookHandlers.php:206`. Le PaymentIntent mobile n'a ni `transfer_data.destination` ni `application_fee`, et son id n'est jamais écrit dans `booking.stripe_payment_intent_id` → le webhook ne retrouve pas le booking. *Fix : router le paiement mobile par le même service à destination charge que le web ; écrire l'intent id sur le booking ; lookup de secours par `metadata.booking_id`.*
⚠️ **Nuance (oubli M4)** : en réalité le mobile réserve peut-être via la WebView `/commander` (`HomeActionsSheet.tsx:49-72`, `useWebViewTicket.ts`), pas via `POST /api/client/bookings`. **À trancher en premier** : quel chemin de paiement le mobile emprunte-t-il réellement ? La réponse change B4, B7 et M-dispatch.

**B5 — Vérification de signature webhook contournable via le provider `mock` choisi par l'URL (KYC / Insurance / SMS).**
`KycWebhookController.php:24-88`, `KycMockProvider.php:88-94`, `KycVerificationService.php:104-134,193-245`. Le segment `{provider}` de l'URL sélectionne le provider ; `mock` accepte n'importe quelle signature. Pire (oubli M3, `KycVerificationService.php:116-125`) : sans `resource id` dans le payload, l'événement s'applique à **la vérification la plus récente** → un attaquant peut faire passer un KYC en « vérifié ». *Fix : ne jamais résoudre `mock` en production ; exiger `{provider}` == provider configuré ; refuser un événement sans resource id.*

**B6 — Push mobile entièrement mort (3 causes cumulées).**
(a) L'app envoie `provider: 'expo'` (`push/hooks.ts:44-50`) que la validation refuse (`in:fcm,apns,mock`, `DeviceTokenController.php:23-31`) → **422 pour 100 % des appareils** (oubli M6). (b) Même si l'enregistrement passait, **aucune notification n'emprunte le canal natif `PushChannel`** et aucun `ExpoPushProvider` serveur n'existe (oubli M7, `PushServiceProvider.php:17-25`). (c) À l'ouverture d'un push, le client lit `data.screen` que le serveur n'envoie jamais (`useNotificationRouting.ts:24-29`). *Résultat : aucune notification push n'arrive ni ne route sur mobile.* *Fix : accepter `expo`, implémenter `ExpoPushProvider` (POST exp.host) OU basculer sur vrais tokens FCM/APNs ; router au moins une notification par `PushChannel` ; ajouter `data.screen`.*

**B0 (rappel, non-code) — Aucune page publique ne s'affiche : `/pricing` et `/services` renvoient 500.**
`pricing.blade.php:7`, `service-trade.blade.php:9,283`, `services-index.blade.php:125` : le JSON-LD `@context` est compilé comme une directive Blade non fermée. *Fix : échapper en `@@context` (comme `guest.blade.php`) ; ajouter un `GET assertOk()` réel sur ces pages.* — **classé blocker : ce sont des pages publiques d'acquisition qui plantent.**

---

## 🟠 HIGH — argent, dispatch, exploitation

### Argent (encaissements manqués / fuites)
- **H1 — Suppléments sur place marqués `CHARGED` sans capture Stripe** → revenu jamais encaissé. `MissionExtraService.php:236-254`. *Confirmer le PI off_session avant de passer `CHARGED`.*
- **H2 — Frais d'annulation jamais encaissés + empreinte carte jamais libérée** pour les bookings `authorized`. `CancelBookingService.php:169-205`. *Capturer le fee puis annuler le reste du PI ; ne pas router l'auth-hold vers `refundMissionPayment` qui exige `captured`.*
- **H3 — Retrait portefeuille prestataire (withdraw/Express) : crée `ProviderPayout` + débit ledger mais AUCUN vrai Stripe Payout/Transfer exécuté.** `ProviderWalletService.php:233-276`, `ExpressPayoutService.php:77-125`. *L'argent quitte le ledger interne sans jamais partir chez le prestataire. Trancher le modèle (payouts auto vs. manuels) et exécuter le transfert réel.*
- **H4 — Remboursement de l'ACOMPTE jamais rattaché** (oubli M1) : ni clawback, ni `payment_status`, ni compta. `StripeWebhookHandlers.php:110-113`. *Retrouver le booking par `deposit_payment_intent_id` en plus de l'intent principal.*
- **H5 — `STRIPE_CONNECT_WEBHOOK_SECRET` manquant → endpoint 500, sans garde-fou.** `StripeConnectWebhookController.php:35-42`. *Ajouter au preflight/health ; renvoyer 200 sur secret absent pour ne pas faire retenter Stripe en boucle ; bloquer le go-live si absent.*

### Chemin réservation → dispatch
- **H6 — L'annulation n'arrête ni la recherche ni les offres ; `accept()` « dé-annule » un booking.** `ClientBookingController.php:218-249`, `MissionDispatchService.php:143-201`. *Refuser accept si booking annulé ; cascader cancel vers `SearchOutcomeService`.*
- **H7 — Réservations PLANIFIÉES créées via l'API mobile jamais dispatchées.** `CreateBookingFromApiAction.php:66-96` n'appelle que `maybeDispatchAsap`. *Passer par `DispatchEngine::dispatchBooking()` comme le web, ou refuser explicitement `scheduled` sur cet endpoint.*
- **H8 — API mobile : `asap_enabled`, filtre de ZONE et `trade_id` non appliqués/persistés** (finding [24] + oublis M5). `CreateBookingFromApiAction.php:54-92` n'écrit ni `service_zone_id` ni `trade_id` → `CandidateFinder` ne peut pas filtrer correctement. *Parité avec `OrderJourney` : résoudre et persister zone+métier, refuser l'asap si zone/métier ne l'autorise pas.*
- **H9 — Modèle récurrent : séries inertes (aucune réservation générée) ET créées en double.** `ApplyRecurringTemplateService.php:74,99` (double `create()`), `next_occurrence_at` jamais amorcé. *Supprimer le second `create()` ; initialiser `next_occurrence_at`.*

### Exploitation / déploiement
- **H10 — File d'attente incohérente : prod dispatche sur `redis`, workers livrés écoutent `database` → aucun job traité.** `.env.production.example:42` vs `deploy/supervisor/brio-worker.conf.example:9`. *Aligner ; ajouter un parity-check.*
- **H11 — L'ordonnanceur (`schedule:run`) n'est provisionné par aucune étape de déploiement.** `Kernel.php:21-105`, `deploy.yml:46-66`. *Sans lui : pas d'escalade d'offres, pas de scan présence, pas de purge. Automatiser le timer + sonde heartbeat.*
- **H12 — SMS/Push par défaut sur `mock`, aucun gate bloquant en CI/deploy, `/api/health` n'audite pas les providers.** `config/sms.php:6`, `config/push.php:6`. *Ajouter `ops:check-providers --strict` bloquant au deploy + check dans /health.*
- **H13 — `BROADCAST_CONNECTION` défaut `null` : tout le temps réel jeté en silence, chat sans repli de polling.** `config/broadcasting.php:19`. *Forcer `reverb` en prod (via `config:parity-check` réellement câblé) + ajouter un `refetchInterval` de repli à `useChatMessages`.* — regroupe les findings [13][40][44][53].
- **H14 — `/api/health` traite Stripe et Reverb comme dépendances DURES malgré les commentaires « soft-fail ».** (oubli M8) `routes/api/public.php:29-30,58-74` : les blocs try/catch écrasent les null par un booléen → un secret manquant fait passer /health en « degraded ». *Corriger la logique d'agrégation.*
- **H15 — Notifications de cycle de vie synchrones via `mail` : une exception SMTP fait planter (500) l'action métier (arrivée/démarrage).** `MissionLifecycleService.php:126-130,169-171,283-285`. *`ShouldQueue` sur ces notifications, ou try/catch soft-fail.*
- **H16 — Collision de nom `admin.onboarding.document.file` (web + api) : le nom résout DÉJÀ vers la route API sans session → aperçu de documents admin cassé.** (finding [16] + oubli M10) `routes/admin.php:572-574` vs `routes/api/provider.php:353-355`. *Renommer la route API.*

---

## 🟡 MEDIUM (14) — à traiter avant ou juste après le lancement

- **M-1** Base de calcul remboursement/no-show = `estimated_price` alors que charge/commission utilisent `devis_estime` → montants incohérents. `CancelBookingService.php:187`.
- **M-2** Clawback de remboursement sur-prélève le prestataire sur les plans acompte (dénominateur = `charge.amount` au lieu du total). `StripeWebhookHandlers.php:136-149`.
- **M-3** `ProcessStripeWebhookJob` `tries=1` : le `backoff()` est mort, la reprise repose entièrement sur le cron horaire. `ProcessStripeWebhookJob.php:20`.
- **M-4** Stored-XSS via upload SVG (avatar client + photo onboarding prestataire). `ClientProfileController.php:34-42`, `ProviderOnboardingController.php:58-78`. *Restreindre aux `jpg,png,webp` ou servir en `Content-Disposition: attachment`.*
- **M-5** Aucun filet planifié pour réconcilier recherches ASAP bloquées / offres expirées. `EscalateMissionAssignmentJob.php:36`. *Commande planifiée qui expire les assignments et `exhaust()` les recherches périmées.*
- **M-6 & M-7** `QUEUE_CONNECTION` défaut `sync` : désactive l'escalade d'offres ASAP et fait s'exécuter le job `->delay()` immédiatement (casse les vagues). `config/queue.php:16`. *Refuser le boot prod si `sync` ; `database` par défaut sûr dans `.env.example`.*
- **M-8** Espace société cliente mobile : onglet « Pilotage » injoignable + 3 raccourcis d'accueil morts. `CompanyOverviewScreen.tsx:49-57`, `ClientCompanyNavigator.tsx:39-89`.
- **M-9** `mission_assignments` : colonnes en doublon `role`/`role_on_mission` et `status`/`assignment_status` ; seule `assignment_status` est vivante. *Migration de suppression des colonnes dormantes après vérif.*
- **M-10** Deux cases de navigation « Ma présence » pointent vers un endpoint JSON (`presence.me`), pas une page. `config/modules.php:140,331`. *Retirer du catalogue.*
- **M-11** Module admin « Automation » cité au registre mais **sans composant** → rend le placeholder « à connecter ». `config/modules.php:189`. *Retirer l'entrée ou implémenter `AutomationCenter`.*
- **M-12** Trois cases admin gardées par un `Gate can:` mais montrées à tout admin → 403 pour un admin à périmètre restreint (menu menteur). `ModuleCatalogue.php:115-167` ignore les Gates. *Faire évaluer les Gates admin par `ModuleCatalogue`.*
- **M-13** `env()` lu hors config dans `TrustProxies`/`SecurityHeaders` → **ignoré dès que `config:cache` réussit** (donc dès que B1 est corrigé, ces middlewares se mettent à voir du null). `TrustProxies.php:21`, `SecurityHeaders.php:33-41`. *Créer `config/security.php` + `config/trustedproxy.php`.*
- **M-14** Dockerfile en `php:8.3-cli` alors que `composer.json` exige `php ^8.5` → `composer install` échoue au build. `Dockerfile:1,14`.
- **M-15** `storage:link` exécuté par aucune étape de déploiement. *Ajouter au script SSH (idempotent).*
- **M-16** Portails MySQL-FK et E2E non bloquants en CI + PHPUnit sur SQLite FK désactivées → le vert ne prouve pas la prod MySQL. `ci.yml:113,189`, `phpunit.xml:42-44`.

---

## 🟢 LOW (sélection) — dette et pièges à noter

Devise codée en dur `'eur'` dans `CommissionService` (finding 38) · `ProviderPayout` à la complétion restent `PENDING` jamais réconciliés + `captureMissionPayment` code mort (39) · colonnes sensibles mass-assignables sur `User` (`platform_role`, `role`, `organization_account_id`) (41) · rotate/revoke API token ne vérifie pas `tokenable_type` (42) · canal `providers.presence` autorisé sur la colonne `role` dépréciée (43) · **5 écrans prestataire orphelins** (`HomeScreen`, `MoreScreen`, `SettingsScreen`, `WalletScreen`, `MissionExecutionScreen`) (45) · deep links à portée d'espace non résolus (46) · worker supervisor ne draine pas la file `notifications` (47) · `device_tokens` sans discriminant client/prestataire (48) · compte à rebours d'offre affiche « 15 s » alors que le TTL config est 20 s (49) · `RecurringBookingSeries` fillable/casts fantômes (50) · **modèles morts sans table `Company` + `QualityAudit` avec factories qui planteraient** (51) · factories désynchronisées sur des tables d'argent/promo (`CustomerCredit`, `ReferralReward`, `PromoCodeRedemption`) (52) · `deploy.yml` ne vérifie pas `APP_KEY` (54) · `config:parity-check` existe mais n'est câblé dans aucune étape du pipeline (M13) · Gates admin `manage-*` définis mais jamais appliqués comme middleware (M11) · migrations datées dans le futur (55, cosmétique).

---

## Checklist go-live minimale (ordre conseillé)

1. **Débloquer le déploiement** : B1 (closure Sentry), B2 (closures de routes), B3 (ordre migrate/config), M-14 (Dockerfile 8.5), M-15 (storage:link), H10 (queue redis/database), H11 (scheduler), M13 (env hors config), câbler `config:parity-check` + `APP_KEY` guard.
2. **Trancher le chemin mobile réel** (M4) : WebView `/commander` vs API native — puis corriger B4/B6/H7/H8 en conséquence.
3. **Fermer les fuites d'argent** : B4, H1, H2, H3, H4, H5, M-1, M-2.
4. **Fermer les failles** : B5 (webhook mock + KYC blast radius), M-4 (SVG XSS), Low 41/42/43.
5. **Fiabiliser le dispatch** : H6, H9, M-5, M-6/M-7 (queue sync).
6. **Notifications** : B6 (push mobile), H12 (providers mock), H13/H14 (broadcast/health), H15 (mail synchrone), M-8 (nav société cliente).
7. **Nettoyer les fantômes** : M-10, M-11, M-12, Low 45/51/52.
8. **Durcir la CI** : M-16 (rendre MySQL-FK bloquant), tests `assertOk()` sur pages publiques (B0).

**Note de confiance** : audit adversarial à deux passes sur 8 dimensions ; chaque finding retenu a été reproduit dans le code. Restent des zones à confirmer par la session qui corrigera — surtout **M4 (chemin de réservation réel du mobile)**, qui conditionne plusieurs blockers. Le déploiement reste par ailleurs à 0 succès historique : ne pas tenter de lancer avant d'avoir coché la section 1.
