# Nouveau devis — proposer, encaisser, refuser, arbitrer

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.
> Steps use checkbox (`- [ ]`) syntax.

**Goal :** permettre au prestataire de réviser un devis sous-doté avant de commencer, encaisser la
différence sans jamais perdre la garantie existante, et sanctionner l'abus des deux côtés sans
jamais punir sur une seule mission.

**Architecture :** une table de révisions (constat) séparée de son encaissement (Stripe), sur le
modèle éprouvé de `MissionTimeSettlement`. La révision garde l'empreinte d'origine et ouvre un
**complément** — Stripe ne capture jamais plus que l'autorisé. Les sanctions naissent d'un motif
observé sur plusieurs contreparties distinctes, jamais d'un incident isolé.

**Spec :** `docs/superpowers/specs/2026-08-18-mission-terrain-design.md` §§ 3 et 4

## Global Constraints

- Le nouveau devis n'existe **que** sur `MissionEngine::DOMICILE`.
- Le prestataire saisit le **prix du service**, jamais le total à payer.
- **Aucune sanction au premier refus**, des deux côtés.
- L'empreinte d'origine n'est **jamais annulée** avant que le complément soit autorisé.
- `charged` ne s'écrit **que** si Stripe l'a dit — l'erreur de `MissionExtraService` à ne pas refaire.
- Une seule révision vivante par mission.

## La règle de tarification, tranchée

Le prestataire annonce un **prix de service**. Le total révisé se recalcule ainsi :

| Remise | Traitement | Pourquoi |
|---|---|---|
| Code promo `percent` | **recalculé** sur le nouveau prix, plafonné par `max_discount_amount` | c'est le terme du code ; il grandit avec le prix, en faveur du client |
| Code promo `fixed_amount` | **inchangé**, borné au nouveau total | un bon de 10 € reste 10 € |
| Code promo `free_first_booking` | 100 % | inchangé |
| Toute autre remise déjà accordée | **reportée telle quelle**, en montant | elle a été accordée sur un service de même nature |

Le client voit les deux totaux, ligne à ligne, avec le nom du code appliqué.

---

### Task 1 : la table des révisions

- Create: `database/migrations/2026_09_04_090000_creer_les_revisions_de_devis.php`
- Create: `app/Models/MissionQuoteRevision.php`
- Test: `tests/Feature/Missions/NouveauDevisTest.php`

Colonnes : voir spec § 3.6. `status` ∈ `proposed|accepted|declined|expired|payment_failed|withdrawn`.
`client_decision` ∈ `continue|stop`.

- [ ] Step 1 : migration + modèle (`$fillable`, casts, relations `mission`, `booking`, `proposedBy`)
- [ ] Step 2 : `php artisan migrate` et vérifier le schéma réel
- [ ] Step 3 : commit

### Task 2 : la fenêtre de révision

- Create: `app/Services/Missions/QuoteRevisionWindow.php`

`etat(Mission): array{open, closes_at, reason}` — fermée au **premier** de :
tâche cochée · photo « après » · échéance (`actual_start_at + 30 min`, ou `arrived_at + 30 min` tant
que la mission n'a pas démarré). Chaque tâche client ajoutée rouvre `requote_reopen_minutes`.

- [ ] Step 1 : test des trois fermetures + les trois témoins
- [ ] Step 2 : implémentation
- [ ] Step 3 : commit

### Task 3 : la tarification révisée

- Create: `app/Services/Missions/QuoteRevisionPricing.php`

`recalculer(Booking, int $prixServiceCents): array{total_cents, breakdown}` selon le tableau
ci-dessus.

- [ ] Step 1 : test — promo 20 % sur 50 € puis révision à 300 € donne 240 €, code nommé dans le
      détail ; **témoin** : sans promo, 300 € donne 300 €
- [ ] Step 2 : implémentation
- [ ] Step 3 : commit

### Task 4 : proposer

- Create: `app/Services/Missions/MissionQuoteRevisionService.php`

`proposer(Mission, User, int $prixServiceCents, string $motif, array $mediaIds): MissionQuoteRevision`

Refus : moteur ≠ domicile · fenêtre fermée · aucune preuve · révision déjà vivante · prestataire
suspendu · prix ≤ prix d'origine.

- [ ] Step 1 : test des six refus + le témoin de la proposition acceptée
- [ ] Step 2 : implémentation
- [ ] Step 3 : commit

### Task 5 : accepter — le complément Stripe

`accepter(MissionQuoteRevision, User $client, string $paymentMethodId): MissionQuoteRevision`

1. crée l'intent de complément (`revised_total − original_total`) — **jamais** avant, jamais après
2. si `succeeded`/`requires_capture` → écrit `devis_estime`, `estimated_price`,
   `payment_amount_cents`, `pricing_snapshot`, statut `accepted`
3. sinon → statut `payment_failed`, l'empreinte d'origine intacte, le motif rendu au client

- [ ] Step 1 : test — l'échec laisse `#1` intacte et la révision en `payment_failed` ; **témoin** :
      le succès porte le total révisé et la commission recalculée
- [ ] Step 2 : implémentation
- [ ] Step 3 : commit

### Task 6 : refuser, et le choix du client

`refuser(MissionQuoteRevision, User $client, string $decision)` avec `continue|stop`.

- `continue` → la mission suit au prix d'origine
- `stop` → annulation par le tuyau commun, **0 €** au prestataire, empreinte relâchée

- [ ] Step 1 : test des deux branches + le 0 €
- [ ] Step 2 : implémentation
- [ ] Step 3 : commit

### Task 7 : l'arbitre et les sanctions

- Create: migrations `mission_dispute_signals`, `mission_feature_suspensions`
- Create: `app/Services/Missions/QuoteRevisionArbiter.php`
- Create: `app/Services/Risk/Rules/RequoteAbuseRule.php`, `UnderDeclarationRule.php`

Verdict : ≥ 3 occurrences ET ≥ 2 contreparties distinctes. Sanctions 14 j / 60 j / définitif.

- [ ] Step 1 : test — aucune sanction au 1er ; sanction au 3e avec 2 contreparties ; **témoin** :
      3 occurrences chez UNE seule contrepartie ne sanctionnent pas
- [ ] Step 2 : implémentation
- [ ] Step 3 : commit

### Task 8 : les API

- `POST /api/provider/missions/{mission}/quote-revision`
- `GET  /api/client/bookings/{booking}/onsite/quote-revision`
- `POST .../quote-revision/{revision}/accept` · `.../decline`

- [ ] Step 1 : tests d'API avec leur garde 403 et son témoin
- [ ] Step 2 : implémentation
- [ ] Step 3 : commit

### Task 9 : suite ciblée, PHPStan sans chemin, `migrate --pretend`
