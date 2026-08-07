# Audit fonctionnel plateforme Brio - 2026-06-08

## Synthese executive

Brio est une plateforme marketplace multi-metiers tres avancee: Laravel 11, Livewire 3, Sanctum, Reverb, Stripe Connect, et une application mobile Expo/React Native. Le produit couvre deja le cycle complet d'une marketplace de services: acquisition client, reservation, estimation, dispatch, execution terrain, tracking, qualite, paiement, litiges, fidelisation, B2B, finance, conformite et administration.

Note globale actuelle: **7.7 / 10**.

Mon avis: la plateforme est bien pensee dans son ambition et dans sa couverture fonctionnelle. Elle ressemble plus a un systeme d'exploitation pour services terrain qu'a une simple app de nettoyage. Le point fort est la profondeur metier: missions, prestataires, zones, contrats, qualite, paiements, KYC/KYB, RGPD, webhooks, API tokens, mobile. Le point faible est la complexite accumulee: plusieurs modules v2 coexistent avec des traces legacy, ce qui impose une discipline forte de gouvernance, tests et simplification avant go-live massif.

Important: l'ancien audit technique `docs/AUDIT-PLATEFORME-2026-06-08.md` signale des risques critiques, mais plusieurs sont deja corriges dans le code actuel: payout Stripe, annulation d'un booking termine, erasure RGPD, feature flags admin, presence v2, Quality authorization et routes QR client.

## Notes par grands axes

| Axe | Note | Avis |
|---|---:|---|
| Vision produit | 9.0 | Tres forte, multi-metiers + B2B + mobile + ops. |
| Couverture fonctionnelle | 8.7 | Beaucoup de modules reels, pas seulement des ecrans. |
| Architecture backend | 8.0 | Bonne separation services/controllers/models, mais dette legacy/v2. |
| Administration | 8.5 | Console admin tres complete, beaucoup de centres de pilotage. |
| Mobile client/provider | 7.2 | Bonne base, mais a valider par parcours E2E reels. |
| Paiement/finance | 7.8 | Ambitieux et corrige sur les gros risques, reste zone sensible. |
| Securite/permissions | 7.6 | Meilleur apres correctifs, demande audit final role par role. |
| Conformite RGPD/KYC/KYB | 8.0 | Tres bon niveau fonctionnel, a prouver par tests et procedure. |
| UX/workflows | 7.3 | Logique metier riche, mais risque de complexite pour les utilisateurs. |
| Readiness production | 7.0 | Possible apres campagne de tests E2E, charge, webhooks et runbooks. |

## Modules et fonctionnalites

| Module | Note | Commentaire |
|---|---:|---|
| Reservations / Bookings | 8.5 | Coeur solide: estimate, create, cancel, ETA, QR start/end, favoris. A garder comme source de verite unique. |
| Missions terrain | 8.2 | Lifecycle start/arrive/complete, tracking, rapports, historique. Tres central, doit etre teste E2E. |
| Multi-metiers / catalogue | 8.0 | Trades, services, options, formulaires dynamiques. Tres bon choix pour sortir du "cleaning only". |
| Zones et geographie | 8.0 | Zones, Belgique/France, pricing par zone. Bon socle operations. |
| Pricing v2 | 7.8 | DSL, regles, quotes, A/B. Puissant mais doit rester explicable pour support/admin. |
| Matching / dispatch | 7.5 | Scoring et presence. Bonne idee, mais performance et lisibilite du score a surveiller. |
| Presence provider | 7.8 | V2 synchronisee avec legacy `is_online`, ce qui corrige le risque principal. |
| Availability provider | 7.7 | Slots, exceptions, iCal. Fonctionnellement important pour reduire le chaos dispatch. |
| Trip tracking / ETA | 7.8 | Tracking provider/client, geofence, ETA. A valider sur mobile en conditions reelles. |
| Quality inspections | 8.0 | Checklists, photos, validation client/admin. Les autorisations sont maintenant visibles dans les controllers. |
| Paiements Stripe | 7.8 | Destination charge + payouts, reconciliation. Les gros risques de double paiement sont corriges dans le code actuel. |
| Wallet provider | 7.2 | Utile, mais toute logique wallet doit etre blindee par idempotence et tests comptables. |
| Commissions | 7.5 | Present et central. Recommande: tableau admin lisible "client paie / plateforme / provider". |
| Factures / devis / PDF | 7.8 | Bon niveau pour exploitation. A renforcer sur numerotation et obligations locales. |
| Accounting v2 | 7.6 | Ledger, exports, periodes. Bonne direction, sensible juridiquement. |
| Subscriptions v2 | 7.2 | Plans/cycles/admin. Utile pour premium ou B2B, mais a simplifier si pas prioritaire go-live. |
| Cancellation v2 | 8.0 | Policy, quote, execution, guard terminal-state. Bon module apres correction. |
| Litiges / disputes | 7.8 | Client/provider/admin, SLA et resolution. Important pour marketplace de confiance. |
| Ratings / feedback | 7.6 | Avis + moderation + signalements. Standard marketplace bien couvert. |
| Safety block/report | 7.5 | Bon signal trust & safety. Doit etre couple a moderation operationnelle claire. |
| KYC provider | 8.0 | Onfido/mock, statut, admin center. Necessaire pour confiance terrain. |
| KYB entreprises | 7.8 | INSEE/VIES/CompaniesHouse, documents, sanctions. Excellent pour B2B, complexite elevee. |
| Onboarding provider | 7.0 | Legacy + v2 existent. Fonctionnel, mais il faut choisir un parcours dominant. |
| Onboarding v2 | 7.4 | Journeys/steps/validators. Bon moteur, attention a ne pas etre contourne par le web legacy. |
| Contrats v2 | 7.8 | Templates, signatures, SLA. Tres pertinent pour B2B. |
| B2B operations | 8.0 | Organisations, sites, work orders, facturation mensuelle. Gros avantage competitif. |
| Entreprises clientes | 7.8 | Roles, sites, approvals. Bien pense pour comptes multi-sites. |
| Entreprises prestataires | 7.6 | Organisation, workers, team lead, dispatcher. Bon mais permissions fines a auditer. |
| Fleet v2 | 7.2 | Vehicules/equipements/maintenance/certifications. Utile si Brio gere vraiment la flotte. |
| Insurance v2 | 7.5 | Plans, achat, claims. Bon module confiance, sensible aux conditions assureur. |
| Loyalty | 7.4 | Points, tiers, transactions. Bien pour retention client. |
| Loyalty rewards | 7.2 | Marketplace recompenses. Utile mais non prioritaire avant stabilite core. |
| Promotions | 7.5 | Promo codes, campagnes, referral. Bon pour acquisition. |
| Marketing v2 | 6.8 | Segments/campagnes/opt-out. Puissant, mais risque RGPD/perf si mal cadre. |
| NPS | 7.0 | Simple et utile pour pilotage qualite. |
| Notifications preferences | 7.8 | Matrice channel x category. Tres bon choix produit. |
| SMS v2 | 7.3 | OTP, logs, retry. A tester avec vrai provider. |
| Push v2 | 7.3 | Device tokens, preferences. Necessaire mobile. |
| Email v2 | 7.0 | Templates/logs. Important mais moins differenciant. |
| Chat v2 | 7.4 | Threads, moderation, read receipts. Bon pour marketplace, attention support/abus. |
| Realtime / Reverb | 7.6 | Presence/live/events. Bonne base, a tester en charge. |
| API tokens v2 | 7.5 | Scopes et usage. Tres utile pour B2B/integrations. |
| Webhooks v2 | 7.7 | HMAC, retry, deliveries. Bon niveau plateforme. |
| Audit v2 | 8.0 | Events, redaction, retention. Fort pour admin et compliance. |
| RGPD | 8.0 | Export/erasure/restriction, anonymisation et cron corriges. A prouver par tests. |
| Analytics | 7.0 | KPIs/funnels. Utile, mais attention a la performance dashboard. |
| International / i18n / FX | 7.2 | Ambition multi-pays. Bon socle mais a deployer progressivement. |
| Platform modules / feature flags | 7.6 | Bonne gouvernance progressive, overrides maintenant lus par runtime. |
| Backups / ops / health | 7.8 | Sentry, backup, health checks, scheduler. Bon niveau production. |

## Notes par role

| Role | Note | Avis |
|---|---:|---|
| Client particulier | 8.2 | Parcours riche: booking, paiement, tracking, QR, avis, litige, fidelite, RGPD. Bon role principal. |
| Prestataire independant / employe | 7.8 | Missions, presence, availability, KYC, wallet, ratings, tracking. Bon, mais mobile terrain doit etre irreprochable. |
| Admin plateforme | 8.7 | Role tres puissant et tres bien outille. Il faut imposer 2FA, audit et permissions fines. |
| Super admin | 8.0 | Necessaire, mais a limiter fortement: risque operationnel si trop de pouvoir manuel. |
| Entreprise cliente - owner | 7.8 | Bon pour comptes B2B, sites, factures, contrats. |
| Entreprise cliente - manager | 7.6 | Role coherent pour piloter demandes et sites. |
| Entreprise cliente - site_manager | 7.4 | Pertinent pour multi-sites, a clarifier dans l'UI. |
| Entreprise cliente - finance | 7.5 | Bon pour factures/credits/contrats. Ne doit pas pouvoir agir sur operations hors finance. |
| Entreprise cliente - requester | 7.2 | Tres utile: creer des demandes sans tout administrer. |
| Entreprise cliente - viewer | 7.0 | Simple mais important pour transparence. |
| Entreprise prestataire - owner | 7.8 | Coherent pour gerer organisation, contrats, equipe, finance. |
| Entreprise prestataire - operations_manager | 7.7 | Role cle pour delivery. Besoin de dashboards de charge tres clairs. |
| Entreprise prestataire - dispatcher | 7.8 | Tres pertinent pour assignation/planification. Role a securiser fortement. |
| Entreprise prestataire - team_lead | 7.5 | Bon pour execution terrain en equipe. |
| Entreprise prestataire - quality_manager | 7.4 | Coherent avec Quality v2. |
| Entreprise prestataire - finance | 7.3 | Utile pour payouts/factures, permissions a limiter. |
| Entreprise prestataire - worker | 7.2 | Doit avoir une UX mobile minimale, directe, sans surcharge admin. |
| Entreprise prestataire - viewer | 7.0 | OK pour audit/transparence. |
| Systeme / jobs / webhooks | 8.0 | Tres present: scheduler, webhooks, queues, reconciliation. Doit etre monitorable. |

## Workflow global de la plateforme

1. Acquisition et creation de compte
   - Le client cree un compte personnel ou entreprise.
   - Le prestataire cree un compte provider independant ou rejoint une organisation.
   - L'admin peut superviser utilisateurs, KYC, KYB, zones, modules et readiness.

2. Onboarding et verification
   - Client: profil, preferences, moyen de paiement, notifications, RGPD.
   - Provider: profil, competences, documents, KYC, Stripe Connect, disponibilites, zones.
   - Entreprise: KYB, membres, roles, sites, contrats, approbations internes.

3. Catalogue et demande
   - Le client choisit un metier/service.
   - Le formulaire dynamique collecte les infos metier.
   - Le pricing estime le montant selon zone, service, options, disponibilite et regles.
   - Le client confirme la reservation et le paiement/PaymentIntent.

4. Matching et dispatch
   - La plateforme cherche des providers disponibles selon zone, metier, presence, score et performance.
   - Le provider recoit une proposition et accepte/refuse.
   - En B2B, la mission peut passer par contrats, work orders ou approvals.

5. Preparation mission
   - Notifications client/provider.
   - Synchro calendrier possible.
   - Tracking/ETA et chat peuvent s'activer.
   - Les checklists qualite peuvent etre preparees selon metier.

6. Execution terrain
   - Provider demarre, arrive, execute et complete.
   - Client confirme potentiellement le debut/fin via QR.
   - Tracking GPS, incidents, medias, checklist et rapport mission alimentent le dossier.

7. Validation qualite
   - Provider soumet inspection/photos.
   - Client valide ou dispute.
   - Admin/quality manager peut moderer, valider ou traiter incident.

8. Paiement et finance
   - Capture Stripe, commission plateforme, payout provider ou wallet.
   - Generation facture/devis/documents.
   - Reconciliation Stripe et accounting.

9. Post-mission
   - Avis, NPS, pourboire, fidelite, badges provider.
   - Litige si probleme.
   - Favoris et rebooking.
   - Marketing/referral/promo pour retention et acquisition.

10. Pilotage admin
   - Admin suit operations, readiness, risques, finance, litiges, logs, webhooks, modules, RGPD.
   - Jobs planifies: reminders, presence cleanup, recurring bookings, payouts, reconciliation, backups, retention.

## Avis produit

Le workflow est globalement bien pense: il suit la vraie vie d'une marketplace de services terrain. Le produit ne s'arrete pas a "prendre rendez-vous"; il gere la confiance, la preuve, la qualite, l'argent et la relation B2B. C'est exactement ce qu'il faut pour une plateforme serieuse.

Ce qui peut etre mieux pense: reduire la complexite visible. L'utilisateur ne doit pas sentir les 50 modules. Le client doit voir un parcours simple: choisir, reserver, suivre, valider, noter. Le provider doit voir: disponible, accepter, aller, executer, finir, etre paye. L'admin doit voir une tour de controle, mais avec priorites et alertes, pas une encyclopedie de menus.

Mon conseil produit: definir 3 parcours "or" et les rendre parfaits avant d'etendre:

1. Client particulier reserve une mission simple, suit le provider, valide, paie et note.
2. Provider independant s'onboarde, accepte une mission, execute avec QR/checklist, recoit son payout.
3. Entreprise cliente cree une demande multi-site sous contrat, suit execution, recoit facture mensuelle.

## Points forts

- Couverture fonctionnelle exceptionnelle pour une marketplace de services.
- Architecture modulaire avec beaucoup de services dedies.
- Admin tres complet.
- Vraie vision B2B: organisations, sites, contrats, KYB, approvals, facturation mensuelle.
- Modules confiance solides: KYC, Quality, Audit, RGPD, Safety, Disputes.
- Paiements et payouts corriges sur les risques majeurs visibles.
- Bon investissement mobile.

## Points faibles / risques restants

- Trop de surfaces v2/legacy: risque de divergence si les anciens chemins restent accessibles.
- UX potentiellement surchargee, surtout admin et mobile provider.
- Les modules money, RGPD, webhooks, mobile QR et mission lifecycle doivent etre couverts par tests E2E, pas seulement unitaires.
- La partie marketing/analytics peut devenir lourde si les requetes ne sont pas chunked/indexees.
- Les roles organisationnels sont bons sur papier, mais demandent une matrice d'autorisations testee route par route.
- Le go-live doit etre progressif par pays/zone/metier, pas global.

## Priorites recommandees avant go-live

| Priorite | Action | Pourquoi |
|---:|---|---|
| 1 | Tests E2E client booking -> payment -> QR -> complete -> invoice -> rating | C'est le coeur business. |
| 2 | Tests E2E provider onboarding -> KYC -> online -> accept -> complete -> payout | C'est le coeur supply. |
| 3 | Matrice permissions par role + tests routes | Evite escalades de privilege et confusion B2B. |
| 4 | Audit final Stripe en mode sandbox avec webhooks reels | Argent reel = zero approximation. |
| 5 | Tests RGPD export + erasure sur fixtures avec PII | Compliance prouvable. |
| 6 | Simplifier navigation mobile provider | Le terrain doit etre ultra direct. |
| 7 | Choisir officiellement les modules legacy a retirer | Reduit les divergences. |
| 8 | Load test admin dashboards, matching, marketing segments | Evite lenteurs operationnelles. |
| 9 | Runbook incident payout/webhook/RGPD | L'equipe doit savoir quoi faire en crise. |
| 10 | Feature flags par zone/metier/role pour rollout progressif | Permet de lancer sans tout exposer. |

## Verdict

Brio est une plateforme tres prometteuse, deja tres avancee techniquement et fonctionnellement. Elle est mieux pensee que beaucoup de marketplaces classiques parce qu'elle integre les vraies contraintes terrain: disponibilite, dispatch, qualite, litiges, preuve, paiement, B2B et conformite.

Elle peut encore etre mieux pensee sur un axe: **la simplicite operationnelle**. Le moteur est puissant; maintenant il faut polir les parcours dominants, fermer les vieux chemins, prouver les workflows critiques par tests E2E et rendre l'admin capable de voir rapidement "ce qui bloque maintenant".

Note finale: **7.7 / 10 aujourd'hui**, avec potentiel **8.8 / 10** apres stabilisation des parcours critiques, nettoyage legacy et validation production.
