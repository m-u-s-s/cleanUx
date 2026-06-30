## 1. Résumé exécutif

Cette projection estime le **potentiel de revenus** de la plateforme CleanUx / brio, à partir du **modèle économique réellement codé** (commission sur transactions via Stripe Connect, abonnements Premium, assurance, pourboires, FX) et d'hypothèses de volume standards pour une marketplace de services en Belgique / France.

**Message central :** le revenu d'une marketplace est **proportionnel au volume de réservations**. Il n'existe donc pas un chiffre unique, mais une fourchette pilotée par trois leviers — le **volume** (réservations/mois), le **panier moyen** (€/prestation) et le **taux de commission** (15-20 %).

| Régime | Réservations/mois | CA plateforme / an | Net annuel* |
|---|---|---|---|
| Lancement local | 1 000 | **~151 k€** | ~127 k€ |
| Traction régionale | 8 000 | **~1,2 M€** | ~1,0 M€ |
| Grande échelle | 40 000 | **~6,0 M€** | ~5,1 M€ |

*\*Net après coûts variables (Stripe, SMS, KYC…), **avant** salaires et marketing.*

> ⚠️ **Revenu actuel = 0 €.** La plateforme n'est pas encore lancée (services en mode simulation). Ces montants sont un **potentiel**, conditionné à l'acquisition de clients/prestataires et à la **liquidité** du marché — le vrai goulot d'étranglement, pas la technique.

## 2. Sources de revenu (modèle codé)

| Source | Statut dans le code | Réglage actuel | Potentiel |
|---|---|---|---|
| **Commission sur transactions** | ✅ `CommissionService` + Stripe Connect | 15 % (`config/cleanux.php`) à 20 % (`.env`), min. 2 € | **Cœur du revenu** |
| **Abonnements Premium** | ✅ Cashier + `STRIPE_PREMIUM_PRICE_ID` | À activer | Revenu récurrent, marge ~pure |
| **Assurance** | ✅ module Insurance | Pass-through (marge 0) | Marge à définir |
| **Pourboires** | ✅ `TipService` | 0 % prélevé | Levier dormant |
| **Change (FX)** | ✅ `FxService` | 0 % de marge | Levier dormant |
| **Contrats B2B / work orders** | 🔧 Spécifié (BLOCK 11-17), non fusionné | — | Plus gros upside futur |

## 3. Économie unitaire (par réservation)

**Hypothèses de base :** panier moyen **70 €**, commission **18 %** (milieu de fourchette).

| Élément | Montant | % du GMV |
|---|---|---|
| Valeur de la prestation (GMV) | 70,00 € | 100 % |
| **Commission brute plateforme** | **12,60 €** | 18 % |
| − Frais Stripe (~1,8 % + 0,25 €) | −1,50 € | −2,1 % |
| − SMS / géoloc / KYC amorti / divers | −0,50 € | −0,7 % |
| **= Contribution nette / réservation** | **≈ 10,60 €** | **≈ 15 %** |

> Sur chaque réservation de 70 €, il reste **~10,60 € net** (avant coûts fixes et salaires). L'économie unitaire est **saine** : la marge de contribution est largement positive dès la première transaction.

## 4. Potentiel selon le volume (régime établi)

| Réservations / mois | GMV / mois | CA plateforme (commission 18 %) | Net / mois (~15 % GMV) | **CA plateforme / an** |
|---|---|---|---|---|
| 250 (pilote) | 17 500 € | 3 150 € | ~2 650 € | **~38 k€** |
| 1 000 | 70 000 € | 12 600 € | ~10 600 € | **~151 k€** |
| 5 000 | 350 000 € | 63 000 € | ~53 000 € | **~756 k€** |
| 15 000 | 1 050 000 € | 189 000 € | ~159 000 € | **~2,3 M€** |
| 40 000 | 2 800 000 € | 504 000 € | ~424 000 € | **~6,0 M€** |

## 5. Projection sur 3 ans — 3 scénarios

Chaque scénario suppose une montée en charge progressive (la 1ʳᵉ année est toujours la plus difficile : il faut amorcer la liquidité).

### 🔵 Scénario Prudent (1 ville, démarrage lent)

| | Réservations/mois (moy.) | GMV annuel | **CA plateforme** | Net annuel |
|---|---|---|---|---|
| Année 1 | 150 | 126 k€ | **~23 k€** | ~19 k€ |
| Année 2 | 700 | 588 k€ | **~106 k€** | ~88 k€ |
| Année 3 | 2 000 | 1,68 M€ | **~302 k€** | ~252 k€ |

### 🟢 Scénario Réaliste (traction régionale)

| | Réservations/mois (moy.) | GMV annuel | **CA plateforme** | Net annuel |
|---|---|---|---|---|
| Année 1 | 400 | 336 k€ | **~60 k€** | ~50 k€ |
| Année 2 | 2 500 | 2,1 M€ | **~378 k€** | ~315 k€ |
| Année 3 | 8 000 | 6,72 M€ | **~1,21 M€** | ~1,01 M€ |

### 🟠 Scénario Optimiste (multi-villes, exécution forte)

| | Réservations/mois (moy.) | GMV annuel | **CA plateforme** | Net annuel |
|---|---|---|---|---|
| Année 1 | 800 | 672 k€ | **~121 k€** | ~101 k€ |
| Année 2 | 6 000 | 5,04 M€ | **~907 k€** | ~756 k€ |
| Année 3 | 20 000 | 16,8 M€ | **~3,02 M€** | ~2,52 M€ |

*« CA plateforme » = commission brute encaissée = GMV × taux de commission. Net ≈ 83 % du CA (après coûts variables), avant salaires et marketing.*

## 6. Sensibilité aux leviers

**Effet du panier moyen et du taux de commission** sur le CA annuel, à volume fixe de **1 000 réservations/mois** (12 000/an) :

| | Commission 15 % | Commission 18 % | Commission 20 % |
|---|---|---|---|
| Panier 50 € (GMV 600 k€) | 90 k€ | 108 k€ | 120 k€ |
| **Panier 70 € (GMV 840 k€)** | 126 k€ | **151 k€** | 168 k€ |
| Panier 100 € (GMV 1,2 M€) | 180 k€ | 216 k€ | 240 k€ |

> **Lecture :** passer la commission de 15 % à 20 % = **+33 %** de revenu, sans aucun effort technique. Monter le panier moyen de 70 € à 100 € (en poussant les métiers à forte valeur : peinture, rénovation, toiture) = **+43 %**.

## 7. Leviers d'upside (déjà présents dans le code)

- 🟢 **Relever le take rate** 15 % → 20 % : +33 % de revenu (simple réglage).
- 🟢 **Augmenter le panier moyen** : orienter vers les métiers chers (peinture, rénovation).
- 🟢 **Abonnements Premium** : ex. 300 prestataires × 19,90 €/mois ≈ **+72 k€/an**, marge quasi pure et **récurrente**.
- 🟢 **Activer la commission sur pourboires + marge FX** : aujourd'hui à 0 %.
- 🟠 **B2B / contrats récurrents** (BLOCK 11-17) : panier élevé + revenu récurrent = **plus gros potentiel**, mais module à développer.

## 8. Seuil de rentabilité & coûts à prévoir

**Coûts fixes mensuels typiques** (au démarrage) :

| Poste | Estimation /mois |
|---|---|
| 1 développeur (coût chargé) | ~5 500 € |
| Infrastructure + outils (cloud, Sentry, EAS…) | ~1 500 € |
| Marketing d'amorçage | ~3 000 € |
| **Total fixe** | **~10 000 €** |

**Seuil de rentabilité :** avec ~10,60 € de contribution nette/réservation →

> **≈ 950 réservations/mois** (~66 500 € de GMV/mois) suffisent à couvrir un développeur, l'infrastructure et un budget marketing de base.

**Unit economics d'acquisition (à surveiller) :**

- **LTV** (valeur vie client) ≈ 8 réservations × 10,60 € ≈ **85 €** (hors récurrence/abonnement).
- **CAC cible** : < 1/3 de la LTV → viser **< 28 €** par client acquis pour rester sain (ratio LTV/CAC > 3).

## 9. Risques & réserves (à lire absolument)

1. 🔴 **Revenu actuel = 0 €** — projection de potentiel, non d'acquis. Plateforme non lancée (services en mock).
2. 🔴 **Liquidité = le vrai défi** — réussir à avoir *simultanément* assez de prestataires ET de clients. 80 % des marketplaces échouent ici, pas sur l'économie unitaire.
3. 🟠 **Coût d'acquisition non inclus** dans les « nets » ci-dessus tant qu'il n'y a pas de bouche-à-oreille — à déduire.
4. 🟠 **Coûts fixes (salaires)** dès la sortie du mode solo développeur (cf. risque « bus factor = 1 » de l'audit de pilotage).
5. 🟡 **Prérequis techniques au revenu** : activer les vrais fournisseurs (Stripe live, SMS, géoloc), trancher le modèle de TVA et la numérotation des factures (cf. audits DevOps & Comptable).
6. 🟡 **Ordres de grandeur** sur hypothèses standards BE/FR — à affiner avec le marché cible réel.

## 10. Conclusion

> Avec une commission de 15-20 %, le projet peut rapporter **~150 k€/an de CA à 1 000 réservations/mois**, **~1,2 M€/an à 8 000/mois**, et **dépasser 3 M€/an** à grande échelle. L'économie unitaire est saine et le seuil de rentabilité atteignable (~950 réservations/mois). **Le déterminant du succès n'est pas la technique — déjà solide — mais la capacité à amorcer la liquidité et à maîtriser le coût d'acquisition.**

*Méthodologie : modèle marketplace GMV × take rate, économie unitaire ascendante, 3 scénarios (prudent/réaliste/optimiste). Hypothèses : panier 70 €, commission 18 %, coûts variables ~3 % du GMV. Tous les montants sont des estimations indicatives, hors fiscalité sur les bénéfices.*
