# Chantier « Niveau maximum » — réparations + 34 modules par rôle + kit sur place (web + mobile natif, sans rien casser)

ultracode

Tu travailles sur le monorepo CleanUx : marketplace multi-services (nettoyage, peinture, babysitting, toiture…), Laravel 11 + Livewire côté web, monorepo Expo/React Native sous `mobile/` (`mobile/client`, `mobile/provider`, package partagé `mobile/shared`). Base MySQL en prod, tests PHPUnit sur SQLite.

**MISSION : installer TOUS les modules listés dans ce prompt — les réparations, les modules manquants par rôle et le kit « sur place » — issus de l'analyse `docs/analyses/2026-08-10-analyse-modules-par-role.md` (LIS-LA en premier). Chaque module doit être au BON endroit, fonctionner sur WEB ET en NATIF MOBILE, et NE RIEN CASSER de l'existant — on ne touche à l'existant que pour le corriger. Le résultat final doit être le résultat recherché, vérifié en boucle.**

## MÉTHODE IMPOSÉE — équipe de dev senior en multi-agents, qui se corrige elle-même

Tu travailles comme une ÉQUIPE DE DEV SENIOR, pas comme un dev solo. Utilise l'orchestration multi-agents (**ultracode / l'outil Workflow**) pour chaque phase :

1. **Architecte** : un agent lit le code concerné et tranche l'EMPLACEMENT exact de chaque pièce du lot (service, table, route, écran, navigateur) en suivant les « règles d'emplacement » ci-dessous — livrable : un mini-plan par module.
2. **Implémenteurs** : des agents implémentent en parallèle ce qui est indépendant (worktrees isolés si les fichiers se chevauchent).
3. **Panel de revue adversariale** : après chaque lot, des agents seniors relisent avec des lentilles DISTINCTES — (a) correctness/bugs, (b) NON-RÉGRESSION (qu'est-ce qui existait et pourrait casser ?), (c) parité web ↔ mobile natif, (d) emplacement/architecture (est-ce au bon endroit, réutilise-t-on l'existant ?), (e) sécurité/permissions. Chaque finding est vérifié adversarialement (un agent tente de le RÉFUTER) avant correction.
4. **Correction puis reboucle** : les findings confirmés sont corrigés, la batterie de vérification tourne, et on reboucle revue → correction **jusqu'à zéro finding confirmé ET batterie verte**. Ne passe JAMAIS au lot suivant avec un finding ouvert ou un test rouge.
5. Si l'outil Workflow n'est pas disponible dans la session, reproduis EXACTEMENT le même schéma avec des sous-agents (Agent tool) ou séquentiellement — mais la passe de revue adversariale n'est jamais sautée.

**L'ARRÊT N'EST AUTORISÉ que lorsque la checklist finale (tout en bas) est entièrement cochée, chaque module installé, branché, vérifié web + mobile, et la plateforme entière encore verte.**

## PROTOCOLE « NE RIEN CASSER » (sacré)

- **Avant** de modifier un fichier partagé : lire ses tests et ses consommateurs (grep). **Après chaque lot** : `php artisan test` COMPLET (zéro échec, y compris les suites qui ne concernent pas le lot), PHPStan **sans argument de chemin**, `php artisan migrate:fresh --seed` (zéro erreur, tables du lot PEUPLÉES), `tsc` + jest sur `mobile/provider` et `mobile/client`.
- Les garde-fous mécaniques existants restent verts : `CatalogueDesModulesTest` (tout point d'entrée web doit être au registre), `AdminConsoleInventoryTest`, `EloquentResourceSchemaTest`, `nativeScreens.test.ts`, tests de thème (`noHardcodedColors`), tests de joignabilité.
- **Migrations ADDITIVES uniquement** dans ce chantier (colonnes/tables nouvelles, combler des NULL) — SAUF pour les réparations de la Phase 0 qui l'exigent explicitement. Seeders et factories mis à jour avec chaque table.
- Interdiction de supprimer ou renommer une capacité existante, sauf quand un module de ce prompt la remplace explicitement (c'est écrit dans le module concerné).
- Tout module risqué ou coûteux passe derrière un **feature flag** (`config/features.php`), défaut ON en dev, pour pouvoir couper sans déployer.
- La suite tourne sur SQLite, la prod sur MySQL strict : pas de SQL vendor-specific ; `lockForUpdate()` no-op sous SQLite — tester la logique. Index ≤ 64 caractères (`migrate --pretend`).
- Livewire ne rejoue pas `mount()` : chaque action publique revérifie ses permissions. Lectures scopées organisation DANS la requête.
- **NE TE FIE PAS à `docs/`** (à part l'analyse citée) : la vérité est dans le code.

## RÈGLES D'EMPLACEMENT — « tout au bon endroit »

- **Logique métier → services par domaine** (`app/Services/<Domaine>/`), JAMAIS dans un composant Livewire ni un contrôleur : le service est consommé par Livewire ET par l'API mobile (motif `ChannelManagementService`, `MissionAssignmentService`).
- **Page web** = composant Livewire dans `app/Livewire/<Espace>/` + route dans le fichier de routes de l'espace + **entrée OBLIGATOIRE dans `config/modules.php`** (context : `client` | `employe` | `admin` | `client-company` | `provider-company`, avec clé `permission` si l'accès est restreint — la navbar filtre dessus).
- **API mobile** = `routes/api/client.php` | `provider.php` | `v2-shared.php` selon l'audience, gardée par permission (`org.permission` middleware pour les lectures de groupe, `exige()` en contrôleur pour les écritures).
- **Écran mobile** = dans le BON espace (`mobile/client`, ou `mobile/provider` : terrain / société / console admin), monté dans le bon navigateur, avec une SURFACE qui y mène (onglet, bouton, navigate()) et un test de joignabilité qui PRESSE (`fireEvent.press`) — un écran monté mais injoignable est un échec (ce dépôt a un historique d'écrans orphelins). Thème via `stylesFor(useThemeColors())`, zéro couleur en dur. `usePresenceHeartbeat()` uniquement espace terrain.
- **Écran admin** = passer par le moteur de console (`app/Admin/Console` : descripteur `EloquentResource` ou `AdminReport`) + `config/admin_console.php`, pour couvrir web ET console mobile d'un coup ; page Livewire admin riche seulement si le descripteur ne suffit pas.
- **Parité** : `config/parity.php` mis à jour pour toute nouvelle surface.
- **Realtime** = event + `TracksBroadcastLedger` + autorisation dans `routes/channels.php` ; mobile via `useChannel`. **Push** = `PushService::dispatchToUser` (catégorie transactionnelle sauf marketing).
- Réutiliser AVANT de créer : Pricing v2 (DSL), Contracts v2 (signatures eIDAS-lite), Accounting v2, Loyalty, Promo, Trip Tracking, Inspections/Quality, Stripe (destination charges), OrganizationNotifier, ModerationService, `MaskedCallService` (dormant), Fleet v2, NPS, Safety center. Quatre permissions ou tables qui « dorment » sont souvent la brique qu'il te faut — vérifie avant d'ajouter une clé ou une table.

---

# PHASE 0 — RÉPARATIONS (corriger l'existant AVANT de construire dessus)

**R1. Realtime du chat client↔prestataire (ChatV2).** Ajouter `Broadcast::channel('chat.thread.{threadId}')` dans `routes/channels.php` (autorisé aux participants actifs du thread, `left_at` nul) ; corriger `mobile/shared/src/chat/hooks.ts::useLiveChat` — canal `chat.thread.${id}` et event `chat.message` (aujourd'hui il écoute `private-channel.{id}` + `ChatMessageSentEvent` : double erreur) ; vérifier l'écoute côté web client. Acceptation : un message envoyé apparaît chez l'autre participant SANS rechargement, web et mobile.

**R2. ChatV2 : participants et création contrôlées.** `POST/DELETE /api/v2/chat/threads/{thread}/participants` (rendre la synchro publique dans `ChatService`, écrire enfin `left_at`) ; restreindre `createThread` : un utilisateur ne peut ouvrir un thread qu'avec une relation réelle (booking/dispute partagé) ou être admin — aujourd'hui n'importe qui peut ouvrir un thread avec n'importe qui et s'auto-déclarer `admin` du thread. Acceptation : tests d'anti-abus + retrait d'un participant lui coupe lecture et realtime.

**R3. Chaîne surge/pricing dynamique COHÉRENTE.** `SurgePricingEngine`, `DynamicPricingService`, `RecomputeSurgeJob` + commande lisent encore `trade_zone_settings`, la table condamnée (décision : `trade_zone_pricing` est le seul chemin, appliqué par `ZonePricingResolver`). Rebrancher toute la chaîne sur `trade_zone_pricing` (colonnes surge additives sur cette table), supprimer `trade_zone_settings` + `ManagesTradeZoneSettings`, exposer le réglage surge dans `CatalogCenter` (même ligne métier×zone), respecter le flag `surge_pricing`. Acceptation : un surge activé sur (métier, zone) change le prix du parcours `/commander`, et une seule table de vérité subsiste.

**R4. `customer_credits` : modèle et table réalignés.** Le modèle et le schéma divergent (écrire via `CustomerCredit::create` casse). Migration d'alignement + tests d'écriture/lecture + vérifier les consommateurs (admin crédits clients, remboursements).

**R5. Canal SMS branché.** `SmsChannel` (0 référence) : le brancher dans les notifications critiques via les préférences (`preferredChannels`) — rappels de RDV, code de présence, alerte « prestataire en route » ; le module SMS (Twilio/Mock, ledger) est prêt. Acceptation : une notification critique part en SMS quand l'utilisateur a opté pour ce canal.

**R6. Supprimer `VideoCallService`** (squelette qui `throw`, remplacé par `CallService` LiveKit) et tout import mort autour.

**R7. `BillingCenter` client entreprise : vraies données.** Le web affiche des zéros codés en dur ; l'API native lit déjà les vraies factures via `ClientFinanceDocumentScope`. Brancher le composant web sur la même source. Acceptation : web et mobile affichent les mêmes montants.

**R8. Notes vocales dans les canaux société.** Le lot « messages vocaux » n'a pas été livré (rien dans `app/Services/Messaging` ne traite l'audio). Livrer : upload audio m4a/aac (~5 Mo, scan malware existant), message type `voice` + `duration` en metadata, enregistreur expo-av + lecteur dans `mobile/shared`, MediaRecorder côté web. Acceptation : envoyer/écouter une note vocale web ↔ mobile.

**R9. Activer `organization_member_site_access`** — voir E10 (Phase 4), c'est le même chantier.

---

# PHASE 1 — KIT « SUR PLACE » (le prestataire arrive chez le client et lance la mission)

Fondations communes : table `mission_media` (mission, type `before|after|incident`, chemin, lat/lng, pris_le, hash sha256 — preuve horodatée géolocalisée) ; table `mission_extras` (mission, libellé, prix via Pricing v2, statut `proposed|approved|declined`, approved_at) ; table `mission_incidents` (catégorie, description, media_id, notified_at). Événements realtime sur `mission.{id}` (canal déjà autorisé). Surfaces : `MissionFieldScreen` (mobile provider) + page mission web employé ; côté client : écran mission (mobile client + web).

**F1. État des lieux photo/vidéo horodaté** (provider) : capture avant de commencer et après avoir fini, rattachée à `mission_media`, visible dans le rapport et en cas de litige.
**F2. Signalement d'imprévu** (provider) : dégât préexistant, accès impossible, objet manquant — catégorie + photo → notification client immédiate + pré-alimentation du dossier litige (Disputes existant).
**F3. Extras sur place** (provider) : proposer un supplément (« vitres +25 € ») → prix calculé par Pricing v2 → le client approuve en un tap (F12) → paiement incrémental Stripe (destination charge, même mécanique que le paiement principal) → ligne tracée. Le devis d'origine reste figé, l'extra est une ligne additionnelle.
**F4. Chronomètre de mission avec pauses** (provider) : sur `trip_tracking_sessions` (pauses additives), alimente les feuilles d'heures (E20).
**F5. Fiche d'accès au lieu** (provider) : instructions, codes, étage — depuis `organization_sites.access_instructions` (B2B) et le carnet d'adresses client (E2, B2C) ; RÉVÉLÉE seulement à l'état `arrived` (jamais avant l'assignation).
**F6. Guide pas-à-pas du métier** (provider) : les checklists d'inspection existantes en mode guidé (ordre imposé, photo de référence attendue par étape).
**F7. Consommables utilisés** (provider) : saisie rapide sur place → mouvements d'inventaire (E23) + éventuelle ligne facturable.
**F8. Appels/SMS masqués client↔prestataire** : BRANCHER enfin `MaskedCallService` (complet, jamais appelé) — routes provider + client, session ouverte automatiquement à l'assignation, fermée à la clôture, cron `scanExpired`, numéros jamais exposés.
**F9. Rapport de fin automatique** : à la clôture, PDF généré (photos avant/après + checklist + durée + extras + consommables) envoyé au client (mail + push) et archivé (Contracts v2 sait stocker/signer). Soft-fail si génération PDF échoue (patron existant).
**F10. Signature de fin du client** sur l'écran du prestataire (eIDAS-lite Contracts v2) quand le client est présent.
**F11. Timeline live de la mission** (client, web + mobile) : arrivé → démarré → items de checklist cochés en direct → ETA de fin — écoute `mission.{id}` + événements d'inspection.
**F12. Approbation des extras en un tap** (client) : push + realtime, récapitulatif de prix clair, refus possible — miroir de F3.
**F13. Visionneuse avant/après** (client) : photos poussées en direct, comparateur à la clôture — miroir de F1.
**F14. Mode « je ne suis pas sur place »** (client) : toggle sur le booking — instructions d'accès, boîte à clés, contact de secours ; la preuve de présence bascule sur photo-preuve d'arrivée (le scan du code 6 chiffres reste le défaut quand le client est là).
**F15. Ping « tout va bien ? »** (client) : notification discrète à mi-mission, réponse en 1 tap → alimente NPS, alerte si négatif.
**F16. Clôture guidée** (client) : un seul flux rapport de fin → pourboire (existant) → avis blind-reveal (existant).

---

# PHASE 2 — SOCIÉTÉ PRESTATAIRE (E19-E27) — le manque le plus structurant

**E19. Rostering / plannings d'équipe (shifts).** Tables `shifts` (org, agence, équipe, user, récurrence, début/fin) ; écran société « Planning d'équipe » (web Livewire + écran natif espace société) ; **`WorkerAvailabilityService` consomme les shifts** : disponible = en shift ET sans chevauchement de mission — l'auto-assignation interne devient fiable. Permission `team.manage`/`missions.dispatch`.
**E20. Pointage & feuilles d'heures.** `time_entries` liées aux missions (clock-in/out automatique par géo-barrière du tracking + correction manuelle approuvée), feuilles d'heures par période, export paie CSV — écran société (finance/owner) + « mes heures » pour le worker.
**E21. Congés & absences.** `leave_requests` (worker demande → manager approuve), intégré à la dispo du dispatch interne et au rostering. Écrans société + mobile (worker : demander ; manager : approuver).
**E22. Rentabilité.** Service d'agrégation (CA, heures pointées, marge par mission/site/équipe/agence) sur Accounting v2 + missions + tips ; écran société analytics (owner/finance) + rapport console admin.
**E23. Inventaire consommables.** `inventory_items` + `inventory_movements` (stocks par agence, seuils, alertes réassort via OrganizationNotifier) ; écran société + saisie terrain (F7).
**E24. Constructeur de devis société.** La société bâtit ses devis multi-métiers elle-même (réutiliser Pricing v2 DSL + bundles) au lieu de la saisie manuelle par l'admin — ferme un gap connu. Écran société + envoi au client pour acceptation.
**E25. Recrutement.** `job_postings` + candidatures simples → pipeline → l'invitation à jeton existante conclut. Écran société (owner/manager).
**E26. Score qualité interne par worker.** Agrégat inspections + avis clients + ponctualité (tracking) par worker ; écran quality_manager + fiche worker. Aucune nouvelle collecte : trois sources existantes.
**E27. Fleet côté société.** Exposer Fleet v2 (véhicules/équipements/certifications de SES workers) dans l'espace société — le module existe, seul l'admin le pilote ; le gating mission-si-cert-expirée existe déjà.

---

# PHASE 3 — CLIENT PARTICULIER (E1-E6)

**E1. Réserver pour un proche.** Étape « bénéficiaire » optionnelle dans OrderJourney (`order_drafts`/`bookings` : colonnes beneficiary_name/phone/note additives) ; le suivi live est partageable au bénéficiaire (E3). Web `/commander` + mobile client.
**E2. Carnet d'adresses multi-logements.** Table `client_places` (adresse géocodée, instructions d'accès, préférences : produits, allergies, animaux) ; pré-remplit OrderJourney ; alimente la fiche d'accès sur place (F5). Écran client web + mobile.
**E3. Partage de suivi.** Lien signé public en lecture seule sur le suivi live d'une mission (patron « Follow my ride ») — route web publique à jeton expirant, généré depuis l'écran de suivi client.
**E4. Budget maison.** Extension de `client.analytics.dashboard` : dépenses par métier/mois, comparatif abonnement vs à la demande — données déjà en base.
**E5. Assistant de commande conversationnel (IA).** Décrire le besoin en texte → mapping secteur/métier + pré-remplissage du questionnaire OrderJourney (le Devis IA photo existe, réutiliser son pipeline). Feature flag.
**E6. « Ma protection ».** Page agrégeant assurance souscrite, politique d'annulation applicable, litiges en cours et recours — briques existantes (Insurance, Cancellation v2, Disputes), seule la vitrine manque. Web + mobile.

---

# PHASE 4 — CLIENT ENTREPRISE (E7-E11)

**E7. Budgets et plafonds par site.** `organization_site_budgets` (site, période, plafond) + alertes de dépassement (OrganizationNotifier) ; visible dans facturation/analytics société cliente (finance/owner).
**E8. Workflow d'approbation des réservations.** Statut `pending_approval` : un requester propose, un manager/owner approuve avant que le booking parte au dispatch ; notifications aux approbateurs ; écran « Approbations » côté client entreprise (le patron admin `enterprise.approvals` existe).
**E9. Tableau SLA.** Extension analytics société cliente : taux de réalisation, retards, annulations par site/contrat — sources : missions, tracking, cancellation v2.
**E10. Accès par site pour les membres (active `organization_member_site_access`).** UI dans Membres/Mes locaux pour restreindre un membre à SES sites ; les listes réservations/locaux/analytics sont scopées par cet accès. La table et la relation `authorizedMembers()` dorment déjà.
**E11. Exports comptables self-service.** La société cliente exporte elle-même FEC/CSV de ses factures (moteur Accounting v2 existant, il manque la porte côté client) — dans Facturation (R7 d'abord).

---

# PHASE 5 — PRESTATAIRE INDÉPENDANT + WORKER (E12-E18, E33-E34)

**E12. Heatmap de demande + prévisions.** Agrégats des recherches/décisions de dispatch par zone/heure (les ledgers existent depuis le chantier dispatch) ; overlay sur la carte du Dashboard mobile + page web `employe.heatmap` — « où me placer, à quelle heure ».
**E13. Objectifs & quêtes.** `provider_quests` + progression (hooks sur missions terminées, patron des hooks Loyalty) ; récompenses via Loyalty/bonus ; écran revenus web + mobile.
**E14. Cash-out express.** Virement instantané Stripe (payant, fee affichée) depuis le portefeuille — Stripe Connect en place ; ledger wallet immuable respecté.
**E15. Statistiques d'offres.** Taux d'acceptation, temps de réponse médian, missions perdues et pourquoi — tout est dans `mission_assignments` (response_seconds, refus, timeouts) ; page web + onglet mobile Revenus.
**E16. Académie / certifications.** `academy_courses` + completions ; réussir débloque un badge (module Badges existant) et un bonus `trade_specialty` dans le scoring ; contenu géré par l'admin (descripteur console).
**E17 + E34. Ma fiche du jour / tournée optimisée.** Ordonnancement des missions du jour (heuristique distance/horaires via Geo v2), temps de trajet entre missions, lancement navigation — écran terrain mobile + planning web.
**E18. Assistant fiscal.** Export annuel des revenus (PDF/CSV) + estimation de charges — extension de la page Revenus, données wallet/accounting existantes.
**E33. Mode sécurité / SOS.** Bouton d'urgence pendant mission (mobile terrain + web) : position partagée en continu, alerte au Safety center admin (existant) + contact d'urgence. Priorité haute, jamais derrière un flag.

---

# PHASE 6 — ADMIN (E28-E32)

**E28. Surge cohérent + pilotage** : c'est R3, plus l'UI de pilotage (heatmap des multiplicateurs, plafonds par métier×zone) dans le Catalogue/Pricing.
**E29. Prévision de demande.** Job d'agrégation (recherches, réservations par zone/métier/heure) + écran de projection — pour piloter le recrutement de prestataires là où ça manque.
**E30. Santé du marketplace.** Extension du centre Répartition : ratio offre/demande par zone, temps médian d'assignation, taux no-candidate, tendance — rapport console admin (web + mobile).
**E31. Exploitation des recherches échouées.** Depuis le centre Répartition : relancer une recherche, contacter le client, geste commercial en 1 clic (PromoCode/crédits existants) — actions de descripteur avec champs requis (patron `Action::requires`).
**E32. Modération IA.** Brancher un provider IA derrière le `ModerationService` existant (avis, photos de mission, chat) sous feature flag, avec file de revue humaine (les centres de modération existent) — l'IA propose, l'admin dispose.

---

## BOUCLE DE VÉRIFICATION (après CHAQUE lot, avant tout passage au suivant)

1. Revue adversariale multi-agents (méthode imposée §haut) → zéro finding confirmé.
2. `php artisan test` COMPLET — zéro échec, y compris les modules qu'on n'a pas touchés (c'est LE test « on n'a rien cassé »).
3. PHPStan sans argument de chemin — zéro erreur.
4. `php artisan migrate:fresh --seed` — zéro erreur, tables nouvelles PEUPLÉES par les seeders, scénario de démo dispatch toujours jouable (`DispatchDemoSeeder`).
5. Mobile : `tsc` + jest des workspaces touchés — zéro échec ; joignabilité PROUVÉE en pressant pour chaque nouvel écran.
6. Registres à jour : `config/modules.php`, `config/admin_console.php`, `config/parity.php`, `config/features.php` — les garde-fous les vérifient.
7. Parcours manuel du lot réalisable de bout en bout sur base fraîchement semée, web ET mobile.

## CHECKLIST FINALE (l'arrêt n'est autorisé que TOUT coché)

**Réparations** : ☐ R1 realtime ChatV2 ☐ R2 participants+création contrôlée ☐ R3 surge sur trade_zone_pricing ☐ R4 customer_credits ☐ R5 SmsChannel branché ☐ R6 VideoCallService supprimé ☐ R7 BillingCenter réel ☐ R8 notes vocales ☐ R9=E10.
**Sur place** : ☐ F1 état des lieux ☐ F2 imprévus ☐ F3 extras ☐ F4 chronomètre ☐ F5 fiche d'accès ☐ F6 guide pas-à-pas ☐ F7 consommables ☐ F8 appels masqués ☐ F9 rapport de fin ☐ F10 signature ☐ F11 timeline live ☐ F12 approbation extras ☐ F13 avant/après ☐ F14 mode absent ☐ F15 ping mi-mission ☐ F16 clôture guidée.
**Société prestataire** : ☐ E19 rostering ☐ E20 pointage ☐ E21 congés ☐ E22 rentabilité ☐ E23 inventaire ☐ E24 devis société ☐ E25 recrutement ☐ E26 score qualité ☐ E27 fleet société.
**Client particulier** : ☐ E1 proche ☐ E2 carnet d'adresses ☐ E3 partage de suivi ☐ E4 budget ☐ E5 assistant IA ☐ E6 ma protection.
**Client entreprise** : ☐ E7 budgets ☐ E8 approbations ☐ E9 SLA ☐ E10 accès par site ☐ E11 exports.
**Indépendant/worker** : ☐ E12 heatmap ☐ E13 quêtes ☐ E14 cash-out ☐ E15 stats d'offres ☐ E16 académie ☐ E17+E34 fiche du jour ☐ E18 fiscal ☐ E33 SOS.
**Admin** : ☐ E28 surge pilotage ☐ E29 prévision ☐ E30 santé marketplace ☐ E31 exploitation échecs ☐ E32 modération IA.
**Global** : ☐ suite complète verte ☐ PHPStan propre ☐ migrate:fresh --seed propre et peuplé ☐ tsc/jest verts ☐ registres et garde-fous verts ☐ chaque nouvel écran joignable en pressant ☐ rien d'existant cassé (les parcours d'avant marchent encore).

Note hors code : le déploiement reste à 0 succès (aucun secret configuré) — signale-le en fin de chantier, ne tente pas de le résoudre sans demande.
