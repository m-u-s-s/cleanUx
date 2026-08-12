# Chantier « Go-Live » — corriger tous les blocages de mise en ligne (web + mobile natif, sans rien casser)

ultracode

Tu travailles sur le monorepo CleanUx : marketplace multi-services, Laravel 11 + Livewire (web) + monorepo Expo/React Native sous `mobile/` (`mobile/client`, `mobile/provider`, `mobile/shared`). Base MySQL en prod, tests PHPUnit sur SQLite.

**MISSION : rendre la plateforme déployable et sûre en production — corriger TOUS les findings de l'audit `docs/analyses/2026-08-12-audit-mise-en-ligne.md` (LIS-LE en entier d'abord), du blocker au low, dans l'ordre des lots ci-dessous. Objectif : verdict GO, zéro régression, web ET mobile natif.**

**FAIT ÉTABLI (résout l'ambiguïté M4 de l'audit) : le mobile client réserve via l'API NATIVE `POST /api/client/bookings`, PAS via la WebView `/commander`.** Donc le chemin natif création → paiement → dispatch → annulation EST le chemin de production, et il est actuellement cassé (B4, H7, H8). Il doit devenir production-safe et atteindre la PARITÉ avec le parcours web `OrderJourney`/`DispatchEngine`.

## MÉTHODE IMPOSÉE — équipe de dev senior multi-agents qui se corrige elle-même

Utilise l'orchestration multi-agents (**ultracode / outil Workflow**) à chaque lot :
1. **Architecte** : lit le code concerné, confirme le finding contre le code ACTUEL (l'audit est fiable mais date du 2026-08-12 — re-vérifie avant de coder), tranche l'emplacement du correctif.
2. **Implémenteurs** en parallèle (worktrees isolés si les fichiers se chevauchent).
3. **Panel de revue adversariale** après chaque lot, lentilles distinctes : (a) le finding est-il réellement corrigé (reproduire l'ancien bug, prouver qu'il ne se reproduit plus) ? (b) NON-RÉGRESSION — qu'est-ce qui pourrait casser ? (c) parité web ↔ mobile natif ? (d) le correctif est-il au bon endroit / réutilise-t-il l'existant ? (e) sécurité. Chaque finding de revue est contesté avant correction.
4. **Reboucle** revue → correction jusqu'à zéro finding ouvert ET batterie de vérification verte. Ne passe jamais au lot suivant sinon.
5. Si Workflow indisponible : même schéma avec des sous-agents. La revue adversariale n'est jamais sautée.

## PROTOCOLE « NE RIEN CASSER » (sacré)

- Avant de modifier un fichier partagé : lire ses tests et ses consommateurs (grep). Après CHAQUE lot : `php artisan test` COMPLET (zéro échec, y compris suites non touchées), PHPStan **sans argument de chemin**, `php artisan migrate --pretend` sur base vide (index ≤ 64 car.), `tsc` + jest sur `mobile/client` et `mobile/provider`.
- **Ne PAS lancer `migrate:fresh` sur une base à conserver.** Migrations **additives** sauf quand un finding exige une suppression de colonne dormante (M-9, low 50/51) — et alors seulement après avoir prouvé zéro lecture/écriture.
- Garde-fous existants verts : `CatalogueDesModulesTest`, `AdminConsoleInventoryTest`, `nativeScreens.test.ts`, tests de thème, tests de joignabilité. Registres à jour (`config/modules.php`, `admin_console.php`, `parity.php`, `features.php`).
- Tout correctif risqué derrière un feature flag si possible. NE TE FIE PAS à `docs/` (sauf l'audit cité) : la vérité est dans le code.
- SQLite en test / MySQL strict en prod : pas de SQL vendor-specific ; `lockForUpdate()` no-op sous SQLite (tester la logique). Livewire ne rejoue pas `mount()` : revérifier les permissions dans chaque action.
- **Chaque correctif doit venir avec un TEST qui échoue AVANT et passe APRÈS** (c'est ainsi qu'on prouve que le bug existait et qu'il est mort). Pour les pages/écrans : test qui fait un vrai GET/press, pas qui lit la source.

---

## LOT 0 — Débloquer le déploiement (le déploiement ne finit jamais aujourd'hui)

- **B1** `config/sentry.php:37-61` : remplacer la closure `before_send` par une classe invokable (`[App\Sentry\BeforeSend::class,'__invoke']`). Smoke-test `php artisan config:cache` en CI.
- **B2** Convertir en contrôleurs invokables toutes les routes à action Closure : `routes/api/public.php:22` (/health), `routes/admin.php:126,140,148,156`, `routes/public.php:44,70,127,131,135,139,157`, `routes/api.php:54`. Puis prouver `php artisan route:cache` OK.
- **B3** `deploy.yml:59-61` : réordonner — `config:cache`/`route:cache` (et leur validation) AVANT `migrate --force` ; sauvegarde base avant migration ; ne migrer que si la config passe.
- **M-13** Créer `config/security.php` + `config/trustedproxy.php` (lisant `env()`), référencer `config(...)` dans `SecurityHeaders.php:33-41` et `TrustProxies.php:21` — sinon ces middlewares voient `null` dès que B1 réussit. Ajouter un test/PHPStan interdisant `env()` hors `config/`.
- **M-14** `Dockerfile:1,14` → `php:8.5-cli` (aligner sur `composer.json` `php ^8.5`).
- **M-15** Ajouter `php artisan storage:link` (idempotent) au script de déploiement.
- **H10** Aligner la file : workers `deploy/supervisor/brio-worker.conf.example` + `deploy/systemd/brio-queue.service.example` sur la même connexion que `.env.production.example:42` (redis). Ajouter la file `notifications` à la liste `--queue` (low 47).
- **H11** Provisionner l'ordonnanceur : timer systemd / cron pour `schedule:run` au premier déploiement + sonde heartbeat (alerte si pas tourné depuis N min).
- **APP_KEY** (low 54) : guard au début du script SSH qui abort si `config('app.key')` vide (jamais `key:generate` en déploiement récurrent).
- **config:parity-check** (M13-audit / low 53) : câbler `php artisan config:parity-check` comme étape bloquante du pipeline ; il doit vérifier `broadcasting.default==='reverb'`, `QUEUE_CONNECTION` ≠ sync, `CACHE_DRIVER` ≠ file, secrets Stripe/webhook non vides.

**Acceptation** : `config:cache` ET `route:cache` réussissent ; un déploiement simulé va au bout ; `migrate` ne tourne jamais si la config échoue ; `config:parity-check` refuse une config prod incomplète ; middlewares sécurité lisent la config cachée.

## LOT 1 — Chemin mobile natif réservation → paiement → dispatch, production-safe (parité web)

Le mobile réserve via `POST /api/client/bookings` : ce chemin doit égaler `OrderJourney` + `DispatchEngine`.

- **B4** `BookingPaymentController.php:50-69` + `PaymentCheckoutScreen.tsx:57-66` : router le paiement mobile par le MÊME service à destination charge que le web (`transfer_data.destination` + `application_fee`, `capture_method=manual`) ; écrire `intent->id` dans `booking.stripe_payment_intent_id` à la création ; ajouter un lookup de secours par `metadata.booking_id` dans `handlePaymentIntentSucceeded` (`StripeWebhookHandlers.php:206`).
- **M2-audit** `BookingPaymentController.php:24-60` : garder contre un booking déjà `authorized`/`captured` (idempotence) — refuser un second PI si `stripe_payment_intent_id` existe déjà.
- **H7** `CreateBookingFromApiAction.php:66-96` : faire passer les DEUX modes par `DispatchEngine::dispatchBooking()` (comme le web), pas seulement `maybeDispatchAsap` — sinon les réservations planifiées mobiles ne sont jamais dispatchées.
- **H8 + M5-audit** `CreateBookingFromApiAction.php:54-92` + `StoreBookingRequest.php` : résoudre et PERSISTER `service_zone_id` et `trade_id` sur la ligne `bookings` (aujourd'hui la zone est transitoire, jamais écrite) ; refuser l'asap si `ZonePricingResolver::allowsImmediate()` est faux. Sans ça `CandidateFinder` ne filtre ni zone ni métier correctement.

**Acceptation** : une réservation immédiate ET une planifiée créées via l'API mobile → booking avec zone+métier persistés, PI à destination charge rattaché, dispatch déclenché par `DispatchEngine`, prestataire payé à la complétion ; test de non-double-débit ; parité prouvée avec un booking web équivalent.

## LOT 2 — Fermer les fuites d'argent

- **H1** `MissionExtraService.php:236-254` : ne passer `CHARGED` qu'après confirmation Stripe réussie (confirmer le PI off_session, ou capturer un PI manuel) ; sinon `approved` + job de reprise idempotent.
- **H2** `CancelBookingService.php:169-205` : pour un booking `authorized`, capturer le fee d'annulation puis annuler le reste du PI (ou annuler+recharger) ; ne pas router l'auth-hold vers `refundMissionPayment` (qui exige `captured`).
- **H3** `ProviderWalletService.php:233-276` + `ExpressPayoutService.php:77-125` : trancher le modèle de payout et exécuter un VRAI `Stripe\Payout`/Transfer sur le compte Connect, écrire `provider_payout_id` pour que `payout.paid` réconcilie — aujourd'hui le ledger est débité sans que l'argent parte.
- **H4** (oubli M1) `StripeWebhookHandlers.php:110-113` : retrouver le booking par `deposit_payment_intent_id` en plus de l'intent principal, sur un remboursement d'acompte (clawback + `payment_status` + compta).
- **H5** `StripeConnectWebhookController.php:35-42` : `STRIPE_CONNECT_WEBHOOK_SECRET` dans le preflight/health ; renvoyer 200 (pas 500) sur secret absent pour ne pas faire retenter Stripe en boucle ; bloquer le go-live si absent (via `config:parity-check`).
- **M-1** `CancelBookingService.php:187` : source unique du montant débité (`booking.payment_amount_cents`, ou `devis_estime ?? estimated_price`) pour refund et no-show, comme `refundMissionPayment`.
- **M-2** `StripeWebhookHandlers.php:136-149` : dénominateur du clawback = total commande (`booking.payment_amount_cents`), pas `charge.amount`, pour les plans multi-charges.
- **M-3** `ProcessStripeWebhookJob.php:20` : `$tries` ≈ 5 avec backoff (garder le cron horaire comme filet).
- **Low 38/39** : devise réelle (`pricing_snapshot`/`booking->currency`) au lieu de `'eur'` en dur (`CommissionService.php:82,131`) ; marquer les `ProviderPayout` auto-transférés comme réconciliés au lieu de `PENDING`, supprimer `captureMissionPayment` mort.

**Acceptation** : tests reproduisant chaque fuite (extra non capturé, annulation auth-hold, withdraw sans transfert, refund d'acompte, mauvais dénominateur) échouent avant / passent après ; aucun montant ni commission comptés deux fois.

## LOT 3 — Fermer les failles de sécurité

- **B5** (+ oubli M3) Webhooks : ne jamais résoudre le provider `mock` en production (`app()->isProduction()`) ; exiger que le segment `{provider}` de l'URL == provider configuré ; `verifyWebhook` du mock échoue en prod ; et dans `KycVerificationService.php:116-125`, refuser un événement sans `resource id` (ne jamais l'appliquer « à la vérification la plus récente »). Couvre KYC, Insurance, SMS.
- **M-4** SVG XSS : `ClientProfileController.php:34-42` + `ProviderOnboardingController.php:58-78` → restreindre aux `mimes:jpg,jpeg,png,webp` (ou `File::image()` sans svg), et/ou servir ces médias en `Content-Disposition: attachment` depuis un domaine sans cookie.
- **Low 41** `User.php:71-109` : retirer `role`/`platform_role`/`organization_account_id`/`current_organization_id` de `$fillable` ; ne les écrire que par `forceFill` dans des flux de confiance.
- **Low 42** `ApiTokensV2Controller.php:79-102` : ajouter `tokenable_type === getMorphClass()` dans rotate ET revoke.
- **Low 43** `routes/channels.php:180-187` : normaliser `providers.presence` sur `isPlatformAdmin()`/`platform_role`, remplacer la branche `dispatcher` (colonne `role` dépréciée) par une permission dédiée.

**Acceptation** : test d'attaque webhook mock rejeté en prod ; upload SVG refusé ; mass-assignment de `platform_role` refusé ; rotate d'un token d'un autre type refusé.

## LOT 4 — Pages publiques + fiabilité du dispatch

- **B0** `pricing.blade.php:7`, `service-trade.blade.php:9,283`, `services-index.blade.php:125` : échapper le JSON-LD `@context` en `@@context` (comme `guest.blade.php`). Ajouter un test `GET assertOk()` réel sur `/pricing` et `/services`.
- **H6** `ClientBookingController.php:218-249` + `MissionDispatchService.php:143-201` : dans `guardAcceptable()`, refuser si booking annulé ; cascader `cancel()` vers `SearchOutcomeService` (retirer les offres live, clôturer la recherche/mission) au lieu d'un `update` nu.
- **H9** `ApplyRecurringTemplateService.php:74,99` : supprimer le second `create()` (double insertion) ; amorcer `next_occurrence_at` à la création (combiner `starts_at` + heure du template) — sinon aucune série ne génère jamais de réservation.
- **M-5** Commande planifiée (chaque minute) qui expire les `mission_assignments` `assigned` dont `expires_at<now` et `exhaust()` les `AsapDispatchRequest` `searching` périmées ; planifier `spine:check-stuck-missions` pour l'alerting.
- **M-6/M-7** `config/queue.php:16` : refuser le boot en prod si `QUEUE_CONNECTION=sync` (l'escalade `->delay()` s'exécute sinon immédiatement et casse les vagues) ; `database` par défaut sûr dans `.env.example`.

**Acceptation** : `/pricing` et `/services` renvoient 200 ; annuler une recherche retire les offres et empêche un accept tardif ; un modèle récurrent génère bien des réservations (une seule série) ; les offres expirées sont balayées même si un job retardé meurt.

## LOT 5 — Notifications & temps réel

- **B6** Push mobile (3 causes) : (a) accepter `provider:'expo'` dans `DeviceTokenController.php:23-31` + `DeviceTokenService`, (b) implémenter un `ExpoPushProvider` serveur (POST `https://exp.host/--/api/v2/push/send`) bindé dans `PushServiceProvider`, OU basculer les apps RN sur de vrais tokens FCM/APNs (`getDevicePushTokenAsync`) ; (c) router au moins les notifications critiques par le canal natif `PushChannel` (aucune ne le fait aujourd'hui) ; (d) ajouter `data.screen`/params côté serveur pour que `useNotificationRouting.ts:24-29` navigue. `device_tokens` : discriminant client/prestataire (low 48).
- **H12** `ops:check-providers --strict` bloquant au déploiement + check provider dans `/api/health` (SMS/Push par défaut `mock`).
- **H13** `config/broadcasting.php:19` : `BROADCAST_CONNECTION=reverb` forcé en prod (via `config:parity-check`) + `refetchInterval` de repli sur `useChatMessages` (chat sans polling aujourd'hui).
- **H14** (oubli M8) `routes/api/public.php:29-30,58-74` : corriger l'agrégation `/health` — Stripe et Reverb sont traités en dépendances DURES malgré le commentaire « soft-fail ».
- **H15** `MissionLifecycleService.php:126-130,169-171,283-285` : `ShouldQueue` sur les notifications de cycle de vie (ou try/catch soft-fail) — une exception SMTP ne doit jamais faire échouer arrivée/démarrage.
- **M-8** Nav société cliente mobile : `CompanyOverviewScreen.tsx:49-57` + `ClientCompanyNavigator.tsx:39-89` — raccourcis d'accueil vers les onglets par navigation profonde, donner une surface réelle à « Pilotage » ; test qui MONTE le navigateur et PRESSE.
- **Low 49** : dériver le compte à rebours d'offre du TTL réel (`config('dispatch.default_timeout')`/`expires_at`), pas « 15 s » en dur.

**Acceptation** : un push natif arrive et navigue sur mobile (test d'enregistrement + envoi) ; `/health` reflète l'état réel des providers ; le chat rafraîchit sans Reverb ; une panne SMTP ne bloque pas une transition de mission ; onglets société cliente joignables en pressant.

## LOT 6 — Fantômes de navigation & dette de schéma

- **H16** (+ oubli M10) Renommer la route API `admin.onboarding.document.file` (ex. `api.admin.onboarding.document.file`) pour que `URL::temporarySignedRoute` résolve vers la route web signée ; vérifier l'aperçu en navigateur, pas seulement via `actingAs()`.
- **M-10** Retirer les deux entrées `presence.me` de `config/modules.php:140,331` (endpoint JSON de polling, pas une page).
- **M-11** `config/modules.php:189` : retirer `admin.automation` (aucun composant, rend un placeholder) ou implémenter `App\Livewire\Admin\AutomationCenter`.
- **M-12** `ModuleCatalogue.php:115-167` : faire évaluer les Gates admin (`Gate::allows`) en plus des permissions d'org — sinon `admin.services`/`admin.teams.partners`/`admin.modules` s'affichent à un admin restreint puis 403 (menu menteur). Appliquer aussi les Gates `manage-*` définis mais jamais posés comme middleware (oubli M11).
- **M-9** `mission_assignments` : après preuve de zéro lecture/écriture, supprimer les colonnes dormantes `status` et `role` (garder `assignment_status`/`role_on_mission` canoniques) ; sinon commenter + documenter dans `schema_drift`.
- **Low 45** Supprimer (ou brancher avec joignabilité prouvée) les 5 écrans prestataire orphelins (`HomeScreen`, `MoreScreen`, `SettingsScreen`, `WalletScreen`, `MissionExecutionScreen`) ; figer la règle par un test de joignabilité prestataire.
- **Low 50/51/52** `RecurringBookingSeries` fillable/casts fantômes ; supprimer les modèles morts `Company` + `QualityAudit` et leurs factories (ou créer les tables) ; réaligner les factories d'argent/promo (`CustomerCredit`, `ReferralReward`, `PromoCodeRedemption`) + test-fumée instanciant chaque factory.
- **Low 46** Deep links à portée d'espace : résoudre l'espace-cible avant rendu, ou ne pas déclarer dans `linking` des chemins jamais montés simultanément.

**Acceptation** : aucun lien de navbar ne mène à un 403/404/placeholder ; aperçu de document admin fonctionne en navigateur ; `migrate --pretend` propre ; instancier chaque factory ne plante pas ; aucun écran orphelin.

## LOT 7 — Durcir la CI (que le vert prouve enfin la prod MySQL)

- **M-16** `ci.yml:113,189` : rendre `money-integrity-mysql` bloquant (retirer `continue-on-error`) une fois le backlog purgé ; faire dépendre le trigger `deploy` de ce job ; E2E bloquant.
- Ajouter les tests manquants nés de ce chantier (pages publiques `assertOk`, non-double-débit, webhook mock rejeté, factories, joignabilité) à la suite CI.

**Acceptation** : la CI échoue si un défaut MySQL-only réapparaît ; le déploiement ne se déclenche que sur une CI verte incluant MySQL-FK + E2E.

---

## BOUCLE DE VÉRIFICATION (après CHAQUE lot)

1. Revue adversariale multi-agents → zéro finding confirmé restant sur le lot.
2. `php artisan test` COMPLET — zéro échec (y compris suites non touchées = preuve de non-régression).
3. PHPStan sans argument de chemin — zéro erreur (re-vérifie qu'il est bien vert au départ).
4. `migrate --pretend` sur base vide — propre, index ≤ 64.
5. `tsc` + jest `mobile/client` + `mobile/provider` — zéro échec ; joignabilité prouvée en pressant.
6. Chaque bug du lot a un test qui échouait avant / passe après.
7. `config:cache` + `route:cache` + `config:parity-check` réussissent (dès le lot 0, et le restent).

## CHECKLIST GO-LIVE (l'arrêt n'est autorisé que TOUT coché)

**Déploiement** : ☐ B1 closure Sentry ☐ B2 closures routes ☐ B3 ordre migrate/config ☐ M-13 env→config ☐ M-14 Docker 8.5 ☐ M-15 storage:link ☐ H10 queue alignée ☐ H11 scheduler ☐ APP_KEY guard ☐ config:parity-check câblé.
**Chemin mobile natif** : ☐ B4 destination charge + rattachement ☐ idempotence paiement ☐ H7 planifié dispatché ☐ H8 zone+métier persistés.
**Argent** : ☐ H1 extras ☐ H2 annulation auth-hold ☐ H3 payout réel ☐ H4 refund acompte ☐ H5 secret webhook ☐ M-1 base refund ☐ M-2 dénominateur clawback ☐ M-3 tries webhook ☐ low 38/39.
**Sécurité** : ☐ B5 mock+KYC ☐ M-4 SVG ☐ low 41/42/43.
**Pages & dispatch** : ☐ B0 pages 500 ☐ H6 annulation cascade ☐ H9 récurrent ☐ M-5 sweep offres ☐ M-6/M-7 queue sync.
**Notifs & realtime** : ☐ B6 push mobile ☐ H12 providers ☐ H13 broadcast ☐ H14 health ☐ H15 mail async ☐ M-8 nav société ☐ low 49.
**Fantômes & schéma** : ☐ H16 route doc ☐ M-9 colonnes doublons ☐ M-10 presence.me ☐ M-11 automation ☐ M-12 gates admin ☐ low 45/46/50/51/52.
**CI** : ☐ M-16 MySQL/E2E bloquants ☐ nouveaux tests ajoutés.
**Global** : ☐ suite complète verte ☐ PHPStan propre ☐ config:cache/route:cache OK ☐ tsc/jest verts ☐ un déploiement simulé va au bout ☐ rien d'existant cassé.

Quand tout est coché : re-lancer l'audit multi-agents de mise en ligne (le même workflow) pour confirmer qu'aucun blocker ne subsiste — c'est la preuve du GO.
