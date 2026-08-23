# Parcours

Ce qui se passe entre le clic d'un client et le paiement du prestataire. Chaque étape nomme le
code qui la porte.

## Vue d'ensemble

```
1. Choisir      secteur → métier → questions          OrderJourney
2. Chiffrer     prix calculé, sans compte             PricingEngine
3. Confirmer    identité, réservation créée           OrderConfirmationService
4. Répartir     trouver et engager un prestataire     DispatchEngine
5. Exécuter     se déplacer, arriver, faire, clore    MissionAssignmentService
6. Payer        capturer, commissionner, créditer     MissionPaymentService
```

## 1. Choisir

`app/Livewire/OrderEngine/OrderJourney.php`

Le client choisit un secteur, puis un métier de ce secteur, puis répond aux questions du métier.

Deux filtres décident de ce qu'il voit :

- `where('sector_id', …)` — un métier sans secteur n'apparaît **jamais**
- `servableEnMode()` — le mode demandé doit être ouvert pour ce couple (métier, zone)

L'adresse fixe la zone. Sans zone résolue, la confirmation refusera : le répartiteur ne saurait
pas qui envoyer.

## 2. Chiffrer

`app/Services/OrderEngine/PricingEngine.php`

Le prix se compose :

```
prix de base du métier
  + impact de chaque réponse       (forfait, par unité, multiplicateur)
  × multiplicateur de zone          trade_zone_pricing.surge_multiplier
  × multiplicateur d'urgence        si mode immédiat
  = fourchette min–max
```

Le résultat est une **fourchette**, pas un nombre : certaines réponses ouvrent une incertitude
que seule la visite lèvera. Un métier peut aussi être marqué « sur devis » et ne rien afficher.

La devise vient de la zone, par `CountryMarketResolver::deviseAttendue()`. Jamais d'un défaut.

## 3. Confirmer

`app/Services/OrderEngine/OrderConfirmationService.php`

C'est le seul moment où une identité est nécessaire.

Le service :

1. Résout la zone une dernière fois, si le panier n'en a pas.
2. Vérifie que la commande est confirmable — sinon il **dit pourquoi**, pour que l'écran grise
   son bouton avec un motif plutôt que de laisser cliquer.
3. Verrouille le panier et crée une réservation par article.
4. **Fige le devis** : le prix accepté engage, même si la grille change demain.
5. Entre dans le répartiteur, dans la même transaction.

Cette opération est **idempotente**. Un double-clic, un rechargement ou un retour arrière renvoie
la même réservation — sans quoi le client se retrouverait avec deux commandes et deux
pré-autorisations bancaires.

## 4. Répartir

`app/Services/Dispatch/DispatchEngine.php` — porte d'entrée unique.

### Mode immédiat

```
CandidateFinder cherche les prestataires    métier + zone + en ligne + rayon
        ↓
Vague 1 : les N plus proches                offre diffusée en temps réel
        ↓ 20 secondes
Vague 2 : les suivants                      si personne n'a accepté
        ↓
Le premier qui accepte emporte la mission   les autres offres tombent
```

`CandidateFinder` filtre sur la présence vivante, pas sur une intention déclarée. Il n'y a plus
de repli ouvert sur le métier : un prestataire qui ne déclare pas ce métier n'est pas candidat.

### Mode planifié

Le prestataire reçoit une proposition sans limite de temps courte. S'il l'accepte, la mission lui
revient ; sinon elle repart en attribution.

## 5. Exécuter

| Étape | Ce qui se passe | Trace |
|---|---|---|
| Accepter | La mission devient `assigned` | `mission_assignments` |
| Partir | Statut `en_route`, suivi ouvert | `trip_tracking_sessions` |
| Arriver | Le client montre un code à 6 chiffres, le prestataire le saisit | présence confirmée |
| Consulter | La fiche d'accès s'ouvre : étage, digicode, consignes du client | `MissionAccessSheetService` |
| Travailler | Photos avant/après, liste de contrôle, suppléments éventuels | `mission_media`, `mission_checklists` |
| Clore | La mission puis la réservation passent à `termine` | l'avis client devient possible |

La liste de contrôle qui **bloque** la clôture est `mission_checklists`. Deux autres tables
portent des listes voisines et ne bloquent rien.

## 6. Payer

`app/Services/Payments/MissionPaymentService.php`

Stripe Connect en **charge à destination** :

```
Le client paie 100 €
        ↓
Stripe crédite le compte du prestataire         85 €
Stripe prélève la commission plateforme         15 €
```

La plateforme n'exécute **aucun virement**. Déclencher un `Stripe\Payout` paierait une seconde
fois.

### Empreinte et capture

Une pré-autorisation est prise à la confirmation. Elle est capturée à la clôture. Si le client
annule tardivement, les frais d'annulation sont **capturés partiellement** sur cette empreinte —
Stripe libère le solde dans le même appel.

Les frais sont écrits à trois endroits, chacun pour une raison :

| Où | Pourquoi |
|---|---|
| `bookings.cancellation_fee_amount` | Le résumé agrégeable, en euros — l'écran d'analyse le somme |
| `bookings.metadata` | Lu par des réponses d'API et des tests |
| `booking_cancellations_v2` | Le détail complet du calcul |

### Facturation au temps

Quand le métier porte `hourly_billing`, la facturation suit le temps réellement passé : un
compteur démarre à l'arrivée, le prestataire peut demander une prolongation, et le règlement se
fait hors session sur le montant final.

## Ce qui peut mal tourner

| Symptôme | Cause fréquente |
|---|---|
| Le métier n'apparaît pas dans le parcours | `sector_id` nul, ou aucune ligne dans `trade_zone_pricing` |
| La confirmation refuse sans motif clair | La zone n'a pas pu être résolue depuis l'adresse |
| Personne ne reçoit l'offre immédiate | Aucun prestataire en ligne dans le rayon, ou `asap_enabled` à faux |
| Le prestataire ne voit pas les consignes | Elles vivent dans `customer_comment`, pas dans `notes` |
| Le paiement reste en attente | L'empreinte n'a jamais été capturée à la clôture |

## Ensuite

- [API](api.md) — appeler ces étapes depuis une application
- [Domaine](domaine.md) — le sens précis des termes employés ici
