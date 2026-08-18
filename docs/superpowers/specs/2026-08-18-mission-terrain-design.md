# Mission terrain — trois moteurs, to-do list client, nouveau devis, et leur réglementation

**Date :** 2026-08-18
**État :** validé section par section avec le porteur, prêt pour le plan d'implémentation

## Ce qui est demandé

Sept chantiers, tous ancrés dans la mission terrain :

1. La checklist qualité devient la **to-do list du client**.
2. Le prestataire peut proposer un **nouveau devis** quand la demande a été sous-dotée, avec une
   réglementation qui empêche l'abus des deux côtés.
3. Les **trois moteurs de mission** (horaire, véhicule, domicile) deviennent explicites, exclusifs,
   et chacun a sa page terrain.
4. Les **modules utiles pendant la mission** sont attachés au bon moment ; sept ajouts sont validés.
5. Toutes les pages de mission, web et mobile, client et prestataire, sont reprises visuellement.
6. Un bouton **annuler** par rôle, avec un questionnaire dont l'admin est maître.
7. Rien de ce qui existe ne doit casser.

## Ce que le code dit — inventaire préalable

Établi en lisant le code, les migrations et la base de développement le 2026-08-18. Pas la
documentation.

| Constat | Où |
|---|---|
| Les 3 moteurs existent déjà, mais leur discriminant est éparpillé et **non exclusif** | `Booking::estUneCourse()` · `HourlyRateResolver::seFactureALHeure()` |
| `missions.mission_type` porte **une autre notion** (standard / enterprise / batched_execution) | `MissionFromRendezVousSyncService:134` |
| Une seule checklist barre la clôture ; il en existe trois | `MissionLifecycleService::assertRequiredChecklistCompleted()` |
| Le gabarit de checklist pose 6 tâches obligatoires génériques | `MissionChecklistService::resolveTemplate()` |
| Le supplément est plafonné à 500 €, et son commentaire réclame déjà le « nouveau devis » | `MissionExtraService:42-45` |
| Une empreinte Stripe se capture pour **moins**, jamais pour **plus** | `MissionPaymentService::capture()` |
| `devis_estime` et `payment_amount_cents` ne doivent pas être gonflés après coup | `HourlySettlementService:22` |
| Le SOS existe et n'est pas atteignable depuis le terrain | `SafetyScreen.tsx` |
| Le renfort existe et n'est atteignable que par le chef d'équipe, sur le web | `TeamLeadOperationsService` |
| L'annulation stocke déjà `reason_code`, `exempt_applied`, `max_per_user_per_30d` — **jamais remplis** | `booking_cancellations_v2` · `cancellation_exempt_reasons` |
| L'API d'annulation n'accepte qu'un texte libre | `ProviderCancellationController:32` |
| `MissionTrackingScreen:258` code une couleur en dur — rectangle blanc en mode sombre | mobile client |
| La carte d'accueil prestataire n'affiche **pas** la route active | `ProviderMap.tsx` |
| `service_name` valait `null` sur toute mission née du moteur de commande | corrigé le 2026-08-18 |

## Principes qui gouvernent toute la spec

1. **Une notion, un endroit.** Le défaut dominant de ce dépôt est d'avoir deux notions sous un même
   nom. Chaque fois qu'une notion voisine apparaît (supplément / nouveau devis, moteur / type de
   mission, checklist client / checklist qualité), elle reçoit son propre nom et sa propre colonne.
2. **Aucune nouvelle colonne booléenne à tenir à la main.** `TradeRouteRules` l'interdit
   explicitement, et pour une bonne raison : elle finit par contredire sa source.
3. **Ce qui a été vendu est figé sur la réservation.** Le catalogue peut changer demain ; une
   mission en cours ne change pas de nature.
4. **Aucun verdict sur une mission isolée.** Les sanctions naissent d'un motif observé sur
   plusieurs contreparties distinctes.
5. **On ne pose que des questions vérifiables ou engageantes.** Un questionnaire entièrement
   déclaratif est un menu d'évitement.
6. **Toute écriture administrative passe par un service du domaine**, jamais par une écriture de
   colonne.

---

# 1. Les trois moteurs de mission

## 1.1 Le discriminant

Aucune colonne nouvelle. Les deux discriminants sont **déjà figés sur la réservation** :

| Moteur | Condition | Colonne, écrite à l'achat |
|---|---|---|
| `VEHICULE` | point de dépose présent | `bookings.dropoff_lat` / `dropoff_lng` |
| `HORAIRE` | temps acheté | `bookings.purchased_minutes` |
| `DOMICILE` | ni l'un ni l'autre | — |

```php
// app/Support/Domain/MissionEngine.php
final class MissionEngine
{
    public const VEHICULE = 'vehicule';
    public const HORAIRE  = 'horaire';
    public const DOMICILE = 'domicile';

    public static function pourReservation(Booking $booking): string;
    public static function pourMission(Mission $mission): string;   // délègue à la réservation
}
```

L'ordre est une **priorité stricte**, et c'est ce qui rend les trois exclusifs : véhicule d'abord.
Une course vendue au temps reste une course — on ne demande pas six chiffres à quelqu'un au volant.

## 1.2 Ce que ce résolveur corrige

`hourly_billing` est lu en direct sur le métier : un administrateur qui décoche la case change la
nature d'une mission **en cours d'exécution**. `purchased_minutes` est gravé à l'achat, il ne bouge
plus. Le moteur devient donc stable pour toute la durée de vie de la mission.

## 1.3 Ce qui n'est PAS fusionné

`MissionEngine` dit **quel parcours et quelle page**.
`HourlyRateResolver::seFactureALHeure()` dit **si le dépassement est facturable**.

Deux questions distinctes. `HourlyMissionClock:56` explique pourquoi il pose les deux : un compteur
allumé sur `purchased_minutes` seul afficherait un dépassement dont on ne saurait pas tirer une
facture. Ce comportement est conservé tel quel.

## 1.4 Comportement par moteur

| | DOMICILE | HORAIRE | VEHICULE |
|---|:--:|:--:|:--:|
| Code QR / 6 chiffres début et fin | ✅ | ✅ | ❌ |
| SMS de confirmation | ✅ | ✅ | ❌ |
| Checklist bloquante | ✅ | ✅ | ❌ |
| To-do list client | ✅ | ✅ | ❌ |
| Compteur et prolongation | ❌ | ✅ | ❌ |
| Nouveau devis | ✅ | ❌ | ❌ |
| Supplément | ✅ | ✅ | ❌ |
| Clôture géo-vérifiée sur le point de dépose | ❌ | ❌ | ✅ |
| Déclaration d'absence après délai | ❌ | ❌ | ✅ |
| Mission de plusieurs jours | ✅ | ❌ | ❌ |

Les trois premières lignes et la dernière sont **déjà le comportement actuel** : `setArrived()` saute
la génération de code sur une course, `ensureChecklist()` rend `null`, et `spine:check-stuck-missions`
alerte sans jamais clôturer. Le reste est à construire.

## 1.5 Portes fermées explicitement

Chaque service refuse ce qui n'est pas pour lui **avec un message qui dit pourquoi**, jamais en
silence — c'est déjà la doctrine de `RideLifecycleService`. Trois refus nouveaux :

- nouveau devis sur `HORAIRE` → « Cette mission est vendue au temps : utilisez la prolongation. »
- nouveau devis sur `VEHICULE` → « Le prix d'une course est fixé par le trajet. »
- to-do list sur `VEHICULE` → l'API ne l'expose pas ; l'écran ne l'affiche pas.

---

# 2. La to-do list du client

## 2.1 Elle écrit dans la table qui barre déjà la clôture

Aucune quatrième checklist. La liste du client **est** `mission_checklists` /
`mission_checklist_items`, celle que `assertRequiredChecklistCompleted()` interroge.

```sql
ALTER TABLE mission_checklist_items
  ADD source              VARCHAR(16) NOT NULL DEFAULT 'template',  -- client|template|provider
  ADD created_by_user_id  BIGINT UNSIGNED NULL,
  ADD locked_at           TIMESTAMP NULL;
```

## 2.2 Le gabarit automatique cesse d'imposer

`MissionChecklistService::ensureChecklist()` ne crée plus d'items obligatoires. Il crée la
`mission_checklists` vide, et le gabarit devient une **liste de suggestions** que le client ajoute
d'un tap depuis « Gérer ma mission ».

Conséquence assumée et voulue : **une mission dont le client n'a rien listé se clôture sans cocher
quoi que ce soit.**

## 2.3 La fenêtre

| Règle | Valeur |
|---|---|
| Ouverture | dès la confirmation de la réservation |
| Fermeture | **30 minutes après `actual_start_at`** |
| Réouverture de la révision côté prestataire | **6 minutes** par tâche ajoutée |

Le client voit un minuteur, et la phrase qui l'engage, avant d'écrire :

> « Le prestataire ne pourra pas terminer tant que ces tâches ne sont pas faites.
> Vous pouvez modifier cette liste pendant encore 27 min. »

Une fois `locked_at` posé, l'API refuse toute écriture, avec le motif.

## 2.4 Qui coche

**Le prestataire coche.** Le client ne revalide pas ligne par ligne : il a déjà le dernier mot, une
fois, avec le **code de fin à six chiffres** qu'il ne donne que s'il est satisfait. Deux serrures
pour une même porte permettraient à un client absent ou de mauvaise foi de retenir le prestataire
indéfiniment.

## 2.5 Périmètre

`DOMICILE` et `HORAIRE`. Sur `VEHICULE`, `ensureChecklist()` rend déjà `null` — rien à faire.

---

# 3. Le nouveau devis

## 3.1 Sa frontière avec le supplément

Deux notions, deux gestes, deux tables.

| | **Nouveau devis** | **Supplément** *(existant)* |
|---|---|---|
| Quand | à l'arrivée, **avant tout travail** | **pendant**, sur découverte |
| Ce qu'il dit | « le devis était faux dès le départ » | « il y a ceci en plus » |
| Effet sur le prix | **remplace** | **s'ajoute** |
| Plafond | aucun | 500 € |
| Si refusé | le client choisit : continuer / arrêter | la tâche ne se fait pas |
| Moteurs | `DOMICILE` seul | `DOMICILE` + `HORAIRE` |

Exemple qui tranche : un plombier vient pour un siphon, démonte, découvre que le problème est dans
les tuyaux — **il a touché**, c'est un supplément. Le nouveau devis lui est déjà fermé.

## 3.2 La fenêtre, et ce qui la ferme

Ouverte à `arrived`. Fermée au **premier** de ces trois événements :

```
1. une tâche de la checklist est cochée
2. une photo « après » est enregistrée
3. 30 minutes après actual_start_at
```

La photo « avant » ne ferme rien : elle se prend justement pour constater l'écart qui justifie la
révision. Chaque tâche ajoutée par le client rouvre 6 minutes.

**Tant que la mission n'a pas démarré**, `actual_start_at` est nul et la troisième borne n'existe
pas : la fenêtre serait ouverte indéfiniment sur une mission qui stagne à `arrived`. Elle est donc
bornée à la proposition et rouverte au démarrage :

```
window_closes_at = actual_start_at ? actual_start_at + 30 min
                                  : arrived_at      + 30 min

au passage a `started`, la revision vivante voit sa fenetre recalee
sur actual_start_at + 30 min.
```

## 3.3 Ce que le prestataire saisit

**Le prix du service, jamais le total à payer.** S'il tapait le total, la remise du client serait
silencieusement avalée. Le serveur repasse par la même chaîne que la commande d'origine :

```
prix de service saisi par le prestataire
  -> remise contrat (ContractPricingResolver)
  -> code promo (BookingPromoCodeApplier)
  -> promotion
  -> credits client
  = nouveau total, presente ligne a ligne au client
```

Motif **obligatoire** et **au moins une photo** obligatoire : sans preuve, pas de révision.

## 3.4 L'encaissement — empreinte d'origine + complément

Décision du porteur. Stripe ne capture jamais plus que l'autorisé ; on n'annule donc rien, on
complète.

```
AVANT        PaymentIntent #1 = 50 EUR   autorise, non capture

ACCEPTATION  PaymentIntent #2 = 250 EUR  autorise pendant que le client est a l'ecran (SCA possible)

CLOTURE      capture #1 + capture #2 = 300 EUR
             + supplements acceptes, sur leurs propres intents

SI #2 ECHOUE le devis revise retombe « refuse », le client lit pourquoi,
             #1 n'a jamais ete touchee, le prestataire n'est jamais sans garantie.
```

Bloqué sur la carte : **exactement 300 €**, jamais un centime de plus.

**Si le nouveau prix est INFÉRIEUR** : aucun second intent. On note le montant révisé et la clôture
capture partiellement #1 — Stripe libère le solde.

### Ce qu'on écrit, et ce qu'on ne touche pas

`HourlySettlementService:22` interdit de gonfler `devis_estime` et `payment_amount_cents` après
coup, parce que la commission se calcule dessus. La révision étant **acceptée avant le travail**, la
situation est différente d'un règlement a posteriori : le contrat est renégocié, pas complété.

On écrit donc, à l'acceptation seulement, et dans une transaction :

```
bookings.devis_estime          <- nouveau total
bookings.estimated_price       <- nouveau total
bookings.payment_amount_cents  <- somme des deux intents autorises
bookings.pricing_snapshot      <- instantane complet, ancien total conserve
mission_quote_revisions        <- la trace, avec les deux totaux et les deux intents
```

La commission se recalcule sur le montant réellement autorisé : aucun écart entre ce qui est
encaissé et ce qui est reversé.

## 3.5 Le refus

```
Le client refuse
   |
Une question, deux reponses :

  - « Continuez au prix d'origine »
       le prestataire choisit alors :
         . poursuivre et terminer normalement    -> 50 EUR
         . cloturer maintenant, travail en l'etat -> 50 EUR

  - « Arretez l'intervention »
       -> annulation par le tuyau commun (section 8), reason_code dedie
       -> le prestataire n'a rien fait : 0 EUR
       -> l'empreinte du client est relachee integralement
```

Le prestataire n'est jamais forcé de faire 300 € de travail pour 50 €. Et il n'est jamais payé pour
un travail qu'il n'a pas commencé. Les deux règles ensemble ferment les deux abus symétriques.

Dans tous les cas : **aucune sanction immédiate**. Le dossier part à l'arbitre.

## 3.6 Schéma

```sql
CREATE TABLE mission_quote_revisions (
  id                       BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  mission_id               BIGINT UNSIGNED NOT NULL,
  booking_id               BIGINT UNSIGNED NOT NULL,
  proposed_by_user_id      BIGINT UNSIGNED NOT NULL,

  original_total_cents     INT UNSIGNED NOT NULL,
  proposed_service_cents   INT UNSIGNED NOT NULL,
  revised_total_cents      INT UNSIGNED NOT NULL,
  discount_breakdown       JSON NULL,
  currency                 CHAR(3) NOT NULL,

  reason_code              VARCHAR(64) NOT NULL,
  reason_text              TEXT NOT NULL,
  evidence_media_ids       JSON NOT NULL,

  status                   VARCHAR(24) NOT NULL,
  window_closes_at         TIMESTAMP NOT NULL,
  responded_at             TIMESTAMP NULL,
  client_decision          VARCHAR(16) NULL,
  top_up_payment_intent_id VARCHAR(128) NULL,
  charged_at               TIMESTAMP NULL,
  last_error               VARCHAR(1000) NULL,

  metadata   JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
```

`status` : `proposed` · `accepted` · `declined` · `expired` · `payment_failed` · `withdrawn`.
`client_decision` : `continue` · `stop`.

**Une seule révision vivante par mission** — index unique partiel émulé par une garde applicative
(MySQL n'a pas d'index partiel) : le service refuse une proposition tant qu'une autre est
`proposed`. Deux propositions concurrentes rendraient le total indéterminé.

**Nom d'index** : tous les index de cette spec restent sous 64 caractères — au-delà, la migration
MySQL échoue et SQLite ne le dit pas.

---

# 4. L'arbitre : qui triche, et ce qu'il en coûte

## 4.1 Le principe

Aucun verdict ne se rend sur une mission isolée. Sur une intervention seule, la parole du client et
celle du prestataire se valent, et toute sanction serait un pari. Ce que la plateforme possède et
qu'aucun des deux ne contrôle, c'est **la contrepartie**.

## 4.2 Trois signaux, dont un seul est décisif

**S1 — Divergence de déclaration** *(charge le client)*
Écart mesurable entre `bookings.trade_form_answers` et ce que le prestataire constate, photos à
l'appui. Falsifiable par un prestataire seul : jamais suffisant.

**S2 — Écart au pair** *(charge le prestataire)*
Son taux de révision sur ce métier et cette zone, comparé à la médiane de ses pairs sur les
90 derniers jours. Falsifiable aussi : un quartier peut être réellement plus difficile.

**S3 — Concordance des tiers** *(l'arbitre)*
La seule chose que personne ne peut fabriquer seul :

- ce client a-t-il été révisé par **plusieurs prestataires distincts** ?
- ce prestataire révise-t-il chez **plusieurs clients distincts** que personne d'autre n'a jamais
  révisés ?

## 4.3 Le verdict

| Situation | Verdict | Sanction |
|---|---|---|
| 1er refus, quel qu'il soit | aucun | **aucune, des deux côtés** — on enregistre |
| Motif d'un seul côté : ≥ 3 occurrences ET ≥ 2 contreparties distinctes | tricheur identifié | graduée, sur lui seul |
| Signaux des deux côtés, ou non concluant | indécis | revue humaine (`RiskEvaluation` → `review` + `RiskHold`) |

**« Sans porter préjudice à la personne adverse » est structurellement garanti** : puisqu'aucun
verdict ne se rend sur une mission isolée, un menteur ne peut pas faire sanctionner un honnête — il
lui faudrait recruter plusieurs contreparties indépendantes mentant toutes dans le même sens.

## 4.4 Sanctions du prestataire

| **Verdict** rendu contre lui *(pas une simple révision refusée)* | Sanction |
|---|---|
| 1er | option « nouveau devis » suspendue **14 jours** |
| 2e | suspendue **60 jours** |
| 3e | suspendue **définitivement**, réactivable par l'admin seul |

Le compteur porte sur les **verdicts** du § 4.3, pas sur les révisions refusées. Un premier verdict
demande déjà 3 occurrences chez 2 contreparties distinctes : la première sanction n'arrive donc
jamais avant un motif établi.

Plus, dès la 1re : **malus de score** dans `MatchingScorer`.

> **Malus, pas exclusion.** S'il est le seul disponible sur une zone à 22 h, il reçoit quand même la
> mission. La plateforme ne perd jamais une course pour sanctionner quelqu'un.

## 4.5 Sanctions du client

Les quatre, par gravité croissante :

1. **Quote-part** du devis due à la plateforme quand le motif est établi.
2. **Frais d'annulation au palier supérieur** (`cancellation_policy_tiers`, déjà en place).
3. **Empreinte au haut de la fourchette** sur ses commandes suivantes : il ne paie que le réel, mais
   ne peut plus sous-doter.
4. **Blocage temporaire de la commande** — 7 jours, puis 30 — réactivable par l'admin.

**Alerte admin obligatoire à chaque blocage automatique**, des deux côtés.

## 4.6 L'entente entre les deux

La règle du 0 € rend l'entente gratuite : personne ne paie rien sur la plateforme, ils règlent en
liquide. Ce qui la détecte, puis ce qui la décourage.

**Détection** — une erreur d'appréciation est dispersée, une entente se **concentre** :

1. **Le couple qui se répète** : même prestataire + même client, 2e « révisé → refusé → arrêt » →
   revue humaine immédiate. On ne se trompe pas deux fois sur la même personne.
2. **Le prestataire qui s'arrête toujours** : ses pairs terminent après un refus, lui arrête.
3. **Le client qui disparaît** : il commandait régulièrement, il s'arrête net après cet arrêt-là.

**Dissuasion** — plus efficace que la détection :

- chaque « révisé → refusé → arrêt » compte au compteur d'abus du prestataire ;
- il compte aussi côté client ;
- **appel masqué** (§ 7.4 ①) : un prestataire qui ne connaît pas le numéro de son client ne peut pas
  lui proposer un arrangement ;
- au clic sur « arrêter », un encart rappelle au client que régler hors plateforme lui fait perdre
  garantie, assurance, recours et preuve de paiement.

## 4.7 Où ça vit

Deux règles nouvelles dans le moteur de risque existant, qui a déjà les décisions
`allow` / `review` / `block`, les seuils en base et les `RiskHold` :

```
app/Services/Risk/Rules/RequoteAbuseRule.php      -- charge le prestataire (S2 + S3)
app/Services/Risk/Rules/UnderDeclarationRule.php  -- charge le client     (S1 + S3)
```

Et une table de compteurs, lue par les deux règles et par l'admin :

```sql
CREATE TABLE mission_dispute_signals (
  id                 BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  mission_id         BIGINT UNSIGNED NOT NULL,
  booking_id         BIGINT UNSIGNED NOT NULL,
  quote_revision_id  BIGINT UNSIGNED NULL,
  cancellation_id    BIGINT UNSIGNED NULL,
  provider_user_id   BIGINT UNSIGNED NOT NULL,
  client_user_id     BIGINT UNSIGNED NOT NULL,
  signal_code        VARCHAR(48) NOT NULL,
  charged_side       VARCHAR(16) NOT NULL,
  outcome            VARCHAR(24) NOT NULL,
  evidence           JSON NULL,
  verdict            VARCHAR(24) NULL,
  verdict_at         TIMESTAMP NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);
```

`charged_side` : `client` · `provider` · `both` · `undetermined`.
`outcome` : `accepted` · `declined_continue` · `declined_stop` · `expired`.
`verdict` : `none` · `client_at_fault` · `provider_at_fault` · `inconclusive`.

Et les sanctions actives :

```sql
CREATE TABLE mission_feature_suspensions (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id          BIGINT UNSIGNED NOT NULL,
  feature          VARCHAR(48) NOT NULL,   -- quote_revision | ordering
  level            TINYINT UNSIGNED NOT NULL,
  starts_at        TIMESTAMP NOT NULL,
  ends_at          TIMESTAMP NULL,         -- NULL = definitif
  reason           TEXT NOT NULL,
  lifted_at        TIMESTAMP NULL,
  lifted_by_user_id BIGINT UNSIGNED NULL,
  lift_reason      TEXT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);
```

---

# 5. Les surfaces client

## 5.1 Le geste

```
ECRAN DE SUIVI (la carte, position du prestataire)
   |
   |   [ ETA 12 min . 3,4 km ]        encart existant, inchange
   |
   +-> [        Ma mission        ]   NOUVEAU, sous la carte
                  |
                  v
       feuille du bas (BottomSheet de @brio/shared, celui de HomeActionsSheet)
         . titre du service, client, adresse
         . « 1 chose attend votre reponse »
         . [ Gerer ma mission ]
         . Message      Signaler un litige
                  |
                  v
       GERER MA MISSION (page pleine)
```

La feuille porte un **aperçu**, pas le contenu. Une feuille qui contiendrait tout obligerait à
faire défiler un panneau à demi ouvert, main levée.

## 5.2 Ce que contient « Gérer ma mission »

Ordonné par **ce qui attend le client**, pas par thème.

| Rang | Bloc | DOMICILE | HORAIRE | VEHICULE |
|---|---|:--:|:--:|:--:|
| 1 | Nouveau devis — accepter / refuser, deux totaux ligne à ligne | ✅ | — | — |
| 2 | Suppléments en attente | ✅ | ✅ | — |
| 3 | Prolonger — compteur + options | — | ✅ | — |
| 4 | Mes codes — 6 chiffres + QR, début puis fin | ✅ | ✅ | — |
| 5 | Ma to-do list — édition + minuteur | ✅ | ✅ | — |
| 6 | Où en est-on — compteur / avancement / ETA | ✅ | ✅ | ✅ |
| 7 | Ce qui a été fait — avant/après, déroulé, imprévus | ✅ | ✅ | ✅ |
| 8 | Joindre & signaler — message, litige, annuler | ✅ | ✅ | ✅ |

## 5.3 Ce n'est pas un écran neuf

`OnSiteScreen` **est déjà** cette page : avancement, compteur, prolongation, suppléments, imprévus,
avant/après, déroulé. Elle se cache derrière un lien secondaire *« Voir le déroulé de
l'intervention »*. On la renomme, on l'enrichit, on lui donne sa porte d'entrée.

Créer une seconde page dupliquerait le fil et les photos, et les deux divergeraient au premier
correctif.

## 5.4 Le web : même logique, corps différent

```
   < 768 px                          >= 768 px
+--------------+              +----------+-------------+
|              |              |          |  Ma mission |
|    CARTE     |              |  CARTE   |  ---------  |
|              |              |          |  devis      |
+--------------+              |          |  codes      |
| [Ma mission] |              |          |  to-do      |
+--------------+              |          |  deroule    |
      v feuille du bas        +----------+-------------+
                                   panneau lateral, toujours ouvert
```

Même composant Livewire, même contenu, même ordre. Seul le contenant change. Le web possède déjà la
carte (`mission-live-tracking`) et les QR (`mission-qr-codes`) : il leur manquait d'être réunis.

## 5.5 Surfaces touchées

| Plateforme | Fichier | Nature |
|---|---|---|
| Mobile client | `MissionTrackingScreen.tsx` | + bouton « Ma mission », + feuille, correction couleur en dur |
| Mobile client | `OnSiteScreen.tsx` | devient « Gérer ma mission », + to-do, + devis, + codes, + annuler |
| Mobile client | `screens/components/MissionSheet.tsx` | **nouveau** |
| Web client | `livewire/client/mission-tracking.blade.php` | restructuré en carte + panneau |
| Web client | `Livewire/Client/GererMaMission.php` + vue | **nouveau** |

---

# 6. Les surfaces prestataire

## 6.1 Une page terrain par moteur

`MissionFieldScreen` sert aujourd'hui les trois moteurs avec des conditions `is_ride` éparpillées.
Elle est éclatée en trois écrans qui partagent leurs blocs :

```
MissionFieldScreen (aiguilleur, lit MissionEngine)
   |-- FieldDomicile   photos, fiche d'acces, imprevus, supplement,
   |                   NOUVEAU DEVIS, to-do list, cloture par code
   |-- FieldHoraire    compteur en tete, prolongation, photos, imprevus,
   |                   supplement, to-do list, cloture par code
   +-- FieldVehicule   carte plein ecran, client a bord, absence,
                       terminer la course. Zero code, zero checklist.
```

**Toutes les options existantes restent**, chacune atterrissant dans la page de son moteur. Aucune
n'est retirée.

## 6.2 Ce que le web doit rattraper

| Outil | Mobile | Web terrain | Action |
|---|:--:|:--:|---|
| Cycle de vie complet | ✅ | ✅ | — |
| Checklist bloquante | ✅ | ✅ | + to-do client |
| Photos avant/après | ✅ | ✅ | — |
| Signaler un imprévu | ✅ | ❌ | à brancher (le composant existe ailleurs) |
| Proposer un supplément | ✅ | ❌ | à brancher |
| Fiche d'accès | ✅ | ❌ | à brancher |
| Compteur horaire | ✅ | ❌ | à brancher |
| Partage de position | ✅ | ❌ | à brancher |
| Nouveau devis | — | — | **neuf des deux côtés** |

Trois de ces cinq manques existent déjà en composants web, sur **d'autres pages** : c'est un défaut
de joignabilité, pas de fonctionnalité.

La page est déjà découpée en sept partiels (`hero`, `status-rail`, `client-card`, `checklists`,
`media-gallery`, `action-hub`, `mobile-action-bar`) ; on y ajoute les blocs manquants et on les
conditionne au moteur.

## 6.3 La carte de la plateforme reste la carte principale

Décision du porteur. `ProviderMap` (accueil) affiche aujourd'hui les **marqueurs** des missions en
attente, pas la route active — alors que `TrackingScreen` la porte déjà
(`session.route.points`).

**À faire :** afficher la route active sur la carte d'accueil dès que le prestataire prend la route.
La navigation externe (Plans / Waze / Google Maps) reste un **bouton secondaire**, jamais le chemin
par défaut.

## 6.4 Joindre et signaler — dès le départ

```
mission acceptee | EN ROUTE ---- arrive ---- demarree ---- terminee
                 |
                 +-> Message  et  Litige apparaissent ICI, des deux cotes,
                     et ne disparaissent plus
```

Avant le départ, un fil de discussion n'a pas d'objet — et l'ouvrir trop tôt donne un canal de
démarchage hors plateforme.

Le bouton **Litige** ouvre le module existant pré-rempli avec la mission, pas un formulaire vierge.

> **À trancher au moment de brancher :** ce dépôt a deux systèmes de discussion, `chat_threads`
> (polymorphe, `context_type` / `context_id`) et `conversations` (porte `booking_id` et
> `mission_id`). On branche **celui que les applications mobiles utilisent déjà**, constaté au
> moment du lot ; on n'en crée pas un troisième.

## 6.5 Direction graphique

Composée avec ce qui existe : `GlassSurface`, `LuxeBackground`, `NightShell`, `fondDeFeuille`,
`apparenceDeBarre`, les jetons `useThemeColors` / `spacing` / `radius` / `shadows`, et
`useReducedMotion` / `a11y` pour tout ce qui bouge. Aucune couleur ni espacement en dur.

- **Domicile** — la matière et la preuve. Avant/après pleine largeur, to-do list en carte de verre,
  nouveau devis présenté comme un document qu'on signe, pas comme une alerte.
- **Horaire** — le temps est le sujet. Compteur en tête, chiffres larges, teinte qui évolue à
  l'approche de l'échéance, **vibration à l'échéance** (respectant `useReducedMotion`, doublée d'une
  annonce pour les lecteurs d'écran).
- **Véhicule** — la carte prend tout. Commandes flottantes sur verre, une seule action à la fois,
  au pouce. Zéro configuration avant de démarrer.

**Défaut à corriger dans l'écran touché :** `MissionTrackingScreen:258` code
`rgba(255,255,255,0.92)` en dur — rectangle blanc sur une carte de nuit. Passe sur `GlassSurface`.

---

# 7. Les modules pendant la mission

## 7.1 La règle de tri

75 modules existent (33 prestataire, 42 client). Un module ne mérite sa place que s'il répond à
« qu'est-ce que je peux faire, là, maintenant ? ». Un écran qui montre tout n'aide à rien.

## 7.2 Deux modules codés que personne ne peut atteindre

**Le SOS.** `SafetyScreen` porte deux niveaux (veille, urgence), prévient le contact d'urgence et
affiche l'accusé de réception. Son commentaire dit qu'il sert *« au moment où les mains tremblent »*.
Il vit dans le menu « Plus ». **Défaut de joignabilité, à réparer.**

**Le renfort.** `MissionReinforcementRequest` + `TeamLeadOperationsService` existent, atteignables
seulement depuis le centre du chef d'équipe, sur le web. **Défaut de joignabilité, à réparer.**

## 7.3 Ce qui s'attache, et quand

**Prestataire**

| Module | OFFRE | EN ROUTE | ARRIVÉ | DÉMARRÉE | CLÔTURE | APRÈS |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| Sécurité / SOS | | x | x | x | x | |
| Signaler un imprévu | | | x | x | x | |
| Litiges (pré-rempli) | | x | x | x | x | x |
| Conduite & véhicule *(VEHICULE)* | x | x | | | | |
| Renfort / équipe | | | x | x | | |
| Aide contextuelle | x | x | x | x | x | |
| Où me placer (heatmap) | | | | | | x |
| Planning (la suivante) | | | | | | x |

**Client**

| Module | EN ROUTE | ARRIVÉ | DÉMARRÉE | CLÔTURE | APRÈS |
|---|:--:|:--:|:--:|:--:|:--:|
| Messagerie | x | x | x | x | x |
| Litiges (pré-rempli) | x | x | x | x | x |
| Ma protection | | x | x | x | |
| Cartes bancaires *(si une autorisation échoue)* | | x | x | | |
| Mes lieux (consignes d'accès) | x | | | | |
| Devis reçus (le devis révisé s'y archive) | | x | x | | x |
| Prestataires favoris | | | | | x |
| Mon avis / NPS | | | | | x |

**Écartés délibérément** : portefeuille, revenus, badges, notes, KYC, dossier, métiers et zones,
historique, disponibilités, agenda, validation multiple, budget, finance, factures, abonnements,
fidélité, parrainage, RGPD, KYB, jetons API, devis IA, chantiers groupés, location de voitures,
statistiques, modèles récurrents, calendrier, offre premium.

## 7.4 Les sept ajouts validés

**① Appel masqué** — numéro relais entre client et prestataire. Protège les deux, et **garde le
contact sur la plateforme** : un prestataire qui ne connaît pas le numéro de son client ne peut pas
lui proposer un arrangement en liquide. Sert directement § 4.6. *Pool de numéros chez l'opérateur
SMS déjà en place.*

**② Renfort depuis le terrain** — expose au prestataire sur place le mécanisme existant. C'est **la
réponse opérationnelle au sous-devis** : au lieu de réviser ou d'abandonner, il appelle un collègue.

**③ Navigation externe en bouton secondaire** — ouvre Plans / Waze / Google Maps. **La carte de la
plateforme reste principale** ; la route active est ajoutée à la carte d'accueil prestataire.

**④ File d'attente hors-ligne** — les gestes du prestataire (cocher, photographier, arriver,
clôturer) sont mis en file localement et rejoués au retour du réseau. Condition d'un large public
multi-pays. *Touche le chemin de l'argent : clés d'idempotence strictes obligatoires, la clôture ne
doit jamais capturer deux fois.*

**⑤ Partager le suivi avec un tiers** — lien signé, à durée de vie limitée, pour le conjoint ou la
concierge qui ouvrira la porte. Moins d'arrivées ratées.

**⑥ Consigne d'accès de dernière minute** — le client pousse « le code est 4589 » pendant le trajet,
ça atterrit sur la fiche d'accès existante.

**⑦ Minuteur de retard** — passé N minutes après l'heure prévue, le client est prévenu
automatiquement et se voit proposer : attendre, reprogrammer, annuler sans frais. Pré-remplit le
questionnaire d'annulation (§ 8).

---

# 8. Annuler : le bouton, le questionnaire, et son administration

## 8.1 Le questionnaire n'est pas à inventer — il est à brancher

La base porte déjà tout :

```
booking_cancellations_v2
  reason_code      VARCHAR(64)   <- la reponse structuree ... jamais ecrite
  reason_text      TEXT
  actor_role       VARCHAR(16)
  exempt_applied   BOOL
  override_admin_user_id, override_reason   <- la main de l'admin

cancellation_exempt_reasons
  reason_code, label, requires_proof, max_per_user_per_30d
```

`max_per_user_per_30d` **est déjà** la règle « pas la première fois, mais si c'est fréquent ».

Le trou : `ProviderCancellationController:32` n'accepte qu'`'reason' => ['nullable','string']`. Un
champ libre ne se compte pas, ne se compare pas, ne déclenche aucun palier.

## 8.2 Le principe

> **On ne pose que des questions dont la réponse est vérifiable, ou dont la réponse engage.**

## 8.3 Côté client

```
  o Le prestataire est en retard
      VERIFIE : planned_start_at vs statut reel.
      Vrai -> aucun frais, et le retard entre au compteur du prestataire
      Faux -> l'option n'est pas proposee

  o Je n'ai plus besoin de ce service      -> frais selon le palier horaire
  o Je me suis trompe dans ma demande      -> frais selon le palier

  o Le prestataire m'a demande d'annuler
      -> aucun frais pour vous, et signal d'entente (section 4.6)

  o Autre                                   -> texte libre, revue humaine
```

La quatrième option est un **piège à entente** qui ne coûte rien à poser : un client à qui l'on
propose un arrangement en liquide n'a aucune raison de mentir ici, cocher lui évite les frais.

## 8.4 Côté prestataire

```
  o Je ne peux pas m'y rendre (panne, maladie, accident)
      justificatif demande au-dela de N fois / 30 j (requires_proof)

  o Adresse introuvable ou inaccessible
      VERIFIE : la trace GPS montre-t-elle un deplacement vers le point ?

  o Le client ne repond pas
      VERIFIE : ping envoye ? appel ? SMS ? -> renvoie vers le NO-SHOW existant

  o Le travail ne correspond pas du tout a l'annonce
      NE PROPOSE PAS L'ANNULATION -> renvoie vers NOUVEAU DEVIS ou RENFORT

  o Le client m'a demande d'annuler          -> signal d'entente, symetrique
```

Le quatrième point est le plus important : **le questionnaire devient un aiguillage.** Un prestataire
qui veut partir parce que le chantier est trop gros ne doit pas annuler — on le lui montre au moment
où il s'apprête à faire le mauvais geste.

## 8.5 Où le bouton apparaît

| Rôle | Peut annuler | Jusqu'à quand | Où |
|---|---|---|---|
| Client particulier | ✅ | avant clôture | suivi, « Gérer ma mission », détail de réservation |
| Client société *(selon capacité)* | ✅ sur son périmètre | avant clôture | mêmes surfaces + tableau de bord société |
| Prestataire | ✅ | **avant démarrage** | offre, détail, page terrain |
| Prestataire après démarrage | ⚠️ « abandon » | — | geste distinct, plus lourd, trace renforcée |
| Chef d'équipe / dispatch société | ✅ sur ses missions | avant clôture | centre de dispatch |
| Admin | ✅ toujours | toujours | console, `override_reason` obligatoire |

Séparer « annuler » d'« abandonner » n'est pas cosmétique : ce sont deux faits différents pour le
client — l'un le laisse libre de recommander, l'autre le laisse avec un chantier ouvert.

## 8.6 Ce qui ne change pas

Le calcul des frais, les paliers, et la décision du 2026-08-12 sur l'empreinte — capture partielle
des frais, puis libération du solde par Stripe. Le questionnaire **alimente** ce calcul par un
`reason_code` ; il ne le remplace pas.

Le « arrêter l'intervention » du § 3.5 emprunte **ce même tuyau**, avec son propre code de motif.
Une seule voie d'annulation, quel qu'en soit le déclencheur.

## 8.7 Le questionnaire est administrable — module admin

L'admin ajoute, modifie, supprime, active et désactive les questions.

### Deux tables, parce que ce sont deux notions

```sql
CREATE TABLE cancellation_questions (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code          VARCHAR(64) NOT NULL UNIQUE,   -- stable, jamais reutilise
  audience      VARCHAR(16) NOT NULL,          -- client | provider | both
  engine        VARCHAR(16) NULL,              -- NULL = tous les moteurs
  moment        VARCHAR(24) NULL,              -- NULL = tous les moments
  label         VARCHAR(191) NOT NULL,
  help_text     TEXT NULL,
  sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  metadata      JSON NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
);

CREATE TABLE cancellation_question_options (
  id                    BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  cancellation_question_id BIGINT UNSIGNED NOT NULL,
  code                  VARCHAR(64) NOT NULL,   -- alimente booking_cancellations_v2.reason_code
  label                 VARCHAR(191) NOT NULL,
  sort_order            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active             TINYINT(1) NOT NULL DEFAULT 1,
  verification          VARCHAR(32) NOT NULL DEFAULT 'none',
  outcome               VARCHAR(32) NOT NULL DEFAULT 'cancel',
  exempt_reason_id      BIGINT UNSIGNED NULL,   -- -> cancellation_exempt_reasons
  collusion_signal      TINYINT(1) NOT NULL DEFAULT 0,
  requires_text         TINYINT(1) NOT NULL DEFAULT 0,
  requires_proof        TINYINT(1) NOT NULL DEFAULT 0,
  metadata              JSON NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
);
```

`verification` : `none` · `provider_late` · `gps_movement` · `client_unreachable`
`outcome` : `cancel` · `redirect_requote` · `redirect_reinforcement` · `redirect_noshow` · `review`

### Versionnement — pourquoi la suppression est douce

Une annulation passée a été décidée avec les questions d'alors. `cancellation_policies` est déjà
versionnée pour cette raison exacte. Donc :

- **jamais de suppression dure** : `deleted_at` + `is_active`. L'admin « supprime », la ligne se
  retire des écrans et reste lisible pour les dossiers passés ;
- `code` est **stable et jamais réutilisé** — c'est lui qui vit dans `reason_code` ;
- à chaque annulation, un **instantané** du questionnaire montré est écrit dans
  `booking_cancellations_v2.metadata` : libellés tels qu'affichés, réponse, verdict de vérification.
  Un libellé modifié demain n'altère pas ce qui a été montré hier.

### L'écriture passe par un service, jamais par une colonne

Règle de ce dépôt pour la console d'administration. Toute création, modification, désactivation ou
suppression passe par :

```
app/Services/Cancellation/CancellationQuestionnaireService.php
  ajouterQuestion() modifierQuestion() basculerActivation() retirerQuestion()
  ajouterOption()   modifierOption()   basculerOption()     retirerOption()
  pour(User $acteur, Mission $mission): array   <- ce que la surface affiche
```

Le service journalise (`ActivityLogger`), refuse un `code` déjà pris, refuse de désactiver la
dernière option active d'une question active, et refuse une option `outcome = redirect_requote` sur
un moteur qui n'a pas le nouveau devis.

### Enregistrement du module

```php
// app/Providers/AdminConsoleServiceProvider.php
$registry->register('cancellation-questions', CancellationQuestionResource::class);

// config/modules.php  (contexte admin)
['key' => 'admin:admin.cancellation-questions.center',
 'label' => "Questionnaire d'annulation", 'icon' => '❓',
 'route' => 'admin.cancellation-questions.center',
 'context' => 'admin', 'category' => 'finance',
 'primary' => false, 'gate' => 'manage-finance'],
```

Catégorie et garde alignées sur son voisin `admin.cancellations-v2.center` : qui administre les
politiques d'annulation administre leur questionnaire.

---

# 9. Ce qui ne doit pas casser

Liste de non-régression, à vérifier par un test avant chaque lot qui y touche.

| Comportement | Pourquoi il est fragile |
|---|---|
| Une course ne génère aucun code et ne reçoit aucune checklist | `setArrived()` et `ensureChecklist()` branchent sur `estUneCourse()` |
| Clôturer deux fois ne rejoue pas la capture | garde d'idempotence en tête de `completeMission()` |
| `MissionProfitService` s'exécute **après** `actual_end_at` | sinon durée 0 et marge à 100 % |
| Le règlement horaire reste hors session, après capture | `HourlySettlementService` |
| Le supplément reste plafonné à 500 € | c'est la frontière avec le nouveau devis |
| `payout_status = auto_transferred` empêche le double versement | charge à destination |
| `fee_captured` ne doit jamais devenir `captured` | sinon le prestataire est crédité d'une prestation non faite |
| `syncFromRendezVous` ne rétrograde pas une mission démarrée | `statutASynchroniser()` |
| Une offre acceptée n'est pas rétrogradée en `assigned` | `syncLeadAssignment()` |
| `/provider/missions/active` reste servie par `lead_provider_user_id` | sinon l'indépendant perd sa mission |
| Les propriétés Livewire qui gardent un droit portent `#[Locked]` | le navigateur peut retourner une publique |
| Aucun nom d'index au-delà de 64 caractères | casse MySQL, invisible sous SQLite |
| Aucune couleur ni espacement en dur | mode sombre |

# 10. Tests — et leurs témoins

Chaque test de refus s'accompagne d'un **témoin positif** prouvant que le chemin fonctionne quand il
doit fonctionner. Sans témoin, un test « ceci est refusé » passe au vert en mesurant une panne.

| Refus à prouver | Témoin positif obligatoire |
|---|---|
| Nouveau devis refusé sur `HORAIRE` et `VEHICULE` | accepté sur `DOMICILE` |
| Révision refusée après une tâche cochée | acceptée avant toute tâche cochée |
| Révision refusée après 30 min | acceptée à 29 min |
| Révision rouverte 6 min par un ajout client | refusée sans ajout, la fenêtre étant close |
| To-do list refusée après verrouillage | acceptée avant |
| Clôture refusée avec une tâche client ouverte | acceptée avec la même tâche cochée |
| **Clôture acceptée quand la to-do list est vide** | et refusée quand elle contient une tâche ouverte |
| Suspension du nouveau devis dès le 1er verdict | proposition acceptée **avant** tout verdict |
| Suspension définitive au 3ᵉ verdict | proposition acceptée après expiration d'une suspension de 14 j |
| Aucune sanction au 1er refus | sanction au 3ᵉ, avec 2 contreparties distinctes |
| Annulation refusée au prestataire après démarrage | acceptée avant |
| Écriture de question refusée hors service | acceptée par le service |

Points de mesure supplémentaires :

- **Argent** : un test qui vérifie que `capture #1 + capture #2 = revised_total_cents`, et qu'un
  échec du second intent laisse `#1` intacte et la révision en `payment_failed`.
- **Promotion** : un test avec code promo prouvant que la remise est **réappliquée** au total révisé.
- **MySQL** : `migrate --pretend` sur toutes les migrations avant fusion — les gardes idempotents
  rendent faux sous SQLite et les blocs sont sautés.

---

# 11. Découpage en lots

Ordre contraint par les dépendances : le moteur d'abord, parce que tout s'y branche ; l'argent
avant les écrans, parce qu'un écran sur une mécanique fausse se refait deux fois.

| Lot | Contenu | Dépend de |
|---|---|---|
| **L0** | `MissionEngine` + résolveur + portes fermées + tests des trois moteurs | — |
| **L1** | To-do list : colonnes, service, fenêtre, verrouillage, API client + prestataire | L0 |
| **L2** | Gabarit en suggestions ; `ensureChecklist` cesse d'imposer ; non-régression clôture | L1 |
| **L3** | `mission_quote_revisions` + service + tarification (promo, remises) + Stripe complément | L0 |
| **L4** | Refus, choix du client, 0 €, branchement sur le tuyau d'annulation | L3 |
| **L5** | `mission_dispute_signals`, `mission_feature_suspensions`, 2 règles de risque, malus dispatch | L4 |
| **L6** | Questionnaire d'annulation : tables, service, module admin, bouton par rôle | L0, L3 |
| **L7** | Client mobile : « Ma mission », feuille, « Gérer ma mission » complet | L1, L3, L6 |
| **L8** | Client web : carte + panneau, parité complète | L7 |
| **L9** | Prestataire mobile : trois pages terrain, SOS et renfort atteignables | L1, L3 |
| **L10** | Prestataire web : blocs manquants, parité, route sur la carte d'accueil | L9 |
| **L11** | Les sept ajouts (appel masqué, renfort terrain, navigation, hors-ligne, partage, consigne, retard) | L9, L10 |
| **L12** | Passe graphique complète des deux côtés, mode sombre, accessibilité, mouvement réduit | tous |

Chaque lot se termine par : sa suite de tests ciblée verte, `git status` propre, et le verdict du
chef d'équipe. La suite complète et PHPStan sans argument de chemin tournent à la fin, et à chaque
fois qu'un changement transverse le justifie.

# 12. Questions volontairement laissées ouvertes

Elles ne bloquent aucun lot et se tranchent au moment de le faire, sur mesure et non sur hypothèse.

1. **Quel système de discussion brancher** — `chat_threads` ou `conversations`. Constaté au lot L7
   d'après ce que les applications utilisent réellement.
2. **Le seuil du minuteur de retard** (N minutes) — à caler sur les données réelles de ponctualité
   une fois le trafic présent. Valeur de départ : 15 minutes, en configuration.
3. **Le montant de la quote-part** due par un client sanctionné — décision produit, en configuration
   dès le lot L5.
