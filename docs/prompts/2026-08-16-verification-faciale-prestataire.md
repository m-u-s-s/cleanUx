# Vérification faciale du prestataire — enrôlement, contrôles aléatoires, appariement pièce d'identité, pilotage admin

Tu travailles sur le monorepo CleanUx : marketplace multi-services (nettoyage, peinture, babysitting, toiture, trajet A→B…), **Laravel 12 / PHP 8.5** + Livewire côté web, monorepo **Expo / React Native** sous `mobile/` (`mobile/client`, `mobile/provider`, package partagé `mobile/shared`). Base **MySQL** en prod, tests **PHPUnit sur SQLite en mémoire**. La plateforme **n'est pas en production** : tu peux optimiser, sécuriser et réécrire, à la seule condition que le code modifié fasse toujours ce pour quoi il a été écrit.

---

# PARTIE I — L'ÉQUIPE

Tu n'es pas un développeur seul. Tu es **une équipe de développeurs seniors**, chacun spécialiste de son domaine, qui travaillent ensemble et **se corrigent les uns les autres**. Rien n'est livré sans passer par le chef d'équipe.

## Les rôles

| Rôle | Responsabilité | Type d'agent |
|---|---|---|
| **Chef d'équipe** | Arbitre, relit tout, approuve ou renvoie au travail. Rien ne part sans son verdict. Il tranche les désaccords. C'est LUI qui décide qu'un lot est terminé. | toi, boucle principale |
| **Architecte** | Frontières de modules, contrats de service, schéma de données, invariants. Refuse une conception qui crée deux sources de vérité. | `ruflo-swarm:architect` |
| **Backend senior (Laravel)** | Migrations, modèles, services, middlewares, jobs, routes, Livewire. Connaît les pièges MySQL/SQLite de ce dépôt. | `ruflo-core:coder` |
| **Mobile senior (RN/Expo)** | `mobile/provider` et `mobile/shared` : navigation, caméra, hooks, intercepteurs, alias × 4. | `ruflo-core:coder` |
| **Graphic designer** | **Vision moderne et culottée, avec une touche luxueuse et gracieuse.** Voir sa charte ci-dessous. Il ne « fait pas joli » : il impose une direction et refuse ce qui la trahit. | skills `high-end-visual-design`, `frontend-design`, `ui-ux-pro-max`, `mobile-design` |
| **Auditeur sécurité** | Cherche le contournement, pas la fonctionnalité. Pose la question « comment je triche ? » à chaque porte. | `ruflo-security-audit:security-auditor` |
| **Ingénieur qualité** | Écrit les tests, y compris **le témoin positif** de chaque test de refus. Traque les tests verts pour une mauvaise raison. | `ruflo-testgen:tester` |
| **Relecteur** | Relit le travail des autres à froid, sans avoir écrit la ligne. Cherche la régression, pas l'élégance. | `ruflo-core:reviewer` |
| **Juriste / RGPD** | Le visage est une **donnée biométrique, catégorie particulière (art. 9 RGPD)**. Il tient le consentement, la durée de conservation, l'effacement et le registre. | boucle principale, avec le skill `gdpr-data-handling` |

## Règles de fonctionnement de l'équipe

1. **Aucun spécialiste ne valide son propre travail.** Le code du backend est relu par le relecteur et l'auditeur sécurité ; les écrans par le graphic designer ET l'auditeur ; les tests par le relecteur.
2. **Le désaccord se tranche par la preuve**, jamais par l'autorité. Quand deux membres s'opposent, on écrit le test qui départage.
3. **Le chef d'équipe approuve à la fin de chaque lot**, avec un verdict explicite : ✅ approuvé / ❌ renvoyé au travail + ce qui manque.
4. **Les lots s'enchaînent sans annonce.** Pas de « je vais maintenant faire le lot 3 ». On travaille, on rend compte à la fin.
5. **On tourne en boucle jusqu'à 100 %.** Tant qu'une case de la checklist finale n'est pas cochée, on n'a pas fini. On ne s'arrête pas sur un « c'est globalement bon ».
6. **Orchestration** : le mot-clé `ultracode` autorise les workflows multi-agents ; les skills `superpowers` (brainstorming → writing-plans → executing-plans → verification-before-completion) portent la méthode ; les plugins `ruflo` fournissent les agents spécialisés. **Le skill `graphify` n'existe dans aucune marketplace installée** — il est remplacé par `high-end-visual-design` + `frontend-design` + `ui-ux-pro-max` + `mobile-design`.

## Charte du graphic designer

**Moderne, culottée, luxueuse, gracieuse.** Ce que ça veut dire concrètement, ici, dans ce dépôt :

- **Culottée** : on ne fait pas un formulaire administratif. Un contrôle d'identité, c'est un moment de tension — l'écran l'assume : plein écran, noir profond, un seul geste possible, un cercle de visée qui respire. Pas de barre de navigation, pas de menu, pas d'échappatoire visuelle.
- **Luxueuse** : la matière avant la décoration. Le dépôt a déjà `GlassSurface` (flou `expo-blur` + voile + arêtes hautes/basses) et `LuxeBackground` (toile Skia unique montée par `NightShell`). On s'en sert. On n'ajoute **aucune** toile Skia supplémentaire.
- **Gracieuse** : `animation.ts` donne `duration.base = 280 ms` et `easing.default = [0.16, 1, 0.3, 1]`. Toute transition les respecte. Le succès n'est pas un `Alert` : c'est `SuccessOverlay`, déjà présent dans `mobile/shared/src/ui`.
- **Interdits** : aucune couleur en dur — tout passe par `useThemeColors()` (`mobile/shared/src/theme/useThemeColors.ts`, la source unique, dont le docblock rappelle que sur 137 fichiers d'interface 7 seulement la consultaient). Aucun `Alert.alert` pour un refus. Aucun écran qui ignore le mode sombre.
- **Côté web / admin** : on réutilise `<x-page-shell>`, `<x-app-card>`, `<x-kpi-card>`, `<x-filter-panel>`, `<x-table-shell>`, `<x-empty-state>`, `<x-badge>`, `<x-toast>` et la couche `.brio-*` de `resources/css/tool-mode.css`. **On ne prend PAS `feature-flags-manager.blade.php` comme modèle** : c'est le seul écran admin en Tailwind brut palette indigo, il est hors charte.
- **Accessibilité** : `useReducedMotion()` et `useScreenReader()` existent — un contrôle d'identité doit rester franchissable sans animation et au lecteur d'écran.

---

# PARTIE II — MÉTHODE DE TRAVAIL IMPOSÉE

- **ANALYSE LE CODE RÉEL. NE TE FIE NI À `docs/`, NI À LA MÉMOIRE, NI AUX README.** `docs/` est ancien et contredit le code (`docs/DATABASE_SCHEMA.md` décrit une table `kyc_documents` **qui n'existe pas**). La vérité est dans `app/`, `routes/`, `database/migrations/`, `config/`, `mobile/`. Toute affirmation de ce document a été vérifiée ligne par ligne le 2026-08-16 — les numéros de ligne peuvent avoir bougé, **re-vérifie avant d'éditer**.
- **CONSIGNE DOMINANTE : NE RIEN CASSER.** On AJOUTE une porte. Les parcours existants (commande, dispatch, mission, clôture, avis, paiement) restent strictement intacts pour tout prestataire hors périmètre du module. Aucune colonne, table, route, méthode ni statut existant n'est supprimé ou renommé. Toute colonne ajoutée est nullable ou à défaut neutre.
- **BOUCLE OBLIGATOIRE** : lot par lot. Après CHAQUE lot, la batterie de vérification ciblée (bas de document). Si ça casse, on corrige et on reboucle sur le lot. **La suite complète ne tourne qu'à la fin** — ou plus tôt si le chef d'équipe juge qu'un changement transverse l'exige.
- **Un lot = une séquence de commits cohérente sur `main`.** Ne pas surveiller la CI sans demande.
- **PHPStan se lance SANS argument de chemin** : `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`. Niveau 6, `paths: [app/]`, baseline de 3 371 entrées.
- **`vendor/bin/pint` avant chaque commit** — la CI le bloque (`--test`).
- **Encadre chaque run d'un `git status`, et n'édite AUCUN fichier pendant qu'une suite tourne.** Le dépôt a déjà connu 1560 échecs pour un `DE ` injecté dans un `use` en session.
- **Ne jamais `| tail` une suite lancée en fond.**
- **PowerShell : ne JAMAIS écrire un fichier PHP avec `-Encoding utf8`** — ça pose un BOM et rend le fichier fatal, l'erreur accusant `namespace`. Utiliser les outils d'édition.

## Les pièges de mesure de ce dépôt (vérifiés, pas théoriques)

1. **Un test de refus exige un témoin positif.** Sans contrôle qui prouve que le chemin passe quand il doit passer, un test « ceci est refusé » vire au vert en mesurant une panne.
2. **SQLite : un identifiant inconnu devient une chaîne littérale.** Une colonne mal nommée ne fait pas échouer le test, elle le fait passer pour une mauvaise raison. Vérifier les migrations avant de transposer PHP → SQL.
3. **`phpunit.xml:44` force `DB_FOREIGN_KEYS=false`.** Les violations d'intégrité référentielle sont invisibles à la suite ; seul le job CI `money-integrity-mysql` les voit.
4. **`tests/TestCase.php:34` pose un `Http::fake()` GLOBAL sur `nominatim.openstreetmap.org/*` qui NE PEUT PAS être supplanté** — Laravel retient le premier motif correspondant, même face à `'*'`. Pour piloter un appel HTTP dans un test, **injecter un faux service**, pas `Http::fake()`.
5. **`lockForUpdate()` est un no-op sous SQLite.** Un verrou concurrentiel ne se prouve pas dans la suite par défaut.
6. **Nom d'index > 64 caractères = migration MySQL cassée, invisible sous SQLite.** Nommer les index explicitement et court.
7. **MySQL réordonne les clés JSON** : ne jamais comparer une colonne JSON castée avec `===`.
8. **Eloquent écarte en silence** : `forceFill` pour toute colonne qui porte une garde ; ne jamais la rendre `$fillable`, ce serait rendre la garde optionnelle.
9. **Livewire ne rejoue pas `mount()`** : chaque action publique revérifie ses gardes, et toute propriété publique servant de garde porte `#[Locked]`.
10. **PHPStan ne lit pas les tableaux de `load()`** : lister le littéral, pas chercher par motif.

---

# PARTIE III — SYNTHÈSE CONCURRENTS

Analyse réelle de la manière de faire d'Uber, Bolt, Deliveroo / Just Eat, et ce qu'on en retient pour CleanUx.

## Uber — « Real-Time ID Check »

- Le conducteur est sollicité **périodiquement**, avant d'accepter une course, pour prendre un selfie comparé à la photo de profil vérifiée.
- **Le déclenchement est aléatoire et non prédictible** — Uber le dit explicitement : « pour éviter la prévisibilité, ces contrôles sont déclenchés à des moments différents ». Aucune cadence n'est publiée, et c'est délibéré.
- Un **système de risque** augmente la fréquence pour un compte donné en cas de signaux de fraude — notamment **plusieurs appareils liés à un même compte**.
- En cas de non-correspondance, **le compte est temporairement bloqué** le temps de l'enquête humaine.
- La comparaison est déléguée à un tiers (Microsoft Cognitive Services à l'origine).

**Ce qu'on retient** : le déclencheur est serveur, aléatoire, invisible du client ; le risque module la fréquence ; l'échec bloque et appelle un humain.

## Bolt — « Driver Selfie Check » + Liveness

- Le selfie est demandé **au passage en ligne**, pas à la connexion. C'est le bon moment : c'est là que le prestataire déclare vouloir travailler.
- **Détection de vivacité (« liveness ») obligatoire** : un selfie *live*, pas une photo d'une photo. Sans ça, le contrôle ne prouve rien.
- Cible explicite : les « tenant drivers » — des comptes loués à la journée à quelqu'un qui n'a passé aucun contrôle. Bolt a durci après un cas mortel d'usurpation de profil en Afrique du Sud.
- En cas d'échec, le conducteur **est empêché d'offrir ses services** jusqu'à re-vérification.
- L'onboarding lui-même est délégué à Veriff.

**Ce qu'on retient** : la porte est « passer en ligne » ; la vivacité n'est pas une option ; la cible n'est pas le fraudeur exotique, c'est la location de compte — **exactement le risque d'un prestataire salarié d'une société**.

## Deliveroo / Uber Eats / Just Eat — la substitution

- Les plateformes de livraison autorisent la **substitution** (un coursier envoie quelqu'un à sa place) — et c'est par là que passe la fraude massive au droit de travail.
- Réponse de Deliveroo : le substitut doit être **enregistré nominativement dans l'app**, et **le titulaire du compte doit lui-même passer un contrôle d'identité** pour prouver qu'il contrôle encore son compte.
- Été 2025 : les trois plateformes britanniques ont **augmenté la fréquence** des vérifications faciales face à l'ampleur du partage de comptes.

**Ce qu'on retient** : ce n'est pas la fraude qu'on mesure, c'est **la possession du compte**. Et la bonne réponse à un abandon répété n'est pas de bloquer tout de suite — c'est d'alerter, puis de bloquer si ça se répète.

## Contrainte légale — non négociable

Le visage est une **donnée biométrique** au sens de l'**article 9 RGPD** : catégorie particulière. Conséquences directes sur la conception :

- Il faut une base légale de l'**article 6** ET une condition de l'**article 9(2)** — en pratique le **consentement explicite**, recueilli, horodaté, versionné, et **retirable**.
- Une **AIPD / DPIA est obligatoire** avant la mise en service (la reconnaissance faciale y tombe systématiquement).
- **Minimisation et durée courte** : on ne garde pas les selfies de contrôle indéfiniment.
- Le consentement en contexte de travail est fragile → **il faut un chemin de remédiation humain** (le contrôle manuel admin exigé par la mission n'est pas un confort, c'est une obligation).

---

# PARTIE IV — ÉTAT DES LIEUX VÉRIFIÉ

## Ce qui n'existe pas (vérifié par grep exhaustif sur `app/ config/ database/ routes/ resources/ tests/ mobile/`)

**Il n'y a AUCUNE capture de visage, AUCUN stockage de selfie, AUCUN contrôle de vivacité, AUCUN gabarit biométrique, AUCUN appel SDK, AUCUNE colonne dédiée.**

- `selfie` : 2 occurrences, **du texte** — un commentaire dans `config/kyc.php:58` et une phrase d'interface dans `resources/views/livewire/provider/provider-kyc-page.blade.php:102`.
- `liveness` : 3 occurrences, **toutes le health-check Kubernetes** (`app/Http/Controllers/HealthCheckController.php:20`).
- `biometric` / `biométrie` / `photo_identite` : **zéro**.
- `facial` : 6 occurrences, **toutes des chaînes déclaratives** — la constante `KycCheck::TYPE_FACIAL_SIMILARITY` (`app/Models/KycCheck.php:15`), la clé dans `config/kyc.php:58` `standard_checks`, la migration, des factories.

**Le seul « facial » du dépôt est un mot envoyé à un tiers qui ne le traite jamais.**

## Deux défauts existants à corriger au passage (ils sont sur le chemin)

1. **`OnfidoProvider::mapCheckResponse()`** (`app/Services/Kyc/Providers/OnfidoProvider.php:157-163`) **écrase TOUS les `report_ids` en `KycCheck::TYPE_DOCUMENT`.** Un résultat de similarité faciale rendu par Onfido est aujourd'hui enregistré comme un check « document » : le type est perdu.
2. **`OnfidoProvider::startVerification()`** (`:36-54`) ne crée qu'un *applicant* et renvoie `externalCheckId: null` **et `hostedFlowUrl: null`** — commenté « Skeleton » en `:17-20`. Or `mobile/provider/src/screens/onboarding/steps.tsx:291` fait `Linking.openURL(data.hosted_flow_url)`. **Aucune image n'atteint donc jamais le fournisseur, et l'étape KYC mobile ouvre `undefined` dès qu'on quitte le mock.**

## Le KYC existant — le patron à copier

`app/Services/Kyc/KycProviderInterface.php` — 5 méthodes : `name()`, `startVerification(KycStartRequest): KycStartResult`, `fetchStatus(KycVerification): KycStatusResult`, `verifyWebhook(string, array): array` (**doit throw si signature invalide**), `mapWebhookEvent(array): ?KycStatusResult`.

Sélection du fournisseur : `app/Providers/KycServiceProvider.php:15-42`, avec un **auto-upgrade implicite** — si `config('kyc.default_provider') === 'mock'` mais qu'un `ONFIDO_API_TOKEN` est présent, il bascule sur `onfido` (`:32-35`). `veriff` / `sumsub` sont configurés mais **non implémentés** (`RuntimeException` en `:40`).

Webhooks : `routes/public.php:181-182` → `POST /webhooks/kyc/{provider}`, hors auth ; `KycWebhookController` (idempotence `firstOrCreate` sur `(provider, external_event_id)`, repli `sha256(payload)`) ; queue **`kyc-webhooks`** ; `ProcessKycWebhookJob` (`tries=3`, `backoff [30,120,300]`).

`KycStatusResult.php:15` documente déjà la forme d'un check :
`array{type:string, result:string, sub_result?:string, confidence?:float, breakdown?:array, external_id?:string}` — **`confidence` et `breakdown` sont le point d'extension prévu pour un résultat facial.**

## Stockage de fichiers

- Disque **`private`** (`storage/app/private`, `visibility: private`) — `config/filesystems.php`.
- Écriture de référence : `app/Services/Onboarding/ProviderOnboardingService.php:141` → `$file->store("providers/{$user->id}/onboarding/{$type}", 'private')`.
- Lecture : `app/Http/Controllers/Admin/OnboardingDocumentController.php:23-42`, route `routes/admin.php:585-587` sous middleware **`signed`**, TTL **10 minutes** (`AdminOnboardingDocumentsCenter.php:242-246`), double contrôle `abort_unless($user->isPlatformAdmin(), 403)`.
- ⚠️ **La photo de profil du prestataire est PUBLIQUE** : `ProviderOnboardingService.php:82-84` → `store(..., 'public')`. C'est aujourd'hui le seul portrait stocké, accessible sans authentification via `/storage/...`. **Le visage de référence ne doit surtout pas suivre ce chemin.**
- **Il n'existe AUCUNE Policy** (`app/Policies` ne contient rien pour document/kyc/provider) : l'autorisation est faite à la main dans le contrôleur. Et **aucune route ne permet au prestataire de relire son propre document.**

## Chiffrement

`app/Casts/` contient exactement **2 fichiers** : `EncryptedStringFallback` et `EncryptedArrayFallback` (chiffrent au `set()`, retombent silencieusement sur du clair legacy au `get()`). Utilisés sur 5 colonnes / 3 modèles. Le cast natif `'encrypted'` n'est utilisé qu'une fois (`GoogleCalendarConnection`).
⚠️ **`kyc_checks.breakdown`, `kyc_webhook_events.payload` et `provider_onboarding_documents.metadata` sont en CLAIR.**

## Les 7 points de passage « le prestataire part chez le client »

| # | Point | Fichier:ligne | Ce qu'il couvre |
|---|---|---|---|
| 1 | `CandidateFinder::baseQuery()` — à côté de `verification_status` | `app/Services/Dispatch/CandidateFinder.php:249` | **Toutes** les offres marketplace (immédiat + planifié + simulateur). Pas l'assignation société. |
| 2 | `DispatchEngine::createOffer()` — à côté de `hasClearedKyc()` | `app/Services/Dispatch/DispatchEngine.php:517` | Toute offre émise. **Pas `assignByDefault()` `:688-741`, qui écrit directement sans passer par `createOffer()`.** |
| 3 | `MissionDispatchService::guardAcceptable()` | `app/Services/Dispatch/MissionDispatchService.php:357` | L'acceptation, les deux routes (`assignments/{id}/accept` et `asap-offers/{id}/accept`) convergent ici. |
| 4 | `MissionLifecycleService::setEnRoute()` | `app/Services/Missions/MissionLifecycleService.php:64` | **Le « je pars chez le client » exact.** Les 3 surfaces (API mobile, web session, Livewire) y convergent. Seule garde actuelle : `assertAssignedToMission()`. |
| 5 | `ProviderPresenceService::goOnline()` — **DEUX services** | `app/Services/Presence/ProviderPresenceService.php:37` (v2) **et** `app/Services/Provider/ProviderPresenceService.php:36` (legacy) | La mise en ligne. **Traiter les deux, sinon contournable par l'autre route.** Aucune des deux ne vérifie quoi que ce soit aujourd'hui. |
| 6 | Middleware sur le groupe `routes/api/provider.php:102` | | Toute la surface API prestataire. Pas le web (`routes/employe.php`, `routes/missions.php`) ni `api/provider/company/*`. |
| 7 | `MissionAssignmentService::assigner()` | `app/Services/Missions/MissionAssignmentService.php:34` | **Le seul chemin qui échappe AUJOURD'HUI à toute garde KYC et conduite** : assignation interne société + `InternalAutoAssignmentEngine`. |

## Le patron de refus à copier littéralement

`app/Http/Middleware/EnsureProviderIsApproved.php` (alias `provider.approved`, `app/Http/Kernel.php:122`) :
- Périmètre restreint : ne s'applique qu'aux profils portant `self_registered_at` (`:43`) — les anciens traversent sans condition.
- **API** : `403 {"ok": false, "error_code": "provider_pending_approval", "message": "..."}`.
- **Web** : `redirect()->route('provider.onboarding')->with('warning', $message)`, avec repli `home`.
- **Exemptions explicites** par `withoutMiddleware('provider.approved')` — `routes/api/provider.php:215` (KYC/profil/Stripe), `:330` (onboarding), `routes/employe.php:52,63,89,101,187`.

Second patron, à deux étages, encore plus proche : `app/Services/Dispatch/ConduiteRequirements.php` — **filtre SQL éliminatoire** `appliquerAuxCandidats()` (`:112-158`) + **méthode qui nomme ce qui manque** `manquantsPour()` (`:165-202`) + **période de grâce configurable** `bloquantDepuis()` (`:71-97`).

## Admin — trois registres nommés « modules », ne pas les confondre

| Registre | Objet | Écran |
|---|---|---|
| Table `platform_modules` + `PlatformModuleResolver` | Activation / audience d'une capacité produit | `/admin/modules` → `PlatformModulesCenter` |
| `config/modules.php` | Catalogue de **navigation** (tuiles) | `/admin/modules-directory` → `ModulesDirectory` |
| `config/admin_console.php` | Couverture de la console mobile admin | (mobile), gardé par un test |

**Fait capital : `PlatformModuleResolver::isEnabledFor()` n'est appelé NULLE PART en production** — ses seules occurrences sont dans `tests/Unit/PlatformModuleResolverTest.php`. Le gating réellement emprunté est `feature()` → `config/features.php` + table `feature_flag_overrides`. Le resolver sait pourtant déjà faire du rollout **par zone** (`rollout_strategy = 'zone'`, `allowed_zone_ids`) — c'est exactement ce dont ce module a besoin. **Ce module sera son premier consommateur en production.**

⚠️ **Piège : `PlatformModulesCenter::save()` (`:122-130`) REMPLACE `settings` en entier.** Tout réglage métier qu'on y range serait effacé au prochain enregistrement d'audience. Il faut le rendre **fusionnant** avant d'y écrire quoi que ce soit.

⚠️ Second piège : `toggleEnabled()` refuse d'agir sur un module `is_locked` (`:148-152`), **mais `save()` ne vérifie pas le verrou** (`:111-113` est un `if` vide) — `is_enabled` reste modifiable par le formulaire sur un module verrouillé.

## Les 4 tests-gardiens qui casseront si on oublie une déclaration

1. `tests/Feature/Navigation/CatalogueDesModulesTest.php:32` — toute route GET `^(admin|dashboard)(/|$)` sans paramètre doit avoir une entrée dans `config.modules.catalogue` **ou** dans `non_modules` avec sa raison.
2. Même fichier `:52` — aucune tuile ne doit pointer vers une route inexistante ; `:73-79` — `context` et `category` contraints.
3. `tests/Feature/Admin/AdminConsoleInventoryTest.php:18` — toute route GET `admin/*` doit avoir une entrée dans `config/admin_console.php` (`coverage: pending` suffit).
4. `tests/Feature/Navigation/CoherenceDesTuilesEtDesEcransTest.php` — frappe les routes pour **11 sous-rôles** et exige que la permission de la tuile soit **identique** à celle de l'écran.

## Alertes admin — le patron

Il n'existe **ni table `alerts`, ni `admin_alerts`, ni `risk_events`**. Deux mécanismes :
- **Calculé à la volée** : `app/Services/Admin/AdminAlertService.php:15` → 7 requêtes Eloquent, consommé par `AdminAlertsCenter` sur `/admin/alerts`. Aucune persistance, aucun accusé de réception.
- **Notification Laravel** : `app/Services/Safety/SafetyAlertService.php:242-256` est **la référence pour cibler tous les admins** — `User::whereIn('platform_role', ['admin','super_admin'])->where('is_active', true)`, `Log::warning` si la liste est vide, `Notification::send($admins, new SafetyAlertRaised(...))`. La notification déclare `via() = ['database', 'mail']` et **n'est délibérément pas `ShouldQueue`** (docblock `:13-17`).

**File d'attente de revue manuelle** — le patron exact est `app/Livewire/Admin/Risk/RiskCenter.php` sur `RiskHold` : onglets `pending | history`, méthodes `approve(int)` / `reject(int)` déléguant au service, colonnes `reviewed_by_user_id`, `reviewed_at`, `review_notes`, retour utilisateur par `$this->dispatch('toast', ...)`.

Respect des préférences de notification : `use InteractsWithUserNotificationPreferences;` + `return $this->preferredChannels($notifiable, '<event_key>', ['mail','database']);` dans `via()`. Une clé d'événement inconnue retombe sur `transactional` — **le doute profite à l'envoi**.

## RGPD — l'état réel

- **Aucun registre de catégories de données, aucun modèle de consentement, aucune colonne `legal_basis`.** Les deux seuls endroits qui énumèrent de facto des catégories sont `DataExportService::collectFor()` (`:73-92`) et `config/gdpr.php:38-46` (`retention`).
- **`RetentionPolicyService::enforceAll()` ne purge que 7 cibles** — aucune n'est KYC ni document d'identité.
- **`DataErasureService` ne touche ni `kyc_verifications`, ni `kyc_checks`, ni `kyc_webhook_events`, ni `provider_onboarding_documents`.** Après un droit à l'oubli exécuté, la carte d'identité scannée reste intacte sur le disque.
- `DataExportService::collectKyc()` (`:208-219`) n'exporte que 9 colonnes et **omet totalement les documents**.

## Mobile — les points d'insertion

- **`resolveSpace()`** — `mobile/provider/src/admin/space.ts:66-139` — est **le point d'insertion naturel d'un état bloquant**. Il rend déjà `'providerOnboarding'` (`:134-135`), et `RootNavigator.tsx:442-446` ne monte alors **qu'un seul écran** : « Dossier incomplet : rien d'autre n'est atteignable. » C'est le patron du blocage dur. **`undefined` (chargement ou erreur) laisse passer** — une panne réseau ne doit pas enfermer l'utilisateur.
- **L'intercepteur ne traite QUE le 401** — `mobile/shared/src/api/client.ts:73-121`. Aucun traitement global d'un 403 ni d'un `error_code` métier. Le bus d'événements `emitSessionExpired()` / `onSessionExpired()` (`:12-31`) est le modèle : il faut un **second émetteur** à côté.
- **Caméra** : `expo-camera ~57.0.3` est installé et déjà utilisé — `PresenceScanScreen.tsx` (`useCameraPermissions`, les 3 états permission, `CameraView` en `StyleSheet.absoluteFill`). ⚠️ **`CameraView` n'accepte plus d'enfants** : la visée est posée en `position: absolute` à côté, avec `pointerEvents="box-none"`. `react-native-vision-camera` est **absent**.
- **Permission iOS à corriger** : `mobile/provider/app.json:22` dit `NSCameraUsageDescription: "brio Provider uses the camera to scan and display QR codes."` — mensonger dès qu'on prend un selfie.
- **QUATRE tables d'alias** doivent rester synchronisées : `babel.config.js:6-36`, `tsconfig.json:10-91`, `jest.config.ts:43-105`, `metro.config.js:6-20`. Un alias oublié fait tomber des dizaines de suites (46 sur 66 côté client, historiquement).
- Chargement paresseux obligatoire des modules natifs : `mobile/provider/src/screens/onboarding/documentPicker.ts:31-47` fait un `require()` **littéral** dans un `try/catch` — `require(variable)` casse le bundling Metro.

---

# PARTIE V — LA MISSION

## Décisions produit prises (ne pas les rouvrir)

| Sujet | Décision |
|---|---|
| Moteur de comparaison | **Interface + fournisseur mock déterministe + adaptateur Onfido**, sur le patron exact de `app/Services/Kyc`. Onfido est déjà configuré (`config/services.php`, `ONFIDO_API_TOKEN`). |
| Conséquence d'un échec | **Blocage dur**, levée par un **admin seul**. Le bouton « ça ne marche pas » ouvre un ticket admin horodaté avec les diagnostics, mais **ne débloque rien**. Zéro vecteur de contournement. |
| Périmètre | **Activable par métier ET par zone.** Pas de contrôle global imposé. |
| Cadence | **Au plus un contrôle par 24 h, au moins un tous les 3 jours, moment tiré au sort côté serveur.** |
| Vivacité | **Obligatoire.** Sans elle, une photo d'une photo passe et le module ne prouve rien. |

## Les 10 consignes (toutes OBLIGATOIRES)

1. **Enrôlement à l'inscription.** Un prestataire enregistre son visage de référence lors de son inscription, avec **consentement explicite** horodaté et versionné. Web et mobile : parité totale. L'enrôlement bloque l'**activation**, pas la création du compte.
2. **Contrôle récurrent avant d'aller chez un client.** Le prestataire repasse un contrôle facial **avant de pouvoir passer en ligne, accepter une mission, ou déclarer qu'il part chez le client**.
3. **Cadence non systématique et non prévisible.** Au plus 1 contrôle par 24 h ; au moins 1 tous les 3 jours ; **la date exacte est tirée au sort côté serveur au moment du contrôle précédent, et n'est JAMAIS renvoyée au client**. Un prestataire ne doit pas pouvoir prévoir le prochain contrôle.
4. **Déclencheurs de risque** (façon Uber) : nouvel appareil, échecs récents, abandons répétés, signalement de fraude, ou forçage admin déclenchent un contrôle **hors cadence**.
5. **Appariement automatique visage ↔ pièce d'identité**, à partir du document déjà présent dans `provider_onboarding_documents` (`identity_card` / `passport` / `residence_permit`). Un verdict `match | mismatch | inconclusive` avec un score.
6. **Contrôle manuel admin**, toujours. L'admin voit la file d'attente, compare, approuve, refuse, force un contrôle, révoque un visage de référence, lève un blocage. Sa décision **prime toujours** sur l'automatique et est tracée.
7. **Pilotage dans la page module.** Le module apparaît dans `/admin/modules`, activable / désactivable, avec son audience par zone. Un écran dédié porte les réglages fins (intervalles, seuils, rétention) et la file de revue.
8. **Alertes graduées.** Un prestataire qui **quitte avant de terminer** un contrôle n'alerte **pas la première fois**. Au-delà d'un seuil configurable dans une fenêtre configurable, un incident est ouvert et les admins sont notifiés. Si c'est systématique, l'incident monte en **fraude possible** (sévérité critique).
9. **Signalement de panne par le prestataire.** Un bouton « le contrôle ne fonctionne pas » ouvre un incident avec les diagnostics techniques (appareil, OS, version d'app, état de la permission caméra, dernière erreur). **Il ne débloque pas** ; seul un admin lève.
10. **Homogénéité.** Le module est branché sur les tables et services existants — `provider_profiles`, `provider_onboarding_documents`, `kyc_checks`, `platform_modules`, `trades`, `provider_presence`, dispatch, audit, notifications, RGPD — sans créer de seconde source de vérité et **sans casser un seul parcours existant**.

## Architecture retenue

### Schéma de données — 3 tables

**`provider_face_profiles`** — le visage de référence, un par prestataire (`user_id` unique).
`status` (`pending|enrolled|rejected|revoked`), `reference_path`, `reference_hash`, `external_face_id`, `captured_at`, `captured_ip_hash`, `captured_device_name`,
`consent_given_at`, `consent_version`, `consent_withdrawn_at`,
`id_document_id` (FK `provider_onboarding_documents`, nullable), `id_match_status` (`pending|match|mismatch|inconclusive|manual_override`), `id_match_score`, `id_match_checked_at`,
`next_check_due_at` (**jamais exposé au client**), `last_check_at`,
`blocked_at`, `block_reason`, `unblocked_at`, `unblocked_by_user_id`,
`reviewed_by_user_id`, `reviewed_at`, `review_notes`, `metadata` (chiffré).

**`provider_face_checks`** — un contrôle.
`trigger` (`enrollment|interval|risk_device|risk_failures|risk_abandons|admin_forced`), `status` (`pending|passed|failed|abandoned|expired|error`), `decision_source` (`auto|manual`), `score`, `liveness_result` (`pass|fail|unknown`), `provider`, `external_check_id`, `selfie_path` (nullable, purgé), `selfie_purged_at`, `attempt_number`, `requested_at`, `answered_at`, `expires_at`, `ip_hash`, `device_name`, `app_version`, `failure_reason`, `raw` (chiffré), `reviewed_by_user_id`, `reviewed_at`, `review_notes`.

**`provider_face_incidents`** — signalements et alertes.
`type` (`provider_report|repeated_abandon|repeated_failure|liveness_fail|id_mismatch`), `severity` (`info|warning|critical`), `status` (`open|acknowledged|resolved|dismissed`), `message`, `diagnostics` (json), `acknowledged_by_user_id`, `acknowledged_at`, `resolved_by_user_id`, `resolved_at`, `resolution`, `resolution_note`.

> **Pas de 4e table pour le blocage** : l'état courant vit sur le profil, l'historique dans `provider_face_checks` + `provider_face_incidents` + l'audit. Une table de plus serait une seconde source de vérité.

### Activation par métier et par zone

- **Métier** : nouvelle colonne `trades.requires_face_check` (boolean, défaut `false`), sur le patron strict de `requires_certification` / `requires_insurance_proof` / `requires_site_visit`. Éditable dans le formulaire métier partagé (`app/Support/Livewire/Concerns/Admin/ManagesTradeForm.php` + `resources/views/livewire/admin/partials/trade-form-fields.blade.php`).
- **Zone** : le module `security.face_check` dans `platform_modules`, `rollout_strategy = 'zone'`, `allowed_zone_ids`. **Premier consommateur de production de `PlatformModuleResolver`.**
- **Résolution** : un service unique `FaceCheckRequirement` répond à « ce prestataire, pour ce métier / cette zone / cette réservation, est-il soumis ? ». **Personne d'autre ne décide.**

### Services

`app/Services/FaceCheck/` :
- `FaceMatchProviderInterface` — `name()`, `enroll()`, `verify()` (score **+ vivacité**), `compareWithDocument()`.
- `Providers/FaceMatchMockProvider` — **déterministe** (dérivé d'un hash), pour que les tests soient stables.
- `Providers/OnfidoFaceMatchProvider` — réutilise `ONFIDO_API_TOKEN`.
- `FaceCheckRequirement` — soumis ou pas (métier × zone × module × type de prestataire).
- `FaceCheckScheduler` — tirage au sort de `next_check_due_at`, déclencheurs de risque. **Jamais de cron pour créer un contrôle : l'évaluation est *pull*, à la porte.** Le cron ne sert qu'à expirer, purger et escalader.
- `FaceCheckService` — ouvrir un contrôle, y répondre, décider, bloquer, lever.
- `FaceCheckIncidentService` — ouvrir, escalader, notifier.
- `FaceIdDocumentMatcher` — appariement avec la pièce d'identité (⚠️ **un PDF ne se compare pas** → `inconclusive` + revue manuelle).

### Les portes

Un middleware `face.verified` sur le patron **exact** de `EnsureProviderIsApproved` (`error_code: face_check_required` / `face_check_blocked`, redirection web vers le parcours de remédiation, exemptions par `withoutMiddleware`), **plus** les gardes de service aux 7 points de passage listés en Partie IV. Le middleware seul ne suffit pas : il ne couvre ni le web, ni `api/provider/company/*`, ni `assignByDefault()`.

### RGPD — les 5 points à câbler

1. Consentement explicite recueilli, horodaté, versionné, **retirable** (le retrait révoque le visage de référence et bloque — c'est la conséquence, elle doit être annoncée).
2. `config/gdpr.php` : nouvelle clé de rétention `face_check_selfie_days` (défaut **30**).
3. `RetentionPolicyService::enforceAll()` : purge des selfies de contrôle **et suppression des fichiers sur le disque**, sur le modèle de `purgeExpiredExports()` (`:86-114`). Seuls le verdict et le score survivent.
4. `DataErasureService::anonymizeUser()` : **suppression immédiate** du visage de référence et des selfies (pas anonymisation — une donnée biométrique n'a aucune obligation de conservation comptable).
5. `DataExportService::collectFor()` : nouvelle section exportant les **métadonnées** (dates, verdicts, scores), **jamais les images ni les gabarits**.

### Les écrans

- **Mobile prestataire** : écran bloquant plein écran via `resolveSpace()`, `CameraView` en façade avant, cercle de visée respirant, consigne de vivacité, `SuccessOverlay` au succès, et le bouton « ça ne marche pas » toujours atteignable.
- **Web prestataire** : même parcours en Livewire + `getUserMedia`, parité totale.
- **Admin** : `/admin/verification-faciale` — onglets **À revoir** (file d'attente), **Incidents**, **Alertes fraude**, **Historique**, **Réglages**. Patron `RiskCenter`.
- **Web/mobile client** : rien. Le client ne voit jamais le visage d'un prestataire dans ce module.

---

# PARTIE VI — BATTERIE DE VÉRIFICATION

## Après CHAQUE lot (ciblé, rapide)

```
git status                                   # avant
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php artisan test --filter=<le lot>
git status                                   # après
```

## À la fin seulement (ou si le chef d'équipe le juge nécessaire)

```
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan migrate:fresh --seed              # sur MySQL, pas seulement SQLite
cd mobile/provider && npx tsc --noEmit && npm test
cd mobile/client   && npx tsc --noEmit && npm test
```

## Checklist finale — on ne s'arrête que lorsque TOUT est coché

- [ ] Les 10 consignes de la Partie V sont satisfaites, chacune prouvée par un test nommé.
- [ ] **Chaque test de refus a son témoin positif.**
- [ ] La cadence est prouvée non prévisible : un test montre que `next_check_due_at` n'est renvoyé par **aucune** réponse d'API.
- [ ] Les 7 points de passage sont gardés — y compris `assignByDefault()` et `MissionAssignmentService::assigner()`.
- [ ] Un prestataire **hors périmètre** (métier ou zone non soumis) traverse tous les parcours **sans jamais voir le module** — prouvé.
- [ ] `PlatformModulesCenter::save()` fusionne les réglages au lieu de les remplacer, et respecte `is_locked`.
- [ ] Les 4 tests-gardiens de navigation / console admin passent.
- [ ] Les 5 points RGPD sont câblés, avec un test de purge et un test d'effacement qui vérifient que **le fichier a disparu du disque**.
- [ ] `OnfidoProvider::mapCheckResponse()` ne collapse plus les types de rapport.
- [ ] Les 4 tables d'alias mobiles sont synchronisées ; `tsc` et `jest` verts sur les deux apps.
- [ ] `NSCameraUsageDescription` décrit la vérification d'identité.
- [ ] Le graphic designer a validé chaque écran : mode sombre, tokens, `GlassSurface`, mouvement réduit, lecteur d'écran.
- [ ] Suite complète verte, PHPStan propre sans nouvelle entrée de baseline, Pint propre, `migrate:fresh --seed` propre sur MySQL.
- [ ] **Verdict explicite du chef d'équipe.**
