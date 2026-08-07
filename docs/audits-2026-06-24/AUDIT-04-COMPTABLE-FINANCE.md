## 1. Résumé exécutif (sans jargon)

Cet audit examine la plateforme sous trois angles financiers : **(1)** les coûts récurrents des services extérieurs, **(2)** le module de comptabilité intégré et les flux d'argent, **(3)** les risques financiers et les licences.

Deux messages clés :

- **Le moteur comptable est techniquement bien conçu** : comptabilité en partie double avec contrôle d'équilibre bloquant, export FEC conforme à la norme française (DGFiP), clôture de période verrouillée, traçabilité et idempotence des flux d'argent. Les quatre bugs « argent » de l'audit du 8 juin (dont un risque de **double paiement quotidien** aux prestataires) sont **corrigés**.
- **Le risque budgétaire principal est « dormant »** : aujourd'hui, presque tous les services extérieurs payants sont en mode **simulation (« mock »)**. Seul Stripe engendre un coût réel actuellement. Au moment du lancement, ces coûts s'activeront d'un coup — d'où l'importance de les budgéter dès maintenant.

> **Avis :** fondations comptables **solides**. Ce qui reste relève de **décisions de paramétrage et de conformité** (modèle de TVA, numérotation des factures, facturation électronique, SIREN) à trancher **avant la première clôture réelle** et le premier export fiscal.

## 2. ⚠️ Avertissement transversal : services en mode « simulation »

Dans `.env.example`, chaque service extérieur est livré avec un fournisseur factice par défaut : `SMS_PROVIDER=mock`, `KYC_PROVIDER=mock`, `KYB_*=mock`, `INSURANCE_PROVIDER=mock`, `GEO_PROVIDER=mock`, `FX_PROVIDER=mock`, `PUSH_PROVIDER=mock`.

**Conséquence comptable :** en l'état, **seul Stripe coûte de l'argent**. Les autres postes (SMS, KYC, IA, cartes, géoloc) ne se déclencheront qu'au basculement vers le vrai fournisseur en production. Le risque budgétaire s'activera donc **au go-live** — c'est précisément ce qu'il faut provisionner.

## 3. Axe 1 — Coûts récurrents des services tiers

| Service | Rôle | Modèle de facturation | Actif ? | Ordre de grandeur* | Scale avec le volume |
|---|---|---|---|---|---|
| **Stripe + Connect** | Encaissement + reversement prestataires | ~1,5–1,8 % par transaction + frais Connect | 🟢 **Oui (réel)** | ~1 800 €/mois pour 100 k€ traités | Oui (∝ CA) — **poste n°1** |
| Twilio (SMS) | OTP, rappels, marketing | ~0,08 € par SMS | Non (mock) | ~800 €/mois à 10 k SMS | Oui — vite |
| KYC (Onfido…) | Vérif. identité prestataires | 1–3 €/vérif (5–15 € enrichie) | Non (mock) | ~1 000 €/mois à 500 vérifs | **Oui — dangereux** |
| KYB (INSEE/VIES…) | Vérif. entreprises | Souvent **gratuit** (quotas) | Non (mock) | ~0 € (FR/UK/VIES) | Non |
| IA (Claude + OpenAI) | Assistant/chatbot | Par token, **plafonné** | Non (clé absente) | ≤ ~300 $/mois (plafond) | Plafonné |
| Google Maps | Adresses, distances | ~5–17 $/1000 requêtes | Non (mock) | Quelques 100 €/mois (avec cache) | Oui — surveiller |
| Assurance (Hiscox…) | Couverture prestations | Prime revendue (neutre) | Non (mock) | Pass-through | n/a |
| Email (Mailgun…) | Emails transactionnels | ~0,80 €/1000 | Non (local) | Quelques dizaines €/mois | Modéré |
| Push (FCM/APNs) | Notifications mobiles | **Gratuit** | Non (mock) | 0 € | Non |
| Sentry | Supervision erreurs | Abonnement (échantillon 10 %) | Installé | ~30–100 €/mois | Maîtrisé |
| FX (BCE) | Taux de change | BCE **gratuit** | Non (mock) | ~0 € | Non |
| Hébergement (cloud, Redis, Reverb) | Infrastructure | Mensuel | n/a | Quelques 100 €/mois | Oui |
| EAS Expo + stores | Builds/soumission apps | ~99 $/mois + 99 $/an + 25 $ | n/a | ~100 €/mois + ~100 €/an | Non |

*\*Ordres de grandeur estimés sur les tarifs publics standards et des hypothèses de volume — à affiner avec les contrats réels.*

**Postes à surveiller en priorité (scale dangereusement) :** Stripe (∝ chiffre d'affaires), KYC (∝ recrutements de prestataires), SMS (∝ utilisateurs), autocomplétion Google Maps (∝ trafic).

🟢 **Bons points de maîtrise des coûts déjà codés :** plafonds anti-abus SMS (5/h, 20/j), plafond de coût quotidien de l'IA (`cost_limit_usd_per_day`), caches géoloc multi-niveaux + repli gratuit « haversine », échantillonnage Sentry à 10 %.

## 4. Axe 2 — Module comptable & flux financiers

### 4.1 🟢 Export FEC conforme DGFiP — présent et correct

Le générateur produit les **18 colonnes obligatoires** de la norme française (`FecExportBuilder.php:22-29`), délimiteur `|`, nettoyage des caractères interdits. Exports **Sage** et **QuickBooks** également présents.

- ⚠️ **À faire :** renseigner le **SIREN** (`ACCOUNTING_FEC_SIREN` vide par défaut) avant tout export fiscal réel.

### 4.2 🟢 Comptabilité en partie double avec contrôle d'équilibre

Chaque écriture refuse d'être enregistrée si **débit ≠ crédit** (`AccountingService.php:47-72`). Plan comptable PCG/PCMN, journaux normalisés, clôture de période **verrouillée** et auditée, écritures **idempotentes** (pas de doublon). Réconciliation Stripe automatisable (comparaison paiements/payouts vs ledger interne).

### 4.3 🟡 Décision structurante : modèle de revenu « principal » vs « agent »

Deux modèles sont codés (`config/accounting_v2.php:86-103`) ; le défaut est **« principal »** (tout le TTC en ventes). Le vrai modèle marketplace est **« agent »** (seule la commission est un produit ; la part prestataire est une dette jusqu'au payout). Le code lui-même note : *« À VALIDER avec le comptable avant de passer en agent »*.

- **Enjeu :** le modèle « principal » **gonfle artificiellement le chiffre d'affaires** et peut fausser l'assiette de TVA.
- **Recommandation FORTE :** c'est **LA** décision comptable centrale, à trancher avant le go-live.

### 4.4 🟡 Facturation : numérotation non séquentielle continue

Le numéro de facture est `FAC-{année}-{id_booking}` (`FinanceDocumentCalculator.php:155-160`), donc basé sur l'ID technique du booking. Si certains bookings ne donnent pas de facture (annulations…), la suite comportera **des trous** — contraire à l'obligation française de **numérotation séquentielle continue**.

- **Recommandation :** introduire un **compteur de factures dédié** (par année/entité), indépendant de l'ID de réservation, avant émission réelle.

### 4.5 🟡 TVA & facturation électronique (Factur-X)

- TVA multi-pays présente (FR 20 %, BE 21 %…), mais la **responsabilité TVA** sur la prestation (plateforme vs prestataire assujetti) n'est pas tranchée dans le code — à définir avec le comptable, en cohérence avec 4.3.
- Un générateur **Factur-X / CII** existe mais en version MVP (embarquement du XML dans le PDF/A-3 non finalisé). À cadencer sur l'échéance légale française de facturation électronique B2B (déploiement échelonné à partir de **septembre 2026**).

### 4.6 🟢 Bugs « argent » du 8 juin — tous corrigés

| Réf | Risque | Statut |
|---|---|---|
| A1 🔴 | Double paiement quotidien aux prestataires | 🟢 Corrigé (`auto_transferred` + exclusion du cron) |
| A2 🟠 | Double virement sans idempotence | 🟢 Corrigé (clé d'idempotence + verrou) |
| M4 🟡 | Double déduction de commission au wallet | 🟢 Corrigé (crédit du net uniquement) |
| M5/M10 🟡 | Crédit wallet non idempotent | 🟢 Corrigé (service idempotent + transaction) |

🟢 Le payout est aussi **gelé tant qu'un litige est ouvert**. Pourboires et conversions de devises sont tracés et idempotents (la plateforme ne prélève actuellement **rien** sur les pourboires ni sur le change).

## 5. Axe 3 — Risques financiers & licences

### 5.1 🟢 Licences — pas de coût caché

Logiciel **propriétaire** (aucune redevance à un tiers pour le code). Dépendances majeures sous licence MIT (gratuites). Les coûts viennent des **services** (Axe 1), pas des librairies — le SDK est gratuit, le service est payant (Stripe, Twilio, Sentry…).

### 5.2 🟡 Incohérences de paramétrage à figer (impact direct sur les chiffres)

| Paramètre | Valeur dans le code | Valeur dans `.env.example` | Enjeu |
|---|---|---|---|
| Taux de commission | 15 % (`config/brio.php`) | 20 % | **5 pts de revenu** d'écart |
| Pays par défaut | BE (compta/KYB) | FR (FEC, taxes) | Taux de TVA & obligations |
| Plafond coût IA/jour | 1 $ | 10 $ | Budget IA |
| Auto-approbation KYC | activée | désactivée | Risque conformité |

- **Recommandation :** figer explicitement ces valeurs en production — elles conditionnent l'exactitude du chiffre d'affaires et de la TVA.

### 5.3 🟡 Remboursements automatiques de litige

Plafond d'auto-remboursement sans intervention humaine configuré à **100 €** (`DISPUTES_AUTO_REFUND_MAX`). À valider par la direction financière (montant acceptable ?).

## 6. Avis sur la fiabilité comptable

**Solide sur les fondations, avec des décisions à trancher et deux points de conformité à finaliser.**

**Points forts (rassurants) :** partie double avec contrôle d'équilibre bloquant, export FEC conforme DGFiP, clôture verrouillée et auditée, idempotence/traçabilité systématiques, réconciliation Stripe, et **les 4 bugs « argent » du 8 juin corrigés** (risque de double paiement levé).

**Réserves à lever avant un usage comptable réel :**

1. 🟡 **Trancher le modèle de revenu** (principal vs agent) — décision prioritaire, impacte le CA et la TVA.
2. 🟡 **Numérotation des factures** séquentielle continue (non conforme en l'état).
3. 🟡 **Finaliser Factur-X** selon l'échéance légale.
4. 🟡 **Figer les paramètres** : SIREN FEC, commission 15 %/20 %, pays BE/FR.
5. 🟡 **Activer l'auto-postage** comptable (désactivé par défaut) après validation du paramétrage.

> **En résumé :** le moteur comptable est bien construit et les risques de perte d'argent connus sont corrigés. Ce qui reste relève de **choix comptables et de conformité** que seul le comptable/DAF peut arbitrer — à figer **avant** la première clôture et le premier export fiscal.

*Réserves : les montants en euros sont des ordres de grandeur (tarifs publics + hypothèses de volume), à affiner avec les contrats réels. L'hébergement n'est pas chiffrable depuis le code (demander la facture cloud).*
