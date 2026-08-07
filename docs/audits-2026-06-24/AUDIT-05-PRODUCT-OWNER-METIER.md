## 1. Résumé exécutif

Brio est une marketplace de services à la demande (style Uber/Bolt pour le nettoyage, la peinture, la garde d'enfants…), avec un backend Laravel et **deux applications mobiles** (client et prestataire).

**Le verdict a fondamentalement changé depuis le 8 juin.** À l'époque, le **cœur du produit était cassé** : le scan QR mobile (preuve de présence + déclenchement du paiement) tapait des routes inexistantes, un risque de double-paiement aux prestataires tournait chaque nuit, et le droit à l'oubli ne s'exécutait jamais. **Aujourd'hui, ces 5-6 blocages critiques sont corrigés** avec des implémentations sérieuses. Le parcours métier fonctionne de bout en bout.

La question n'est donc plus « est-ce que ça marche ? » mais **« est-ce branché sur de vrais fournisseurs ? »** et **« les promesses du README sont-elles tenues ? »**. Réponse : presque tous les services externes sont en mode **simulation (mock)** par défaut, et plusieurs promesses commerciales sont exagérées (« 30+ métiers », « white-label », « 50+ modules »).

> **Capacité à lancer : un go-live ciblé et progressif est réaliste** (un pays, quelques métiers, fournisseurs réels configurés et testés). Un lancement global tenant toutes les promesses du README ne l'est pas encore.

## 2. Le parcours cœur — réparé et fonctionnel

### 2.1 🟢 Flow de réservation → exécution (QR) → paiement → notation

- **QR start/end** : routes créées et implémentées ; le scan client valide le code, déclenche la mission et **capture le paiement** Stripe pré-autorisé ; côté prestataire, le code de fin est bien transmis et validé serveur.
- **Paiement** : le risque de double-paiement aux prestataires est éliminé (idempotence en place).
- **Anti-double-annulation** : un booking déjà terminé ne peut plus être ré-annulé/remboursé.
- **Module Qualité** : un utilisateur ne peut plus consulter ni signer les inspections (photos, signatures) d'autrui.

> **Action produit :** réaliser **un** test terrain réel (vrai téléphone, vrai scan) avant go-live — le câblage est bon, mais le « happy path » terrain doit être prouvé une fois.

## 3. Écarts promesse / réalité à arbitrer

### 3.1 🟠 Deux systèmes d'annulation client aux conséquences financières différentes

Deux routes coexistent : une annulation **simple** (`/api/client/.../cancel`) qui ne facture **aucun frais** et ne rembourse rien, et le **moteur V2** (`/api/v2/client/.../cancel`) qui calcule frais + remboursement + reprise de fidélité.

- **Impact :** selon le canal, un même client annulant la même réservation peut être facturé… ou pas. Risque de revenus perdus et d'incohérence.
- **Recommandation :** router tous les canaux vers le moteur V2, neutraliser la route simple.

### 3.2 🟠 Pas de bouton d'annulation dans l'app mobile client

Aucun appel d'annulation dans `mobile/client/src` : l'app affiche le statut « annulé » mais n'offre pas l'action.

- **Impact :** le client mobile ne peut pas annuler depuis l'app → report sur le support.
- **Recommandation :** ajouter l'annulation mobile en la câblant directement sur le moteur V2 (résout 3.1 et 3.2 d'un coup).

### 3.3 🟡 Deux moteurs d'onboarding prestataire divergents

L'API mobile passe par `OnboardingV2`, mais le **wizard web** utilise toujours l'ancien service (logique dupliquée). Un prestataire qui s'inscrit via le web ne suit pas le même parcours/validations que via le mobile.

### 3.4 🔵 « White-label / Tenancy v2 » annoncé mais mort

Le README le liste comme livré ; en réalité la fonctionnalité a été supprimée (colonne morte, aucune logique active). **Promesse commerciale non tenue** — à retirer du discours tant qu'elle n'est pas refaite.

### 3.5 🔵 « 30+ métiers » → 12 réellement configurés (mais bien faits)

12 métiers réels (nettoyage, peinture, bâtiment, plomberie, électricité, jardinage, déménagement, garde d'enfants, toiture, levage, rénovation, sécurité), chacun avec formulaire dynamique, modèle de tarification et certifications. **Mais** seuls 2/12 ont une checklist qualité dédiée ; les autres utilisent une checklist générique.

- **Recommandation :** ajuster la promesse à « 12 métiers + extensible », ou compléter les checklists métier par métier.

### 3.6 🔵 « 50+ modules » → 10 réellement pilotables

Les « 50+ » désignent des domaines de code, pas des modules activables/désactivables depuis l'admin. Seuls **10 modules** sont gouvernables par flag. Pas un bug, mais une confusion à clarifier.

## 4. 🟠 Mocks vs réel — la maturité d'intégration (point clé go-live)

Tous les services externes critiques sont en **mode simulation par défaut** : SMS/OTP, Push, KYC, KYB, Assurance, Géolocalisation, FX/devises, Email v2. Stripe n'a pas de clé par défaut.

- **Impact :** sans configuration explicite (clés Twilio, Onfido, FCM/APNs, Stripe live, Google Maps…), **aucune de ces fonctions n'est réellement opérationnelle**. Chaque fournisseur réel = un contrat + une intégration + un test.
- 🟢 **Garde-fou :** la commande `ops:check-providers --strict` **bloque le déploiement en prod** si un mock est détecté.
- **Recommandation :** bâtir un plan d'activation fournisseur par fournisseur, priorisé (Stripe + SMS + Push + géoloc d'abord ; KYC/KYB/assurance selon la stratégie de confiance). **C'est probablement le plus gros chantier restant avant un vrai lancement.**

## 5. Points forts produit

### 5.1 🟢 Internationalisation complète (6 langues)

fr, nl, en, es, it, de — chacune avec **364 clés identiques** et fichiers symétriques. Aucune langue squelettique. L'expansion multi-pays est crédible côté langues — l'une des promesses les mieux tenues.

### 5.2 🟢 Apps mobiles en beta solide

App client (~33 écrans) et app prestataire (~30 écrans) : parcours principal câblé, exécution mission/GPS/wallet solides côté prestataire. Reste : annulation client absente, pourboire non câblé, quelques TODO mineurs côté prestataire → **beta solide, pas encore « polie prod »**.

## 6. Roadmap & dette fonctionnelle

Les documents **BLOCK 11-17** spécifient une couche **B2B/terrain avancée non fusionnée** (équipes & chefs d'équipe, contrats B2B & work orders, orchestration multi-chantier) — un « v2 produit » à arbitrer après lancement. Le `CLEANUP_PLAN_PRODUCTION.md` liste un nettoyage important non exécuté (contrôleurs/Livewire orphelins, unification de clés, tables v1 dupliquées) à faire avant la prod pour réduire la dette.

## 7. Cartographie : ce qui marche / partiel / mock

| Domaine | État réel |
|---|---|
| Réservation / booking | 🟢 Marche (formulaires dynamiques par métier) |
| Flow QR start/end (mobile) | 🟢 Marche (réparé — à tester terrain) |
| Paiement Stripe (capture/payout) | 🟢 Marche (réparé — nécessite clés Stripe live) |
| Notation / ratings | 🟢 Marche |
| Module Qualité | 🟢 Marche (checklists métier partielles 2/12) |
| Dispatch / présence ASAP | 🟢 Marche (réparé) |
| RGPD (export/erasure) | 🟢 Marche (réparé) |
| Multi-métiers | 🟢 12 métiers (pas 30+) |
| i18n (6 langues) | 🟢 Complet |
| Annulation | 🟠 Partiel (2 systèmes divergents, absent du mobile) |
| Onboarding prestataire | 🟠 Partiel (2 moteurs divergents) |
| App mobile client / prestataire | 🟡 Beta solide |
| SMS, Push, KYC, KYB, Assurance, Géoloc, FX | 🟠 **Mock par défaut** (non fonctionnels sans config) |
| Tenancy / white-label | 🔴 Mort (annoncé mais supprimé) |
| BLOCK 11-17 (B2B terrain avancé) | 🔴 Non fusionné (roadmap future) |

## 8. Verdict & recommandations produit

> **Maturité produit :** passée de « moteur puissant mais cœur cassé » (8 juin) à **« moteur puissant, cœur fonctionnel, intégrations à brancher et discours à recalibrer »**.

**Priorités produit avant go-live :**

1. 🟠 **Activer les vrais fournisseurs** (Stripe live, SMS, Push, géoloc) et les tester — le plus gros chantier.
2. 🟠 **Unifier l'annulation** (3.1/3.2) et **compléter l'annulation mobile**.
3. 🟡 **Unifier l'onboarding** prestataire (web vs mobile).
4. 🔵 **Recalibrer la promesse commerciale** : 12 métiers (pas 30+), retirer le white-label, clarifier « 50+ modules ».
5. 🔵 **Exécuter le plan de nettoyage** pour réduire la dette legacy.

**Scénario recommandé :** go-live **progressif** (un pays, un sous-ensemble de métiers, fournisseurs réels configurés/testés), puis élargissement.

*Réserve : audit basé sur l'état du code au 24/06 ; la maturité réelle des modules « squelette » (Loyalty, Marketing, Fleet, NPS…) est variable et n'a pas été testée fonctionnellement un par un.*
