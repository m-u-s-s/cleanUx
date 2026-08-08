# Chantier « Société prestataire » — RBAC par sous-rôle, dispatch automatique, multi-sites, messagerie & appels (web + mobile natif)

Tu travailles sur le monorepo CleanUx : marketplace multi-services (nettoyage, peinture, babysitting, toiture…), Laravel 11 + Livewire côté web, monorepo Expo/React Native sous `mobile/` (app prestataire = `mobile/provider`, package partagé = `mobile/shared`, alias `@/ui`, `@/api`, `@/chat`, `@/realtime` → `../shared/src/*`). Base MySQL en prod, tests PHPUnit sur SQLite.

**Périmètre strict : le rôle `provider_company` uniquement.** Toute modification se fait côté web ET en NATIF dans `mobile/provider` (pas de WebView pour ces écrans). On conserve les deux espaces mobiles existants : espace terrain (`TabNavigator`, 4 onglets) et espace société (`ProviderCompanyNavigator`, 6 onglets).

## Les 8 exigences produit

1. **RBAC strict par sous-rôle** : chaque membre n'accède qu'à ce que son rôle permet. Un `worker` ne voit QUE les missions qui lui sont assignées. L'`owner` change les sous-rôles de ses employés quand il veut.
2. **Bouton « assignation automatique »** pour l'owner : les missions non assignées vont automatiquement au worker le plus disponible — plus un mode continu (toute nouvelle mission de la société est auto-assignée).
3. **Multi-sites** : gérer les sites clients desservis (assigner une équipe OU un worker par site) ET des agences propres de la société (nouvelle entité). L'owner peut changer date, heure et LIEU d'un rendez-vous.
4. **Messagerie puissante intra-société** (inspiration Discord/WhatsApp) : créer une conversation et ajouter/retirer des participants en deux gestes, DM, temps réel, non-lus, notes vocales ; puis vrais appels audio/vidéo (LiveKit) en phase 2.
5. **Réassignation** : owner, chef d'équipe et responsables peuvent réassigner une mission entre workers (le chef d'équipe : au sein de SON équipe seulement).
6. **Espace de gestion des rôles** : owner + rôles habilités ajoutent/retirent/changent les sous-rôles des membres, y compris depuis le mobile, et peuvent régler la matrice rôle→permissions de LEUR société.
7. **Mobile natif** : tout ce qui précède existe en natif dans `mobile/provider`, dans les deux espaces conservés.
8. **Espace terrain adapté au sous-rôle** : un worker ne peut ni voir ni suivre une mission qui ne lui est pas assignée — ni à l'écran, ni via l'API.

## Règles non négociables

- Travail direct sur `main`. Un lot = une séquence de commits cohérente et vérifiable. Ne pas surveiller la CI sans demande.
- Migrations **non destructives et idempotentes** uniquement : combler des NULL, ajouter tables/colonnes ; ne rien renommer ni supprimer. Noms d'index EXPLICITES ≤ 64 caractères (motif `psa_org_site_user_unique`) ; vérifier via `php artisan migrate --pretend`.
- La suite tourne sur SQLite, la prod sur MySQL strict : pas de SQL vendor-specific, backfills en `chunkById`. `lockForUpdate()` est un no-op sous SQLite — tester la logique, pas le verrou.
- PHPStan à lancer **SANS argument de chemin** avant chaque fin de lot.
- Livewire ne rejoue PAS `mount()` : **chaque action publique revérifie sa permission** (motif `TeamManagement`).
- Toute lecture est scopée organisation DANS la requête (jamais « charger puis 403 ») — motif `CompanyController::canalSousGarde()`.
- Résoudre l'organisation via `organizationContextId()` / trait `App\Support\Organizations\ResolvesActiveOrganization`, jamais `currentOrganization` seul.
- Mobile : un espace = un navigateur rendu hors des autres piles ; écrans profonds sur la pile racine ; **ne jamais monter `usePresenceHeartbeat()` hors espace terrain** (un gérant apparaîtrait disponible au dispatch). Thème : fabriques `stylesFor(useThemeColors())`, zéro couleur en dur (gardé par `__tests__/theme/noHardcodedColors.test.ts`).
- Tests de joignabilité mobile : **PRESSER** (`fireEvent.press` depuis le navigateur monté), jamais lire la source d'un fichier de navigation (modèle `SortieDeLEspaceSociete.test.tsx`). Un écran monté n'est pas un écran atteignable.
- Ne PAS recopier de matrice de permissions côté client : le mobile applique ce que le serveur déclare, défaut-refus si champ absent.

## État des lieux vérifié (à connaître avant d'écrire une ligne)

**Rôles.** 11 sous-rôles dans `app/Enums/OrganizationRole.php` (owner 100, operations_manager 80, manager 80, dispatcher 60, site_manager 60, quality_manager 50, finance 50, team_lead 40, requester 20, worker 20, viewer 10). `canManage()` = rang STRICTEMENT supérieur. `app/Services/PermissionService.php` résout en 3 étages : `organization_members.permissions` (JSON nominatif) > table `organization_role_permissions` (matrice PAR société — **lue mais aucun écran/API ne l'écrit**) > constante `ROLE_PERMISSIONS` (35 clés). Cache 60 s + `invalidateOrganizationCache()`. Un worker n'a par défaut que `channels.create` + `tasks.create`.

**Trous RBAC actuels** (à fermer, c'est l'exigence 1) : `SiteOperations` sans `mount()` ni garde de lecture (`sites.view_all` déclarée, jamais consultée) ; `ProviderDashboard::getTeamStatusProperty()` + KPIs `members_active`/`pending_tasks` non filtrés ; `TaskBoard` expose toutes les tâches ; navbar (`config/modules.php` entrées `provider-company`, `ModuleCatalogue::visibles()`) sans clé de permission → liens 403 visibles ; API `GET /api/provider/company/{overview,members,sites,field-teams,tasks}` sans permission (`CompanyController`) ; `MissionPolicy` ignore l'organisation (un owner ne peut pas ouvrir `/missions/{id}` de sa propre société) ; middleware `CheckOrganizationPermission` écrit mais NON enregistré dans `app/Http/Kernel.php` ; `TeamManagement::invite()` sans règle `in:` (ValueError 500).

**Missions.** La table `missions` EST le modèle d'exécution mais reste vide sur le parcours nominal : `MissionFromRendezVousSyncService` (déclenché par `RendezVousObserver` sur `Booking::saved` statut `confirme`) **n'écrit jamais `provider_organization_id`** (vérifié : 0 occurrence), et les bookings réels ont `assigned_provider_organization_id` NULL. `DispatchCenter` filtre sur cette colonne → écrans société vides. `mission_assignments` a des doublons historiques `role`/`role_on_mission` et `status`/`assignment_status` : le code société n'écrit QUE `role_on_mission` (`lead`/`helper`) + `assignment_status` (`assigned`/`released`/`reassigned`) ; ne pas toucher aux doublons.

**Équipes — trois notions concurrentes.** `provider_teams` (cible de la FK `missions.provider_team_id`, aucun modèle Eloquent) ; `field_teams`/`field_team_members` (modèles Eloquent, créées par l'espace société via `FieldTeams.php`, mais PAS référencées par `missions`, membres gérés uniquement par l'admin plateforme) ; vestige Jetstream `teams`. Une équipe créée dans l'espace société ne peut donc pas recevoir de mission. Deux « chefs d'équipe » distincts : `OrganizationRole::TEAM_LEAD` vs `field_teams.team_lead_user_id` (`User::isFieldTeamLead()`).

**Disponibilité.** `AvailabilityService::isAvailable()` est un concept d'INDÉPENDANT (créneaux publiés) : il rend `false` pour tout salarié et coûte ~200 ms/personne — INTERDIT pour la société. Le bon calcul (1 requête de chevauchement `mission_assignments`×`missions` pour toute l'équipe) existe mais est ENFERMÉ dans `DispatchCenter::getDisponibilitesProperty()` (l.277-353).

**Assignation.** `app/Services/Missions/MissionAssignmentService.php` : `assigner()` (réassigner = désassigner, garde le `orWhereNull` sur `role_on_mission`), `ajouterRenfort()`, `retirerRenfort()`. Partagé web + API mobile (`POST /api/provider/company/missions/{mission}/assign`). **N'envoie AUCUNE notification** — ni l'entrant ni le sortant ne sont prévenus.

**Messagerie — deux systèmes.** ChatV2 (`chat_threads`, contexte booking/dispute, modération PII+toxique via `ModerationService`, realtime CASSÉ : aucune `Broadcast::channel('chat.thread.{id}')` dans `routes/channels.php`, et le mobile écoute `private-channel.{id}` + event `ChatMessageSentEvent` alors que le serveur émet `chat.thread.{id}` + `chat.message`). **Channels** (`app/Models/Channel.php`, org-scopé, `channel_members` rôles owner/moderator/member/readonly, DM `ouvrirConversationDirecte`, mentions/réactions/threads/épinglage, scan malware des PJ, realtime `channel.{id}` FONCTIONNEL) — mais toute la gestion (créer, ajouter/retirer un membre, DM) n'existe QUE dans le Livewire `TeamChannels.php` ; l'API mobile ne sait que lister/lire/poster. **La base intra-société = Channels.** ChatV2 n'est pas touché (il sert client↔prestataire).

**Realtime.** Reverb + ledger `broadcast_events` (`TracksBroadcastLedger`). Mobile : `RealtimeProvider` OK (auth Bearer sur `/api/broadcasting/auth`), `useChannel`. Pièges : `config/broadcasting.php` défaut `null` (broadcasts jetés sans env — consigne d'exploitation, ne pas changer le défaut) ; `presence-org.{orgId}` s'autorise sur `users.organization_account_id` alors que la société d'un worker vit dans `provider_profiles.organization_account_id` ; `useLiveMissionUpdates` (provider) est défini mais JAMAIS appelé.

**Appels.** Greenfield total : `VideoCallService` = squelette qui throw ; `MaskedCallService` (Twilio Proxy) complet mais jamais câblé — le laisser intact.

**Push.** `PushService::dispatchToUser()` (FCM/APNs, ledger idempotent, opt-in catégories). Enregistrement device sous `/api/client/devices/*` (nommage trompeur, pas d'équivalent provider). Aucune primitive « notifier tous les porteurs d'une permission dans l'org ».

**Sites.** `organization_sites` = locaux du CLIENT (référent client, code d'accès). `provider_site_assignments` (migration 2026-08-07, 0 ligne, aucun écrivain) = `provider_organization_id` × `organization_site_id` × `user_id`, rôle `lead`/`backup` — TOUTE lecture scopée `provider_organization_id` (plusieurs sociétés concurrentes peuvent desservir le même immeuble). Aucune entité « agence » de la société prestataire.

**Mobile.** Le sous-rôle n'est PAS exposé : `/auth/me` (`AuthMeController`) ne renvoie que `can_manage_company` (= `missions.view_all`). `resolveSpace()` (`src/admin/space.ts`) aiguille sur ce booléen. Trou : `ProfileScreen` (terrain) affiche les 6 boutons société dès `organization_type === 'provider_company'` — vrai pour un worker aussi. `CompanyDispatchScreen` assigne via un `Alert.alert` limité à 10 membres, sans indicateur de dispo ni écran de détail mission société. `CompanyMembersScreen` est en lecture seule. `CompanyChannelsScreen` n'a pas de realtime. Écrans orphelins à ne pas confondre avec des surfaces vivantes : `HomeScreen`, `WalletScreen`, `SettingsScreen`, `MissionExecutionScreen`, `AssignmentOfferScreen`.

---

## Lot 0 — Préalable données : traçabilité société sur le parcours nominal

Sans ce lot, tous les écrans société restent vides. Objectif : `missions.provider_organization_id` et `bookings.assigned_provider_organization_id` TOUJOURS écrits.

- Créer `app/Services/Organizations/ProviderOrganisationResolver::pourUtilisateur(int $userId): ?int` qui lit `provider_profiles.organization_account_id` (LA source de vérité de la société d'un salarié). Tous les chemins d'écriture passent par lui.
- Dans `MissionFromRendezVousSyncService` (`createFromRendezVous` + `syncFromRendezVous`) : renseigner `provider_organization_id` (booking → sinon résolveur via `employe_id`) et `provider_team_id` existant. Si le booking est comblable, l'écrire via query builder / `saveQuietly()` — JAMAIS `save()` (le service est déclenché par un observer sur `Booking::saved` : boucle).
- Corriger `OrganizationMembershipService::rattacher()` : le `firstOrCreate` du `ProviderProfile` ne met pas à jour `organization_account_id`/`provider_type` d'un profil existant (un indépendant qui rejoint une société reste 403) — récupérer puis mettre à jour explicitement.
- Migration de rattrapage idempotente en `chunkById(500)`, ne touchant QUE des NULL : missions via booking, puis missions via `lead_provider_user_id` + résolveur, puis bookings via `employe_id`/`assigned_provider_user_id`. `down()` = no-op.
- Rejouer `EspacesSocieteDemoSeeder` en local pour valider visuellement.

**Acceptation** : booking confirmé avec un salarié → mission portant `provider_organization_id` ; backfill comble les orphelines et laisse intactes celles d'indépendants ; `DispatchCenter` et `GET /api/provider/company/overview` non vides après seed.

## Lot 1 — RBAC serveur : fermer tous les trous web + API (exigences 1, 5, 8 côté serveur)

Stratégie de gardes À DEUX ÉTAGES : middleware `org.permission:<clé>` sur les LECTURES de groupes de routes (uniforme, impossible à oublier), `exige()`/`abort_unless` dans les contrôleurs et actions Livewire pour chaque ÉCRITURE (fin, testable, compatible Livewire).

- Enregistrer `'org.permission' => CheckOrganizationPermission::class` dans `app/Http/Kernel.php` (à côté d'`org.type`) et corriger le middleware pour résoudre via `organizationContextId()` + adhésion active (pas `current_organization_id` seul).
- Gardes API (`routes/api/provider.php`, groupe `provider/company`) : `overview` → `missions.view_all` ; `members` → `team.view` ; `sites` → `sites.view_all` (l'ajouter à la matrice pour operations_manager, dispatcher, site_manager — additif) ; `field-teams` → `team.view` ; `tasks` → scoping (sans `tasks.assign` : seulement créées par moi ou assignées à moi).
- Web : `SiteOperations` reçoit un `mount()` gardé `sites.view_all` ; `ProviderDashboard` réserve team status + KPIs d'équipe à `missions.view_all` (le worker garde un dashboard réduit à SES missions) ; `TaskBoard` idem tasks ; ajouter une clé `permission` aux entrées `provider-company` de `config/modules.php`, filtrée dans `ModuleCatalogue::visibles()` — fini les liens menant à un 403.
- `MissionPolicy::view()` : accorder si (membre actif de `mission.provider_organization_id` ET `missions.view_all`) OU assignment actif sur la mission. C'est la garde serveur de l'exigence 8.
- `TeamManagement::invite()` + `changeRole()` : `Rule::in(...)` sur le rôle selon le type d'org (supprime le 500).
- Matrice : ajouter `missions.assign` à `team_lead` (portée bornée à son équipe au Lot 3).
- `routes/channels.php` `presence-org.{orgId}` : autoriser via `OrganizationMember` actif (repli `provider_profiles.organization_account_id`).

**Acceptation** : worker → 403 sur overview/members/sites/field-teams, tasks = les siennes, mission non assignée → 403, assignée → 200 ; owner ouvre la mission de sa société → 200 ; invitation rôle inconnu → 422 ; navbar du worker sans liens morts ; PHPStan propre.

## Lot 2 — Contrat de rôle mobile + espace de gestion des rôles (exigences 1, 6, 7, 8 côté mobile)

- `/auth/me` (`AuthMeController`) — contrat ADDITIF, pas de versionnage : ajouter `organization_role` (valeur enum du membre actif) et `organization_permissions` (liste des clés ACCORDÉES via PermissionService). `can_manage_company` reste. **Parité connexion/reprise** : mêmes champs dans `ApiAuthController::serializeUser()`.
- Web : nouveau Livewire `ProviderCompany/RolePermissionsMatrix` (route + module gardés `members.manage_permissions`) — PREMIER écrivain de `organization_role_permissions` (accorder ET retirer, `granted` explicite), `invalidateOrganizationCache()` à chaque écriture, permission revérifiée par action.
- API mobile équipe : `POST /api/provider/company/members/invitations`, `PATCH /members/{member}/role`, `POST /members/{member}/suspend`, `DELETE /members/{member}`, `GET/PUT /role-permissions` — en réutilisant les gardes existantes (`GuardsOrganizationMembers`, `canManageMember()` rang strictement supérieur pour gérer, comparaison `<` pour `changeRole` — la protection porte sur le DERNIER owner, pas sur le rôle —, `estLeDernierProprietaire()`), extraites en service partagé Livewire↔API.
- Mobile : étendre `AuthUser` + helper `can(permission)` dans `mobile/shared/src/auth` (défaut-refus si champ absent). `ProfileScreen` (terrain) : conditionner les 6 boutons société par permissions, plus par `organization_type`. `ProviderCompanyNavigator` : onglets conditionnés (Dispatch → `missions.dispatch`, FieldTeams → `team.view`). `CompanyMembersScreen` : actions inviter/changer rôle/suspendre ; nouvel écran `CompanyRolePermissionsScreen` (matrice) sur la pile société.

**Acceptation** : `/auth/me` cohérent matrice+overrides, parité login/refresh (2 tests) ; la matrice écrite change la réponse de `PermissionService` ; mobile en PRESSANT : un worker n'a AUCUN bouton société dans son profil ; un owner change un rôle depuis le téléphone ; un dispatcher sans `team.view` ne voit pas l'onglet Équipes.

## Lot 3 — Unification des équipes + mission↔équipe (exigences 3a, 5)

- `field_teams` devient LA notion canonique. Nouvelle colonne `missions.field_team_id` (nullable, index court) — on ne repointe PAS la FK `provider_team_id` ; `provider_teams` est gelée (commentaire deprecated, aucun nouveau lecteur).
- `MissionAssignmentService::assignerEquipe(Mission, FieldTeam)` en transaction : vérifier même `provider_organization_id` ; lead = `team_lead_user_id` (repli premier membre actif) via `assigner()` existant ; autres membres → `ajouterRenfort()` ; renforts non repris → `retirerRenfort()`.
- Migration additive `mission_assignments.reassigned_by` (FK users nullable) + `reassignment_reason` (nullable) ; `assigner()` les renseigne à la libération.
- Règle « peut réassigner » (helper unique consommé web + API) : `missions.assign` global OU (`team_lead` d'org ET membre de l'équipe de la mission) OU `field_teams.team_lead_user_id` de cette équipe — réconcilie les deux notions de chef d'équipe.
- Gestion des membres d'équipe PAR la société (aujourd'hui admin seulement) : actions dans `FieldTeams` Livewire + `POST/DELETE /api/provider/company/field-teams/{team}/members` (`team.manage`).
- Mobile : `CompanyFieldTeamsScreen` gagne le détail d'équipe + membres ; `CompanyDispatchScreen` gagne « assigner une équipe ».

**Acceptation** : équipe de 3 assignée → 1 lead + 2 helpers, `lead_provider_user_id` juste ; bascule vers l'équipe B → anciens `reassigned`/`released` avec `reassigned_by` ; le worker de l'équipe voit la mission dans `/provider/missions/active` ; team_lead réassigne DANS son équipe, 403 dehors ; `migrate --pretend` propre.

## Lot 4 — Dispo interne, notifications, moteur d'auto-assignation (exigences 2, 5)

- **Extraire** la requête de chevauchement de `DispatchCenter::getDisponibilitesProperty()` en `app/Services/Missions/WorkerAvailabilityService::libresPour(orgId, debut, fin?, userIds?)` (fidèle : jointure `mission_assignments`×`missions`, `orWhereNull(planned_end_at)`). `DispatchCenter` délègue ; nouvel endpoint `GET /api/provider/company/availability?mission_id=` (`missions.assign`). INTERDIT de passer par `AvailabilityService`.
- **Moteur interne** `InternalAutoAssignmentEngine` + `config/internal_dispatch.php` — rien à voir avec `MatchingScoreEngine` (marketplace). Dispo réelle = FILTRE éliminatoire ; scores : référent site (`provider_site_assignments` lead +40 / backup +20), charge du jour (−5/assignment), rotation (+1/jour depuis la dernière, plafonné +15), métier (+15), agence/zone (+10, activée au Lot 6).
- **Audit** : table `internal_assignment_decisions` (mission, org, déclencheur, mode `manual|auto_button|auto_mode`, choisi, statut `assigned|no_candidate|skipped_locked`, candidats JSON avec détail) — calquée sur `booking_matching_decisions`.
- **Bouton owner** : `DispatchCenter::autoAssignerTout()` + `POST /api/provider/company/missions/auto-assign` (`missions.dispatch`) → `AutoAssignerMissionsJob(orgId)` en file, `ShouldBeUnique` (uniqueId = orgId, 300 s), `lockForUpdate()` + revérification par mission. Sans candidat : trace `no_candidate` + résumé aux dispatchers en fin de job.
- **Mode continu** (« toutes les missions ») : réglage d'org `auto_assign_enabled` (colonne ou settings JSON sur `organization_accounts`, additif) ; à la création d'une mission de la société (hook soft-fail après `MissionFromRendezVousSyncService`/dispatch), tenter l'auto-assignation, tracer `auto_mode`. Toggle dans DispatchCenter + mobile, `missions.dispatch`.
- **Notifications** (trou actuel : `assigner()` ne prévient personne) : `OrganizationNotifier` — `notifierUtilisateur()` et `notifierPorteursDe(orgId, permission, payload)` via `PushService::dispatchToUser` (catégorie transactionnelle). Branché DANS `MissionAssignmentService` : entrant, sortant, renfort. Une notification par personne max lors d'une assignation d'équipe. Ajouter des alias `POST /api/provider/devices/*` vers le contrôleur devices existant (nommage honnête, zéro duplication).
- Mobile : remplacer l'`Alert.alert` de `CompanyDispatchScreen` par un écran de sélection avec badge de dispo ; bouton + toggle « Assignation auto » (`missions.dispatch`) ; nouvel écran `CompanyMissionDetailScreen` (pile société : détail, réassigner, renforts, reschedule au Lot 5) ; brancher enfin `useLiveMissionUpdates`.

**Acceptation** : tests engine (référent site gagne ; à égalité le moins chargé ; rotation départage) ; bouton → tout l'arriéré assigné + décisions tracées ; double-clic sans double travail ; mode continu : mission créée → assignée sans clic ; le worker assigné reçoit un push (ligne ledger) ; mobile en pressant : owner lance l'auto-assignation, worker ne voit pas le bouton.

## Lot 5 — Reschedule prestataire : date, heure, LIEU (exigence 3)

- Nouvelle clé `missions.reschedule` (owner, operations_manager, manager, dispatcher par défaut).
- Étendre `BookingRescheduleService` (aujourd'hui strictement client/admin, aucun endpoint, jamais le lieu) : `reprogrammerParPrestataire(Booking, User, date, heure, ?site_id, ?adresse)` — réutilise `booking_reschedule_history` (+ colonne additive `actor_context`) ; lieu = autre `organization_site_id` (B2B) ou adresse libre (B2C). La propagation mission existe déjà (`RendezVousObserver` → resync des `planned_*` et re-géocodage) : ne pas re-sauver le booking dans la synchro.
- Règle par défaut : application immédiate + notification client systématique, PAS d'accord requis ; fenêtre de gel `provider_reschedule.freeze_window_hours` (24 h) sous laquelle seuls owner/operations_manager agissent, motif obligatoire. Le worker assigné est notifié aussi.
- Surfaces : modal `DispatchCenter` (web), `POST /api/provider/company/missions/{mission}/reschedule`, action dans `CompanyMissionDetailScreen` (mobile).

**Acceptation** : dispatcher reprogramme → historique écrit, mission recalée, client + worker notifiés ; à H−12 dispatcher → 403, owner → 200 avec motif ; changement de site suivi sur la mission ; worker → 403 ; branches client existantes intactes (non-régression).

## Lot 6 — Multi-sites large : référents sites clients + entité Agences (exigence 3)

- **Sites clients** : premier écrivain de `provider_site_assignments` — UI dans `SiteOperations` + `POST/DELETE /api/provider/company/sites/{site}/referents {user_id, role}` (`sites.assign_members`). Équipe par défaut d'un site : nouvelle table `provider_site_teams` (org, site, field_team, unique court `pst_org_site_unique`) + `PUT /sites/{site}/default-team` ; l'UI d'assignation pré-propose cette équipe. Toute lecture scopée `provider_organization_id` (anti-fuite entre sociétés concurrentes sur un même immeuble).
- **Agences** : table `provider_agencies` (org FK, name, slug unique par org, adresse, ville, cp, pays, lat/lng, `service_zone_id` nullable, statut) + FK nullables additives `field_teams.provider_agency_id` et `organization_members.provider_agency_id`. Clés `agencies.view` / `agencies.manage`. Web : Livewire `ProviderCompany/Agencies` (CRUD + rattachements) + entrée module gardée. API : `GET/POST/PATCH /api/provider/company/agencies` + rattachements. Activer la dimension agence du moteur (Lot 4) et un filtre par agence dans le dispatch.
- Mobile : `CompanySitesScreen` gagne la gestion des référents ; nouvel écran `CompanyAgenciesScreen` (pile société).
- Ne PAS confondre : `organization_sites` = locaux du CLIENT ; `organization_member_site_access` (pivot client) hors périmètre ; `is_multisite`/`is_key_account` restent non lus.

**Acceptation** : référent lead nommé → le moteur le choisit en priorité ; société A ne voit jamais les référents de B (test anti-fuite) ; agence créée + équipe rattachée + filtre dispatch ; mobile en pressant : nommer un référent depuis un site.

## Lot 7 — Messagerie consolidée sur Channels + notes vocales (exigence 4, phase 1)

Base = **Channels** (org-scopé, realtime `channel.{id}` déjà autorisé et fonctionnel). ChatV2 intact (contextes booking/dispute ; son realtime cassé est une dette consignée, hors périmètre).

- Extraire la logique de `TeamChannels.php` en `ChannelManagementService` consommé par Livewire ET API. Tout envoi passe par `MessageService::send()` (mentions/notifs/diffusion en une transaction — ne pas réimplémenter).
- API : `POST /api/provider/company/channels` (`channels.create`) ; `PATCH /channels/{id}` (rename/archive/lock — `channels.manage` OU owner/moderator du canal) ; `POST/DELETE /channels/{id}/members` (owner/moderator ; cible = membre actif de l'org uniquement) ; `GET /channels/{id}/members` ; `POST /channels/{id}/leave` ; `POST /channels/direct {user_id}` → `ouvrirConversationDirecte` ; `GET /channels/{id}/messages?before_id=` (curseur) ; `POST /channels/{id}/read` + `GET /channels/unread-counts` (`channel_members.last_read_at`, migration additive si absente).
- **Modération partagée** : injecter le `ModerationService` de ChatV2 dans `MessageService::send()` derrière `config/messaging.php` `moderation.channels => true` (ne pas casser les messages système).
- **Notes vocales** : upload audio (m4a/aac, ~5 Mo, scan malware réutilisé), message type `voice` + `duration` en metadata ; enregistrement expo-av + lecteur dans `mobile/shared`.
- Mobile : refonte `CompanyChannelsScreen` (liste + non-lus), nouvel écran `ChannelConversationScreen` (pile société) : realtime `useChannel('channel.'+id)`, pagination, gestion des participants en 2 gestes, bouton micro.

**Acceptation** : anti-fuite inter-orgs (ni lister ni rejoindre) ; retirer un participant lui coupe l'auth du canal Reverb ; la modération bloque un numéro de téléphone ; non-lus exacts ; mobile en pressant : créer une conversation, ajouter puis retirer un participant, envoyer une note vocale (recorder mocké), recevoir un message en temps réel (émission simulée).

## Lot 8 — Appels audio/vidéo LiveKit (exigence 4, phase 2)

- `config/livekit.php` (url, key, secret, ttl) ; `app/Services/Calls/CallService` (tokens serveur — SDK PHP LiveKit ou JWT signé) remplaçant le squelette `VideoCallService` ; `MaskedCallService` intact.
- Table `calls` (channel FK, initiateur, type audio|video, statut ringing|active|ended|missed, room_name, horodatages).
- Endpoints : `POST /channels/{id}/calls` (room + token + broadcast `CallStarted` sur `channel.{id}` + push haute priorité), `POST /calls/{id}/token`, `POST /calls/{id}/end`, `GET /calls/{id}`. Timeout de sonnerie → `missed` par job différé.
- Mobile : `@livekit/react-native` + plugin Expo (**rebuild du dev-client requis** — le planifier ; piège connu : un plugin sans `app.plugin.js` fait accuser expo-modules-core), `CallScreen` (audio d'abord, vidéo en toggle), bannière d'appel entrant (realtime + push), permissions micro/caméra. Rien de tout cela dans l'espace terrain.

**Acceptation** : unit tokens (clé fake) ; machine à états (ringing→active→ended, ringing→missed) ; en pressant : bouton « Appeler » dans un canal, bannière entrante répond/refuse ; E2E manuel documenté (infra LiveKit dev en docker).

## Fin de chantier — vérifications globales

- `php artisan test` complet + PHPStan sans argument de chemin, verts.
- `php artisan migrate --pretend` sans erreur (noms d'index ≤ 64).
- Mobile : `tsc` + jest des deux workspaces ; les tests de joignabilité PRESSENT.
- Parcours manuel de bout en bout par sous-rôle : owner (tout), dispatcher (dispatch sans équipe), team_lead (réassigner dans son équipe), worker (SES missions, tâches, canaux — rien d'autre), viewer (lecture).
- Rappel : un signal vert ne prouve ni la joignabilité d'un écran ni qu'une table se remplit en prod — vérifier sur données semées réelles.
