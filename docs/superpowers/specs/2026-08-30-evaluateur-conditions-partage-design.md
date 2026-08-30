# Évaluateur de conditions partagé — conception du lot 1

Date : 2026-08-30. État : **conception validée, non implémentée**.

## Pourquoi ce lot existe

`/admin/automation` est une coquille. La route existe, aucun composant n'est branché, et la page
sert le gabarit de repli « il reste à y brancher la logique métier ». Trois registres la promettent
pourtant : `config/modules.php` (une tuile gardée par `manage-automation`), `config/admin_console.php`
(un module de la console mobile) et `config/parity.php` (déclarée `responsive_verified`). Un
administrateur voit donc une tuile, clique, et tombe sur du vide.

La décision produit est de construire un vrai moteur d'automatisation **générique** — déclencheur,
condition, action — couvrant la surveillance opérationnelle, le cycle de vie client et les règles
métier de la plateforme.

Ce moteur a besoin d'un langage de conditions. **Il en existe déjà un**, complet et sûr, dans
`app/Services/Marketing/SegmentEngine.php`. Le construire une seconde fois créerait deux
vocabulaires qui divergeraient dès le premier opérateur ajouté à l'un et pas à l'autre.

Le chantier se fait donc en deux lots :

| Lot | Contenu | État |
|---|---|---|
| **1** | Extraire l'évaluateur de `SegmentEngine`, le rendre indépendant de l'entité, faire passer le marketing dessus | **ce document** |
| **2** | Le moteur d'automatisation par-dessus l'évaluateur partagé | à concevoir ensuite |

Ce découpage sépare deux risques. Si le lot 1 dérape, il se retire seul. Si le lot 2 dérape, le
marketing n'a pas bougé.

## Ce que fait `SegmentEngine` aujourd'hui

269 lignes. Deux appelants applicatifs — `RecomputeSegmentJob` et `MarketingCenter` — plus deux fichiers de tests.

Il fait quatre choses collées ensemble, dont deux seulement sont propres aux utilisateurs.

| Responsabilité | Générique | Destination |
|---|---|---|
| Parcours de l'arbre `and` / `or` / `not` (`applyNode`) | oui | évaluateur partagé |
| Les 15 opérateurs traduits en Eloquent (`applyOperator`) | oui | évaluateur partagé |
| Résolution des champs (`applyLeaf`) : liste blanche, préfixe `users.`, cas `email_domain`, agrégats `bookings_*` | non | descripteur d'entité |
| Racine de requête (`buildQuery`) : `User::query()`, `Schema::hasTable('bookings')` | non | descripteur d'entité |

Le vocabulaire actuel, lu dans `config/marketing.php` :

- **15 opérateurs** : `eq`, `neq`, `in`, `not_in`, `gt`, `gte`, `lt`, `lte`, `older_than_days`,
  `newer_than_days`, `is_null`, `is_not_null`, `contains`, `starts_with`, `ends_with`.
- **7 champs** : `role`, `locale`, `email_domain`, `created_at`, `bookings_count`,
  `last_booking_at`, `total_spent_cents`.

`country_code` et `last_login_at` ont été retirés à la tâche 8 (2026-08-30) : ces colonnes
n'existent pas sur `users`, un segment qui les employait plantait en « Unknown column ».

## Architecture

Trois objets, chacun avec une seule raison d'exister.

### `RuleTreeEvaluator`

Ne connaît que des nœuds et des opérateurs. Jamais une entité.

```
apply(Builder $requête, array $nœud, EntityDescriptor $entité): void
```

Il descend `and` / `or` / `not` à l'identique de `applyNode`, et demande au descripteur la liaison
de chaque feuille.

### `EntityDescriptor`

Ne connaît que son entité. Jamais l'arbre.

```
baseQuery(): Builder                      // User::query(), Booking::query(), …
fields(): array<string, FieldBinding>     // les champs exposés, et rien d'autre
operators(): list<string>                 // les opérateurs permis pour cette entité
```

**Les clés de `fields()` SONT la liste blanche.** Une seule source, au lieu d'un tableau de config
lu au fond d'une méthode.

### `FieldBinding`

Trois formes, jamais mélangées. Noms et signatures ci-dessous sont ceux du code livré — pas de
`transformeValeur` : `email_domain` reste une colonne simple, cette forme n'a jamais été implémentée.

| Forme | Sert à | Usage marketing |
|---|---|---|
| `colonne('users.locale')` | le cas courant | 4 champs sur 7, dont `email_domain` |
| `jointe(fn (Builder $racine): ?string => …)` | une sous-requête ; la fermeture reçoit la requête RACINE (jamais le nœud courant) et rend le nom de la colonne jointe, ou `null` si elle ne peut pas se poser | `bookings_count`, `last_booking_at`, `total_spent_cents` |
| `indisponible()` | ce champ n'est pas servable ici | rend `1=0`, comme `Schema::hasTable('bookings')` faux |

### Décision : le vocabulaire reste en code

Champs et opérateurs se déclarent en PHP. **Ajouter un champ demande un déploiement.** C'est un
arbitrage explicite du 2026-08-30, pris contre l'option « tout en base » : le pouvoir gagné ne
valait pas la liste blanche perdue.

Ce qui devient administrable au lot 2, sans déploiement : les règles, les seuils, les quotas, les
canaux, les messages, l'activation, l'ordre et la priorité.

**Ce qui ne sera jamais administrable** : un champ de SQL ou d'expression PHP libre. Une interface
d'administration qui exécute du code arbitraire donne la base et le serveur à quiconque prend un
compte admin.

## Flux

Rien ne change pour l'utilisateur du marketing.

```
MarketingCenter / RecomputeSegmentJob
     └─ SegmentEngine::compute()                              signature inchangée
          ├─ RuleTreeEvaluator::apply(…, UserSegmentDescriptor)          neuf
          └─ pluck ids → memberships → member_count → ActivityLogger   inchangé
```

`compute()` fait bien plus qu'évaluer : il matérialise les adhésions, met à jour les compteurs et
journalise. Tout cela reste chez lui. Il devient un adaptateur, pas une coquille.

`UserSegmentDescriptor` construit ses `fields()` à partir de `config('marketing.segment_fields')` et
restreint les opérateurs à `config('marketing.segment_operators')`. La configuration garde donc la
main, et la liste blanche a quand même une source unique.

## Invariants

1. **Un champ non déclaré ne peut pas atteindre SQL.** Le nom de colonne ne vient jamais de la
   règle : il vient de la valeur de `fields()`, écrite en PHP. La clé que l'admin choisit et la
   colonne que SQL reçoit sont deux choses distinctes. Les valeurs passent par les liaisons
   Eloquent. La surface d'injection est nulle par construction.

2. **Tout arbre accepté est re-rendable dans un constructeur visuel.** Le vocabulaire est fini :
   `and` / `or` / `not` plus `{field, op, value}` avec `field` ∈ clés de `fields()` et `op` ∈
   opérateurs connus. Aucune règle valide n'échappe à l'affichage. C'est ce qui empêchera la porte
   « JSON brut » du lot 2 de devenir un cimetière de règles illisibles.

3. **Un descripteur qui ment se voit au test.** Un champ déclaré vers une colonne absente ne casse
   aujourd'hui qu'à l'exécution. Un test générique — pour chaque descripteur, chaque champ déclaré
   doit produire une requête qui s'exécute — l'attrape une fois pour toutes.

## Les trois changements assumés

Ce lot **n'est pas à comportement constant**. Trois corrections sont demandées, toutes dans la
traduction des opérateurs.

### 1. Clause `ESCAPE` explicite

Mesures du 2026-08-30, exécutées sur les deux moteurs.

| Requête | MySQL (l'application) | SQLite (la suite de tests) |
|---|---|---|
| `'a%b' LIKE 'a\%b'` sans clause | **1** — l'échappement marche | **0** — l'échappement ne marche pas |
| `… ESCAPE '\'` | **erreur 1064** — l'antislash échappe le guillemet fermant | 1 |
| `… ESCAPE '\\'` | 1 | **erreur** — « ESCAPE expression must be a single character » |
| `… ESCAPE '!'` | **1** | **1** |

Deux conclusions.

1. L'échappement de `%` est **déjà cassé sur SQLite**. Un test écrit dessus mesurerait le contraire
   de la production.
2. **L'antislash ne peut pas servir de caractère d'échappement portable** : la clause qui marche sur
   l'un est une erreur de syntaxe sur l'autre. Un `whereRaw` figé ne peut pas servir les deux.

La correction émet donc `LIKE ? ESCAPE '!'` — identique sur les deux moteurs, mesuré.

### 2. Trois caractères échappés

`!`, puis `%`, puis `_` — **dans cet ordre**. Le caractère d'échappement d'abord, sinon on
ré-échappe ce qu'on vient d'écrire.

Conséquence heureuse : l'antislash redevient un caractère ordinaire dans les valeurs. Aujourd'hui,
MySQL le traite comme un échappement et le mange silencieusement.

### 3. Bornes sur l'arbre

Profondeur ≤ **10**, nœuds ≤ **200**. Dépassement → exception de domaine `RuleTreeTooComplex`, et
non un `1=0`. `SegmentEngine::compute()` la rattrape, rend 0 et l'écrit dans `ActivityLogger`.
Une règle refusée devient visible au lieu de rendre un segment vide qui a l'air normal.

### Impact sur les données

0 segment en base locale au moment de la conception. La production n'est pas visible d'ici.
L'impact s'y limite aux segments dont une valeur `contains` / `starts_with` / `ends_with` contient
`%`, `_` ou `\`. Ces segments capturent aujourd'hui **trop large** ; après correction ils
captureront moins. Des adhésions peuvent bouger : à signaler dans les notes de version.

## Stratégie de test

19 tests existent déjà (`SegmentEngineTest`, `SegmentEngineCoverageBatch7Test`). Ils couvrent
`and`, `or`, `not`, les opérateurs, `email_domain`, champ inconnu, opérateur inconnu, `preview` et
le recalcul. C'est un vrai filet pour l'extraction.

**Le trou** : les trois champs dérivés (`bookings_count`, `last_booking_at`, `total_spent_cents`) et
la branche « table `bookings` absente » ne sont couverts par aucun d'eux. C'est précisément la
partie qui migre dans le descripteur.

Quatre familles, dans cet ordre.

1. **Caractérisation, avant de toucher au moteur.** Les 3 champs dérivés et la branche sans table.
   Ils doivent passer sur le code actuel non modifié : c'est le seul moment où ils prouvent quelque
   chose.
2. **Constance, pendant.** Les 19 tests existants tournent **sans être modifiés**. Si l'un demande à
   être retouché, l'extraction a changé un comportement : signal d'arrêt, pas d'ajustement du test.
3. **Les trois changements, après — chacun avec son témoin positif.** Un test d'échappement se
   satisfait trop facilement d'un résultat vide.
   - `contains('a_b')` trouve `a_b` **et ne trouve pas** `axb`. Le second est le témoin.
   - Le test de la clause `ESCAPE` passe du rouge au vert par la correction, sur SQLite. C'est ce
     qui prouve qu'il mesure la bonne chose.
   - Un arbre à la limite exacte passe (témoin) ; au-delà, `RuleTreeTooComplex` et `compute()` rend 0.
4. **Test générique de descripteur.** Chaque champ déclaré produit une requête qui s'exécute
   réellement. Gratuit maintenant, il couvrira les descripteurs du lot 2.

**Ce qui n'est pas testé : MySQL.** La suite tourne sur SQLite. Les deux moteurs ont été mesurés à
la main pour la correction de l'échappement, et le résultat figure ci-dessus. Prétendre que la suite
couvre MySQL serait un vert obtenu pour une mauvaise raison.

## Dette laissée en place, volontairement

| Sujet | Pourquoi maintenant | Quand |
|---|---|---|
| Un champ ou opérateur inconnu rend `1=0` **en silence** | Aucun écran, à ce lot, où le dire | Lot 2 : l'éditeur refuse à l'écriture |
| Les 5 alertes métier écrites en dur (`BusinessAlerts`) | Hors périmètre de l'extraction | Lot 2 : elles deviennent des règles |

## Deux défauts découverts pendant la planification

### Les champs dérivés n'ont jamais fonctionné

`buildQuery` enveloppe **toujours** l'arbre dans `where(function ($q) { … })`. `applyLeaf` y appelle
`applyBookingDerivedField`, qui pose un `leftJoinSub` **sur le constructeur imbriqué**. Or une
jointure ajoutée à un constructeur de groupement n'est jamais compilée : seules ses clauses `where`
le sont.

Mesure du 2026-08-30, MySQL :

```
SQL produit : select * from `users` where (`b_count_agg`.`agg` > ?)
                                    ↑ aucun JOIN
Erreur      : SQLSTATE[42S22] 1054 Unknown column 'b_count_agg.agg' in 'where clause'
```

**Les trois champs `bookings_count`, `last_booking_at` et `total_spent_cents` plantent donc à
l'exécution, systématiquement.** Un tiers du vocabulaire des segments est mort. Aucun des 19 tests
ne les couvre : c'est ce qui l'a caché.

Conséquence sur la conception : `RuleTreeEvaluator::apply()` garde une référence à la requête
**racine** et la passe aux liaisons jointes, pendant que le parcours de l'arbre travaille sur le
constructeur imbriqué. La forme `FieldBinding::jointe()` reçoit donc la racine, jamais le nœud
courant. Le défaut devient structurellement impossible.

### Deux emplois du même champ dérivé entrent en collision

Chaque champ dérivé pose sa jointure sous un **alias fixe** (`b_count_agg`, `b_lastat_agg`,
`b_spent_agg`). Une fois la jointure remontée à la racine, une règle comme `bookings_count > 2` ET
`bookings_count < 10` en poserait deux du même nom.

Mesuré : `SQLSTATE[42000] 1066 Not unique table/alias: 'b_count_agg'`.

La jointure se pose donc **au plus une fois par alias** ; le second emploi du champ réutilise la
colonne déjà jointe.

### Pourquoi ces deux correctifs entrent dans ce lot

Déplacer du code qui plante sans le voir serait pire que de le laisser. Et la caractérisation
demandée en stratégie de test ne peut pas « figer le comportement actuel » d'un chemin qui lève une
exception : elle documente le plantage, et l'extraction le répare.

## Risques

| Risque | Parade |
|---|---|
| L'extraction change un comportement du marketing sans qu'on le voie | Les 19 tests tournent non modifiés ; toute retouche demandée est un signal d'arrêt |
| Les champs dérivés partent dans le descripteur sans filet | Caractérisation écrite **avant** l'extraction |
| Le correctif d'échappement modifie des segments en production | Impact borné aux valeurs contenant `%`, `_` ou `\` ; à signaler dans les notes de version |
| `QUEUE_CONNECTION=sync` : pas de file asynchrone | Sans objet au lot 1 ; contrainte majeure du lot 2, où l'ordonnanceur devra être le véhicule d'exécution |
