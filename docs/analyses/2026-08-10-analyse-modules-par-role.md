# Analyse complète des modules par rôle — état vérifié du 2026-08-10

Analyse produite en croisant : le registre web `config/modules.php` (source unique des points d'entrée, gardé par `CatalogueDesModulesTest`), `config/features.php`, le registre console admin `config/admin_console.php` (81 modules), la parité mobile `config/parity.php`, trois explorations exhaustives du code (2026-08-08) et **deux passes de re-vérification faites aujourd'hui** — car les chantiers « société prestataire » et « moteur de répartition » ont été exécutés depuis, ce qui a comblé plusieurs trous et en a laissé d'autres.

Rôles couverts : client particulier · client entreprise (6 sous-rôles) · prestataire terrain (indépendant + salarié) · société prestataire (11 sous-rôles) · admin & super-admin.

---

## A. Les rôles et sous-rôles

| Espace | Rôles / sous-rôles |
|---|---|
| **Client** | particulier |
| **Client entreprise** | owner, manager, site_manager, finance, requester, viewer |
| **Prestataire terrain** | indépendant ; salarié de société (worker) |
| **Société prestataire** | owner (100), operations_manager (80), manager (80), dispatcher (60), site_manager (60), quality_manager (50), finance (50), team_lead (40), requester (20), worker (20), viewer (10) — matrice de permissions à 3 étages (override nominatif > matrice par société `organization_role_permissions` > défauts), désormais réglable par écran |
| **Admin** | admin, super-admin (console web + console 100 % native dans `mobile/provider`) |

---

## B. Modules transversaux (tous rôles)

| Module | Ce qu'il fait |
|---|---|
| Compte / Profil (`profile.show`) | identité, mot de passe, sessions |
| Notifications (`notifications.index`) + Préférences | centre de notifications ; matrice channel × catégorie versionnée, forced-on légal |
| Aide (`help.center`), CGV, Confidentialité, Mentions, Cookies | pages légales et support |
| i18n v2 | fr/nl/en/es/it/de, overrides en DB sans déploiement |
| Push (FCM/APNs/web) | `PushService` avec ledger idempotent, opt-in par catégorie |
| SMS / WhatsApp | provider-agnostic Mock/Twilio, OTP, webhooks DLR |
| Realtime (Reverb) | ledger `broadcast_events`, publish idempotent, replay admin |
| Analytics produit | event ledger + sessions, PII sanitisée, funnels + cohortes |
| Audit v2 | `audit_events` typés, rétention par domaine, redaction PII |
| GDPR | export self-service, droit à l'oubli, rétention |
| Feature flags | kill-switch + rollout par %, users, rôles (`config/features.php` + manager admin) |

---

## C. Modules installés par rôle, et ce qu'ils font

### C1. Client particulier (web ~31 modules + app `mobile/client`)

**Commander & planifier** : Commander (`/commander` = OrderJourney : SECTEUR→MÉTIER→QUESTIONS, prix-avant-identité, immédiat/RDV — **désormais le MÊME formulaire sur `/dashboard/client/rendez-vous/nouveau`**) · Mes rendez-vous · Historique · Calendrier interactif (drag & drop reprogrammation) · Templates 1-clic (récurrence) · Chantiers groupés (bundles multi-métiers) · Trouver un prestataire (Search v2 : filtres note/prix/zone/métier) · Devis IA depuis photo.
**Pendant la mission** : suivi GPS live (Trip Tracking v2 : ETA, géo-barrière 150 m), code de présence à 6 chiffres affiché au prestataire, messagerie contextuelle (ChatV2 par booking, modération PII automatique).
**Payer** : Finance (factures) · Cartes bancaires · Portefeuille (crédits) · Abonnements + Abonnements v2 (Stripe, recovery) · Offre Premium · Pourboires 3 presets après mission.
**Qualité & confiance** : Litiges/SAV (SLA, résolutions Stripe+promo) · Avis blind-reveal style Uber · Assurance (plans + sinistres) · NPS.
**Croissance** : Fidélité 4 tiers + Récompenses (5 types, stock verrouillé) · Parrainage · Codes promo · Favoris + re-réservation 1 clic.
**Compte** : Profil, RGPD, KYB (si passage entreprise), API tokens, Mes statistiques.
**Mobile client** : parcours de commande, suivi live, chat, profil, + 6 écrans natifs « Espace entreprise » (overview, sites, réservations, membres, contrats, facturation) si société.

### C2. Client entreprise (web 15 modules — sous-rôles : owner, manager, site_manager, finance, requester, viewer)

Accueil société · Réservations (hub) + Nouvelle réservation + **Multi-locaux** (via `bookings.parent_booking_id`) + Import bulk · Mes locaux (`organization_sites` : accès, référents, préférences par métier) · Membres (6 sous-rôles, invitations) · Contrats + Signatures sur place (`signing_appointments`) · Facturation (⚠ stub, voir §D) · Litiges · Analytics · KYB (INSEE/VIES/Companies House + sanctions, RiskScoreEngine) · RGPD · Prendre rendez-vous (même OrderJourney).
Les sous-rôles filtrent par la même matrice `PermissionService` que côté prestataire (bookings.view_all, sites.edit, finance.view…).

### C3. Prestataire terrain — indépendant & salarié (web 23 modules + app `mobile/provider` espace terrain)

**Ma journée & missions** : Ma journée (dashboard carte-first) · Mes missions · Historique · Boîte de réception des offres · **Modal d'offre 20 s** (`src/offers/` : OfferHost + OfferModal — realtime `user.{id}` + push + repli polling, compte à rebours sur `expires_at` serveur) · Suivi de mission (GPS, géo-barrière, scan du code de présence) · Inspections/checklists par métier · Incident.
**Ce que je fais et où** : **Métiers et zones** (`employe.trades-zones` — les deux tables que lit la requête candidate du dispatch ; cocher une case change les offres reçues) · Disponibilités (créneaux récurrents + exceptions + iCal) · Ma présence (Presence v2 : online/busy/pause/offline, heartbeat + position GPS) · Planning · Google Agenda.
**Revenus** : Tableau de bord revenus · Portefeuille · Stripe Connect · Devis chantiers (bundles).
**Confiance & croissance** : Dossier de vérification (onboarding v2 par rôle) · KYC (Mock/Onfido/Veriff/SumSub) · Badges (13 seedés, 6 critères) · Mes avis · Feedbacks · Mes litiges.
**Équipe (salarié)** : Coordination · Équipe terrain · Panneau chef d'équipe (`employe.teamlead.operations`).
**Mobile terrain** : 4 onglets (Dashboard carte, Missions, Revenus, Profil) + offres ASAP, tracking, scan présence, KYC, disponibilités, badges, litiges, avis, chat, notifications — et le modal d'offre par-dessus le home.

### C4. Société prestataire (web 11 modules + espace société mobile — 11 sous-rôles)

Dashboard société (KPIs filtrés par permission) · **Dispatch** (répartition : missions, dispo par chevauchement, **bouton + mode continu d'auto-assignation** via `InternalAutoAssignmentEngine` + `AutoAssignerMissionsJob`, audit `internal_assignment_decisions`, reprogrammation date/heure/lieu) · Équipe (inviter, changer les rôles, suspendre, retirer, permissions nominatives, invitations à jeton) · **Rôles et permissions** (matrice par société — premier écrivain de `organization_role_permissions`) · Équipes terrain (`field_teams` + membres, rattachées aux missions) · **Nos implantations** (agences : entité `provider_agencies` + rattachement équipes/membres) · Sites desservis (`SiteOperations` : référents lead/backup par site client via `provider_site_assignments`, équipe par défaut) · Tâches (kanban) · Canaux (messagerie interne org-scopée : groupes, DM, mentions, réactions, pièces jointes scannées, **appels LiveKit** via `CallService`) · Dossier société · Présence.
**Mobile société** : 6 onglets (Accueil, Répartition, Équipes, Tâches, Canaux, Profil) + membres, matrice de rôles, détail mission (réassigner, renforts, reschedule), sites, implantations, conversation de canal. Notifications d'assignation via `OrganizationNotifier`.
**Par sous-rôle** : owner = tout ; operations_manager = missions+équipe+canaux+analytics ; dispatcher = répartition ; site_manager = sites ; quality_manager = qualité/missions lecture ; finance = finance ; team_lead = ses missions d'équipe + réassignation DANS son équipe ; worker = SES missions, tâches, canaux — rien d'autre ; viewer = lecture.

### C5. Admin & super-admin (web ~80 modules + console native mobile 81/81)

Par catégorie (registre `config/modules.php`) :
**Rendez-vous & planning** : Planning, Disponibilités, Calendrier interne, Réglages Google Agenda.
**Missions & terrain** : Missions, **Répartition** (`admin.dispatch.center` — l'histoire complète d'une recherche : qui sollicité, quand, à quelle distance, refus ou silence), Orchestration, Automation, Presence providers, Trip Tracking GPS, Fleet véhicules, Bundles chantiers.
**Documents** : Contrats v2, Comptabilité (FEC/Sage/QuickBooks).
**Finance** : Finance, Business, Pricing v2 (DSL + A/B), Stripe hardening, Stripe prestataires, Factures B2B, Abonnements v2, Annulations v2, Crédits clients, Pourboires, Approbations entreprise.
**Comptes & organisations** : Utilisateurs, Entreprises, B2B opérations, Sites, Zones, Pays, International, Services, Équipes & partenaires, Onboarding prestataires + documents, Clients premium.
**Prestataires** : Inscriptions, Onboarding v2.
**Communication** : Chat & messagerie (modération), Push, SMS/WhatsApp, Emails, Realtime/Reverb, Préférences notifications.
**Qualité & litiges** : Litiges & SAV, Inspections qualité, Modération avis, Feedbacks, Badges, Signalements & blocks.
**Conformité** : KYC, KYB, RGPD, Audit v2 + Journaux, Risk scoring, Assurance.
**Croissance** : Fidélité + Récompenses, Marketing automation (segments DSL, drip, A/B), Campagnes/Codes promo, Parrainage.
**Données** : Dashboard, Vue d'ensemble, Alertes, Analytics + v2, Raisons d'annulation, IA Dispatch, Matching insights, NPS.
**Plateforme** : Modules, Feature flags, **Catalogue géographique** (Pays → Zones → Métiers : activation + prix + **asap_enabled** par (métier, zone), constructeur de questionnaires), Trades, Géolocalisation v2, FX devises, Traductions, Webhooks B2B, API tokens v2, Readiness, Outils.
**Console mobile native** : moteur à descripteurs (`app/Admin/Console`) couvrant 81 modules (71 descripteurs + 10 rapports), catalogue géographique et JourneyBuilder inclus.

---

## D. Installés mais MAL branchés ou PAS branchés (chaque point re-vérifié aujourd'hui)

### Cassés ou incohérents (à réparer en priorité)

| # | Constat | Preuve |
|---|---|---|
| 1 | **ChatV2 (client↔prestataire) : realtime cassé.** Le serveur émet sur `chat.thread.{id}` mais AUCUNE `Broadcast::channel('chat.thread.*')` n'existe dans `routes/channels.php` → toute souscription = 403 ; en plus le mobile écoute le mauvais canal (`private-channel.{id}`) ET le mauvais event. Le chat client marche uniquement par rechargement. | grep `chat.thread` dans channels.php : 0 |
| 2 | **ChatV2 : pas d'API participants.** Impossible d'ajouter/retirer quelqu'un d'un thread ; `left_at` n'est jamais écrit ; et n'importe quel utilisateur authentifié peut ouvrir un thread avec n'importe qui (aucun contrôle de relation). | routes `v2-shared.php` : aucune route participants |
| 3 | **Chaîne surge/dynamic pricing incohérente.** `SurgePricingEngine`, `DynamicPricingService`, `RecomputeSurgeJob` + cron lisent `trade_zone_settings` — la table condamnée par la décision « `trade_zone_pricing` seul survivant », que le nouveau `ZonePricingResolver` applique. Deux vérités de prix par zone coexistent à nouveau ; le flag `surge_pricing=true` est allumé sur une chaîne branchée sur la mauvaise table. | 6 fichiers lisent encore trade_zone_settings |
| 4 | **`customer_credits` : modèle et table désalignés** — écrire via `CustomerCredit::create` casse (piège documenté, jamais corrigé). | mémoire projet, non re-testé aujourd'hui |

### Dormants (le code existe, rien ne l'appelle)

| # | Constat |
|---|---|
| 5 | **`MaskedCallService` (appels masqués Twilio Proxy)** : service complet + config + mock, `enabled=false`, AUCUNE route, aucun appelant hors tests. C'est pourtant LA brique idéale du kit « sur place » (§F). |
| 6 | **`VideoCallService`** : squelette qui `throw`, rendu obsolète par le nouveau `CallService` LiveKit — code mort à supprimer. |
| 7 | **`SmsChannel`** (canal de notification Laravel) : 0 référence — les notifications ne partent jamais par SMS alors que le module SMS est prêt. |
| 8 | **`organization_member_site_access`** (accès par site pour les membres d'une société CLIENTE) : table + relation `authorizedMembers()`, aucun écran ne l'écrit ni ne la lit — un site_manager d'entreprise cliente voit donc tous les locaux. |
| 9 | **`is_multisite` / `is_key_account`** : maintenant référencés par des écrans admin (`GestionEntreprises`) mais sans effet produit clair — flags décoratifs. |

### Stubs et écarts assumés à surveiller

| # | Constat |
|---|---|
| 10 | **`BillingCenter` (facturation client entreprise web)** : zéros codés en dur (« à connecter à Invoice model ») — l'API mobile, elle, est branchée sur les vraies factures. Le web ment. |
| 11 | **Devis chantiers multi-métiers** : les devis prestataires sont saisis À LA MAIN par l'admin (gap connu et reporté). |
| 12 | **Déploiement : 0 succès sur 100 runs, aucun secret configuré** — toute cette richesse fonctionnelle n'a jamais atteint un serveur. |
| 13 | **Notes vocales dans les canaux société** : rien dans `app/Services/Messaging` ne traite l'audio (le lot « messages vocaux » du chantier messagerie ne semble pas livré, contrairement aux appels LiveKit). À vérifier puis livrer. |
| 14 | Chantiers tout juste livrés (dispatch 20 s, auto-assignation interne, agences, matrice de rôles, reschedule prestataire, formulaire unifié) : **branchés mais jeunes** — à re-tester sur base fraîchement semée (`DispatchDemoSeeder` existe pour ça) avant de considérer §C comme acquis en production. |

*(Anciens trous re-vérifiés aujourd'hui et désormais COMBLÉS — ne plus les signaler : `provider_organization_id` écrit par la synchro mission ; `asap_enabled` + chaîne géographique livrés ; `provider_site_assignments` a des écrivains ; `useLiveMissionUpdates` branché ; l'écran d'offre orphelin remplacé par `src/offers/` ; `/rendez-vous/nouveau` sert bien OrderJourney.)*

---

## E. Modules MANQUANTS par rôle — pour passer au niveau maximum (utilité réelle uniquement)

### Client particulier
1. **Réserver pour un proche** (bénéficiaire ≠ payeur : parent âgé, location) — adresse + contact du bénéficiaire, suivi partagé.
2. **Carnet d'adresses multi-logements** avec préférences par logement (produits, allergies, animaux, instructions d'accès) — aujourd'hui réservé aux entreprises (`organization_sites`), les particuliers repartent de zéro à chaque commande.
3. **Partage de suivi** (« Follow my ride » d'Uber) : lien de suivi live à partager à un tiers pendant une mission babysitting/dépannage.
4. **Budget maison** : dépenses par métier/mois, projection abonnement vs à la demande (les données existent dans Mes statistiques).
5. **Assistant de commande conversationnel** (IA) : décrire le besoin en langage naturel → pré-remplit le questionnaire du bon métier (le Devis IA photo existe déjà ; le texte manque).
6. **Garanties visibles** : page « ma protection » agrégeant assurance, politique d'annulation, recours — les briques (insurance, cancellation v2) existent sans vitrine client.

### Client entreprise (sous-rôles)
7. **Budgets et plafonds par site** avec alertes (finance/owner) — brancher sur Accounting v2.
8. **Workflow d'approbation des réservations** (requester propose → manager approuve) — `admin.enterprise.approvals` existe côté admin, rien côté client.
9. **Tableau SLA** : taux de réalisation, retards, pénalités par site/contrat (quality + trip tracking fournissent déjà les données).
10. **Activer `organization_member_site_access`** (§D8) : restreindre un requester/site_manager à SES locaux — la table dort.
11. **Intégrations comptables self-service** : exports FEC/Sage par la société cliente elle-même (le moteur existe côté admin).

### Prestataire indépendant
12. **Heatmap de demande + prévisions** (« où me placer à quelle heure ») — patron Uber ; les données de recherches/dispatch existent désormais dans les décisions d'assignation.
13. **Objectifs & quêtes** (gamification de revenus : « 5 missions ce week-end = bonus ») — s'appuie sur Loyalty/Badges existants.
14. **Cash-out express** du portefeuille (virement instantané payant) — Stripe Connect est en place.
15. **Statistiques d'offres** : taux d'acceptation, temps de réponse, raisons de perte (les lignes existent dans `mission_assignments` : `response_seconds`, refus, timeouts).
16. **Académie / certifications** : mini-formations par métier débloquant des badges et un boost de matching (trade_specialty existe dans le scoring).
17. **Optimisation de tournée** : ordonnancement des missions du jour + navigation (trip tracking + géo v2 existants).
18. **Assistant fiscal** : export annuel des revenus, estimation de charges (accounting v2 côté admin sait déjà tout).

### Société prestataire (sous-rôles)
19. **Rostering / plannings d'équipe** (shifts) : horaires contractuels, rotations, remplacements — LE grand absent ; l'auto-assignation interne se base sur les missions faute de planning déclaré.
20. **Pointage & feuilles d'heures** (time clock lié au trip tracking) → export paie (finance).
21. **Congés & absences** (worker demande, manager approuve) — alimente la dispo du dispatch interne.
22. **Rentabilité** par mission/site/équipe : CA, heures, marge (accounting + tips + missions déjà en base) — pour owner/finance.
23. **Inventaire consommables** : stocks, seuils, réassort, consommation par mission (quality_manager/ops).
24. **Constructeur de devis** multi-métiers par la société elle-même — ferme le gap §D11.
25. **Recrutement** : annonces internes, pipeline candidats → invitation (l'invitation à jeton existe déjà).
26. **Score qualité interne par worker** (inspections + avis + ponctualité) — pour quality_manager ; les 3 sources existent.
27. **Fleet côté société** : véhicules/équipements/certifications gérés par la société (le module Fleet v2 existe mais n'est piloté que par l'admin).

### Admin / super-admin
28. **Surge & pricing dynamique COHÉRENT** : rebrancher la chaîne surge sur `trade_zone_pricing` (§D3) avec heatmap et plafonds — le moteur dort déjà là.
29. **Prévision de demande** (par zone/métier/heure) pour piloter le recrutement de prestataires — les ledgers analytics + dispatch suffisent.
30. **Santé du marketplace** : ratio offre/demande par zone, temps médian d'assignation, taux no-candidate — compléter le centre Répartition.
31. **Centre d'exploitation des recherches échouées** : relance manuelle, appel client, geste commercial en 1 clic.
32. **Modération IA** des avis/photos/chat (le pipeline modération regex existe ; l'IA manque).

### Worker (sous-rôle terrain)
33. **Mode sécurité / SOS** : bouton d'urgence pendant mission (position partagée, contact admin) — Safety center admin existe, rien côté terrain.
34. **Ma fiche du jour** : itinéraire ordonné, temps de trajet entre missions (cf. §E17).

---

## F. Modules « SUR PLACE » — le prestataire arrive chez le client et lance la mission

### Ce qui existe déjà (à valoriser, pas à réécrire)
- **Confirmation de présence** : code 6 chiffres affiché par le client, scanné par le prestataire.
- **Trip tracking** : enroute → arrivée par géo-barrière 150 m → in_mission → ended ; le client suit en live.
- **Inspections / checklists par métier** (quality) : items cochables pendant la mission, signature eIDAS-lite.
- **Chat contextuel** du booking (modéré) + désormais appels LiveKit dans les canaux société.
- **Pourboire** et **avis blind-reveal** en fin de mission ; **litiges** si problème.

### À créer côté PRESTATAIRE (l'aider et le protéger)
1. **État des lieux photo/vidéo horodaté** avant de commencer (et après) — preuves géolocalisées attachées à la mission ; protège des litiges.
2. **Signalement d'imprévu** : dégât préexistant, accès impossible, objet manquant — photo + catégorie, notifie le client ET pré-alimente le dossier litige.
3. **Extras sur place (upsell validé)** : « vitres en plus ? +25 € » → le client approuve en un tap → repricing via Pricing v2 + paiement Stripe incrémental. Le patron « prix figé au devis » reste, l'extra est une ligne additionnelle tracée.
4. **Chronomètre de mission** avec pauses (lié au tracking) — base des feuilles d'heures (§E20).
5. **Fiche d'accès au lieu** : instructions, codes, étage, parking — existe pour les sites B2B (`access_instructions`), à offrir au particulier (§E2) et à AFFICHER au prestataire à l'arrivée seulement (pas avant l'assignation).
6. **Guide pas-à-pas du métier** : la checklist qualité présentée en mode guidé, photos de référence attendues par étape.
7. **Consommables utilisés** : saisie rapide → stock société (§E23) et facturation éventuelle.
8. **Appel/SMS masqué client↔prestataire** : brancher enfin `MaskedCallService` (§D5) — numéros jamais exposés, fenêtre limitée à la mission.
9. **Rapport de fin automatique** : PDF photos avant/après + checklist + durée + extras, envoyé au client à la clôture (contracts v2 sait signer, push/mail savent livrer).
10. **Signature de fin du client** sur l'écran du prestataire (eIDAS-lite existant) quand le client est présent.

### À créer côté CLIENT (simplifier l'expérience pendant la mission)
11. **Timeline live de la mission** : arrivé → démarré → étapes de checklist cochées en direct → ETA de fin — le canal `mission.{id}` et les inspections existent, il manque la vue.
12. **Approbation des extras** en un tap (miroir du §3) avec récapitulatif de prix clair.
13. **Visionneuse avant/après** : photos du prestataire poussées en direct, comparateur à la fin.
14. **Mode « je ne suis pas sur place »** : instructions d'accès, boîte à clés, contact de secours ; le code de présence bascule sur un mode photo-preuve d'arrivée.
15. **« Tout va bien ? »** : ping discret à mi-mission (satisfaction en 1 tap) — alimente NPS et détecte les problèmes avant le litige.
16. **Clôture guidée** : validation du rapport de fin → pourboire → avis, en un seul flux (les trois briques existent séparément).

---

## G. Priorités recommandées

1. **Réparer les cassés** (§D1-4) : realtime ChatV2 + API participants, chaîne surge sur la bonne table, customer_credits — petits chantiers, gros irritants.
2. **Kit « sur place »** (§F) : c'est le différenciateur d'expérience le plus visible, et 70 % des briques existent (tracking, checklists, contracts, masked calls dormants, pricing).
3. **Rostering + pointage + congés** (§E19-21) : le manque le plus structurant pour les sociétés prestataires — l'auto-assignation interne devient vraiment fiable quand elle connaît les horaires.
4. **Heatmap/prévision de demande + stats d'offres** (§E12, 15, 29-30) : boucle d'amélioration du dispatch fraîchement livré.
5. **Déploiement** (§D12) : aucun de ces modules n'existe pour un utilisateur tant que la plateforme n'est jamais déployée.

---

*Boucle de complétude effectuée : (1) inventaire depuis les registres réels du code, pas la doc ; (2) chaque « pas branché » re-vérifié par grep aujourd'hui — 6 anciens constats retirés car comblés par les chantiers récents, 2 nouveaux ajoutés (notes vocales absentes, surge incohérent) ; (3) chaque rôle et sous-rôle couvert dans §C et §E ; (4) modules sur place couverts côté prestataire ET client dans §F.*
