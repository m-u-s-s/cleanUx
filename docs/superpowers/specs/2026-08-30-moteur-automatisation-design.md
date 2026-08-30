# Moteur d'automatisation admin — conception du lot 2

Date : 2026-08-30. État : **conception validée, non implémentée**.
S'appuie sur : `docs/superpowers/specs/2026-08-30-evaluateur-conditions-partage-design.md` (lot 1, livré).

## Pourquoi ce lot existe

`/admin/automation` est une coquille : la route sert un gabarit de repli, et trois registres la
promettent pourtant — `config/modules.php` (une tuile gardée par `manage-automation`),
`config/admin_console.php` (un module de la console mobile), `config/parity.php` (déclarée
`responsive_verified`). Un administrateur voit une tuile, clique, et tombe sur du vide.

L'automatisation existe déjà dans le produit, mais **figée dans le code** : `BusinessAlerts` porte
cinq alertes écrites en dur, et aucune ne s'ajoute sans une mise en production.

Le lot 1 a livré l'évaluateur de conditions partagé. Ce lot construit le moteur par-dessus.

## Décisions actées

Prises en conception les 2026-08-30. Elles ne se rediscutent pas dans le plan.

| Sujet | Décision |
|---|---|
| Périmètre | Moteur **générique** : surveillance opérationnelle, cycle de vie client, règles métier |
| Expression des conditions | L'arbre JSON du lot 1. **Constructeur visuel ET porte JSON validée**, un seul moteur d'évaluation |
| Vocabulaire | **En code.** Ajouter un champ, un déclencheur ou une action demande un déploiement |
| Administrable sans déploiement | Règles, seuils, quotas, canaux, messages, activation, ordre, priorité, **et la ligne autonome / à valider par action** |
| Jamais administrable | Un champ de SQL ou d'expression PHP libre |
| Déclenchement | **L'événement dépose, l'ordonnanceur exécute.** Un seul moteur d'évaluation, jamais dans la requête d'un client |
| Idempotence | Registre « déjà agi », avec **politique de reprise par règle** |
| Armement | L'admin arme quand il veut, **mais le moteur refuse d'armer une règle au journal d'observation vide** |
| Quota | Il **bride** ; la suspension vient de trois plafonds consécutifs |
| Forme d'une règle | **Un pas** : déclencheur, conditions, actions. La séquence multi-étapes est un lot ultérieur, dont le point d'extension est nommé ici |

## Modèle de données

Cinq tables. Migration à créer après `2026_09_28_090000_socle_de_la_location_entre_membres.php`.

### `automation_rules`

| Colonne | Rôle |
|---|---|
| `nom`, `description` | Ce que la règle fait, en clair |
| `entite` | Clé du descripteur visé : `booking`, `mission`, `user` |
| `declencheur` | `cadence`, ou la clé d'un déclencheur d'événement |
| `cadence` | `chaque_minute` / `quart_heure` / `heure` / `jour`, quand `declencheur = cadence` |
| `conditions` | L'arbre JSON du lot 1 |
| `actions` | Liste de `{cle, parametres}` |
| `politique_reprise` | `une_fois` (défaut) / `une_fois_par_jour` / `chaque_passage` — **toujours par entité** : « une fois » signifie une fois par entité, jamais une fois pour la règle entière |
| `etat` | `brouillon` / `observation` / `armee` / `suspendue` / `desactivee` |
| `quota_par_passage` | Défaut 50 |
| `plafond_journalier` | Butée dure |
| `cree_par`, horodatages | |

### `automation_runs`

Un passage : `rule_id`, `demarre_le`, `termine_le`, `mode` (`observation` / `armee`), `entites_vues`,
`actions_posees`, `statut` (`ok` / `plafond_atteint` / `echec`), `message`.

### `automation_actions`

**Journal d'audit, registre « déjà agi » et file des propositions — une seule table.**

Ces trois rôles sont le même objet vu sous trois angles. Une action **proposée et non encore
décidée** doit compter comme « déjà agi », sinon le passage suivant la propose une seconde fois.
Les séparer créerait deux sources de vérité sur ce que le moteur a fait à une entité.

| Colonne | Rôle |
|---|---|
| `rule_id`, `run_id` | D'où vient la ligne |
| `entite_type`, `entite_id` | Sur quoi |
| `action_cle`, `parametres` | Quoi |
| `mode` | `observation` / `armee` |
| `resultat` | `simulee` / `executee` / `proposee` / `validee` / `refusee` / `echouee` / `expiree` |
| `decide_par`, `decide_le`, `motif` | La décision humaine, pour les propositions |
| `etape` | Entier, **zéro partout aujourd'hui** |
| `message` | Le détail d'un échec |
| `pose_le` | Index avec `(rule_id, entite_type, entite_id)` |

**`etape` est le point d'extension de la règle multi-étapes.** Elle ne coûte rien aujourd'hui et
évite une réécriture le jour où une règle devient une séquence : l'avancement d'une entité s'y lira.

### `automation_reevaluations`

La file que les écouteurs d'événements déposent : `evenement`, `entite_type`, `entite_id`,
`depose_le`. Index **unique** sur `(evenement, entite_type, entite_id)` — deux occurrences du même
événement sur la même entité ne créent qu'une ligne.

### `automation_action_settings`

Le drapeau réglable : `action_cle` (unique), `autonome` (booléen, **défaut faux**), `modifie_par`,
`modifie_le`. **L'absence de ligne vaut « à valider ».**

## Machine à états d'une règle

```
brouillon ──activer l'observation──▶ observation ──armer──▶ armée
                                          │                   │
                              REFUS si le journal      3 plafonds consécutifs
                                   est vide             ou 3 échecs consécutifs
                                                              ▼
                                                         suspendue ──réarmer──▶ armée

   n'importe quel état ──désactiver──▶ désactivée
```

## Le vocabulaire, en code

```php
interface AutomationTrigger {
    public function cle(): string;                        // 'paiement.capture_echouee'
    public function evenement(): string;                  // la classe d'événement
    public function entite(): string;                     // 'booking'
    public function identifiant(object $evenement): ?int; // extrait l'entité
    public function libelle(): string;
}

interface AutomationAction {
    public function cle(): string;                        // 'notifier.admins'
    public function libelle(): string;
    public function entitesSupportees(): array;
    public function champs(): array;                      // paramètres typés — le formulaire s'en déduit
    public function toucheAuDomaine(): bool;              // écrit-elle dans le métier ?
    public function executer(Model $entite, array $parametres): ActionResult;
}
```

Les **entités** réutilisent `EntityDescriptor` du lot 1. Ce lot livre `BookingDescriptor` et
`MissionDescriptor`, et hérite du garde-fou qui vérifie que chaque champ déclaré s'exécute vraiment.

L'entité `user` **n'est pas** `UserSegmentDescriptor` : celui-ci expose le vocabulaire des segments
marketing (7 champs, dont trois agrégats de réservations), pensé pour découper une base de clients.
Une règle d'automatisation sur les utilisateurs voudra d'autres champs. Le registre les tient
séparés ; les deux descripteurs coexistent sous des clés distinctes, et le lot 1 reste intact.

**`toucheAuDomaine()` ne décide pas de l'autonomie.** Il décide si la bascule vers « autonome »
demande une confirmation renforcée. La déclaration dit ce que l'action **est** ; le réglage dit ce
que l'administrateur **autorise**.

## Exécution

Une seule commande d'ordonnanceur :

```php
$schedule->command('automation:executer')->everyMinute()->withoutOverlapping();
```

```
automation:executer
 ├─ draine automation_reevaluations
 │     └─ pour chaque événement : les règles qui y sont branchées,
 │        évaluées RESTREINTES aux entités déposées
 └─ les règles à cadence dont le tour est venu
       └─ évaluées sur toute leur entité

        les deux ──▶ RuleRunner::executer(Regle $regle, ?array $identifiants)
```

L'évaluation tient en une requête par règle et par passage :

```php
$entite  = $registre->descripteur($regle->entite);
$requete = $entite->baseQuery();

RuleTreeEvaluator::apply($requete, $regle->conditions, $entite);          // le lot 1

$requete->when($identifiants, fn ($q) => $q->whereKey($identifiants));
$requete->whereNotIn('id', <déjà agi, selon la politique de reprise>);
$requete->limit($regle->quota_par_passage + 1);
```

**Le `+1` n'est pas cosmétique** : sans lui, une règle qui trouve exactement son quota est
indiscernable d'une règle qui en trouve mille, et le signal d'emballement disparaît.

**Un écouteur d'événement écrit une ligne et rend la main.** Pas d'évaluation, pas d'action, pas de
notification. `QUEUE_CONNECTION=sync` : il n'existe aucune file asynchrone dans ce dépôt, donc tout
le reste s'exécuterait dans la requête de l'utilisateur qui vient d'agir. Cette contrainte, mesurée,
a dicté la forme du moteur.

## Les gardes

### 1. L'observation

Une règle en observation écrit ses lignes avec `resultat = simulee` et **ne touche à rien**. Le
moteur refuse d'armer une règle dont le journal d'observation est vide.

### 2. Le quota bride, il ne suspend pas

| | Ce que c'est | Ce qu'on en fait |
|---|---|---|
| **Bridage** | La règle trouve 200 entités, son quota est de 50 — fonctionnement normal sur une grosse population | Agir sur 50, laisser le reste au passage suivant |
| **Emballement** | Le plafond est atteint **passage après passage** : la population visée ne diminue pas | **Suspendre** après trois plafonds consécutifs |

Plus un `plafond_journalier` par règle, en butée dure.

### 3. L'interrupteur global

`FeatureFlagService::isEnabled('automation')`, lu en tête de la commande. Il coupe tout, et
**aucun réglage d'action ne peut le contourner** : c'est la seule garde que le réglage ne touche pas.

### 4. Les deux vitesses, et leurs cinq contrepoids

La ligne autonome / à valider est réglable par action, dans l'écran d'administration. Ce choix est
assumé ; ces cinq contrepoids le rendent tenable.

1. **Toute action naît « à valider ».** L'absence de ligne dans `automation_action_settings` vaut
   « à valider ». On opte pour l'autonomie ; on n'y tombe jamais par défaut.
2. **Basculer une action vers autonome est journalisé** — qui, quand — dans `ActivityLogger`.
3. Une action dont `toucheAuDomaine()` est vrai demande une **confirmation renforcée**. Les routes
   d'administration portent déjà `enforce_2fa`.
4. Le journal distingue `executee` (le moteur) de `validee` (un humain). Jamais la même valeur.
5. **Une proposition non décidée expire.** Ce n'est pas de l'hygiène : une proposition en attente
   compte comme « déjà agi », donc tant que personne ne tranche, la règle ne repasse jamais sur
   cette entité. Sans expiration, une file oubliée gèle silencieusement une partie du domaine.

### 5. L'échec d'une action

Une action qui lève est enregistrée `echouee` avec son message, et **le passage continue** : une
ligne fautive ne doit pas emporter les autres. Trois passages entièrement en échec suspendent la
règle.

## Les écrans

Sous `/admin/automation`, qui cesse d'être une coquille.

| Écran | Ce qu'on y fait |
|---|---|
| **Liste** | Les règles, leur état, leur dernier passage, ce qu'elles ont posé sur 7 jours. L'interrupteur global y est **visible**, pas caché dans un réglage |
| **Constructeur** | Entité, déclencheur, l'arbre de conditions — constructeur visuel **et** porte JSON validée, écrivant le même JSON — actions et paramètres, politique de reprise, quota |
| **Journal d'une règle** | Passages et lignes posées, filtrables par résultat. **C'est l'écran qu'on lit avant d'armer** ; sans lui, l'observation obligatoire ne sert à rien |
| **File des propositions** | Transversale : ce qui attend une décision, valider ou refuser avec motif |
| **Réglages d'actions** | La ligne autonome / à valider, avec la confirmation renforcée |

**Contraintes de rendu**, toutes déjà payées dans ce dépôt :

- Système `brio-*`, mode sombre par les jetons, aucune couleur en dur.
- Un titre de section en `h2` sort en Allura — c'est voulu ; un titre d'interface s'écrit en `h3`.
- **Une racine unique par vue Livewire.** Une modale posée à côté ne s'affiche jamais, sans erreur.
- **Toute modale rendue dans une section de verre passe par `@teleport('body')`**, et sa condition
  d'ouverture se pose **avant** le téléport. `backdrop-filter` fait de la section le bloc conteneur
  de tout `fixed` descendant ; et `@teleport` rend un `<template x-teleport>` qu'Alpine clone à son
  initialisation — émis vide, il le reste.

## Les cinq alertes métier

`BusinessAlerts` lève `paymentCaptureFailed`, `payoutFailed`, `webhookBacklog`,
`stuckMissionHoldingFunds`, `reconciliationDivergence` depuis du code, à des moments précis. Ce ne
sont pas des conditions sur une table : ce sont des **événements**. Chacune devient un déclencheur,
et une règle livrée reproduit le comportement d'aujourd'hui — en gagnant au passage des conditions
et des canaux que le code figé n'offrait pas.

**La voie Sentry existante n'est pas retirée.** Les règles s'ajoutent. Retirer un chemin d'alerte
sur l'argent dans le lot même qui construit son remplaçant contredirait « ne rien casser ». Le
retrait, s'il a lieu, sera un lot à part.

## Tests

- **Chaque brique seule** : un déclencheur extrait-il son identifiant ; une action s'exécute-t-elle ;
  le runner bride-t-il ; l'idempotence tient-elle sous chaque politique de reprise.
- **Un témoin positif par refus.** « L'observation n'écrit rien dans le domaine » n'a de valeur
  qu'accompagné de « armée, elle y écrit ». Sans quoi le test passe au vert en mesurant une panne.
- **Un test de bout en bout** : événement → dépôt → drain → action posée.
- **Un garde-fou générique**, comme au lot 1 : toute action déclarée expose des champs cohérents,
  tout déclencheur sait extraire une entité de son événement, tout descripteur résout ses champs.
- La suite tourne sur **SQLite**, l'application sur MySQL. Toute mesure qui dépend du moteur se fait
  à la main sur les deux et se consigne ici.

## Les cinq phases

Une seule spec, un ordre défendable. Le risque croît de la phase 1 à la 4, et **chaque garde arrive
avant ce qu'elle retient**.

| | Phase | Livrable |
|---|---|---|
| 1 | **Le noyau** | Tables, registres, `RuleRunner`, cadence, observation, bridage, interrupteur. Deux actions inoffensives (`notifier.admins`, `journaliser`). Piloté en ligne de commande |
| 2 | **Les événements** | Écouteur générique, file de réévaluation, drain, les cinq déclencheurs de `BusinessAlerts` |
| 3 | **Les écrans** | Liste, constructeur, porte JSON, journal. L'administrateur devient autonome |
| 4 | **Les deux vitesses** | Réglages d'actions, file de propositions, écran de validation, expiration — et **seulement là** les actions qui écrivent dans le domaine |
| 5 | **Les cinq règles** | Elles reproduisent les alertes existantes. `/admin/automation` cesse d'être une coquille |

## Ce que ce lot ne fait pas

| Sujet | Pourquoi |
|---|---|
| La règle multi-étapes (séquence, attentes, sorties) | Généricité de **durée**, que personne n'a demandée. Le point d'extension est la colonne `etape` |
| Retirer la voie Sentry des cinq alertes | Retirer un chemin d'alerte sur l'argent dans le lot qui construit son remplaçant |
| Un champ de SQL ou d'expression libre | Une interface d'administration qui exécute du code arbitraire donne la base et le serveur |
| Une file asynchrone | `QUEUE_CONNECTION=sync`. Le moteur est conçu pour s'en passer, pas pour l'exiger |
| Les quatre reliquats parqués du lot 1 | Cellule de spec périmée, `??=` qui ne mémoïse pas `null`, garde-fou d'opérateurs incomplet, état de jointure qui ne suit pas un clone |

## Risques

| Risque | Parade |
|---|---|
| Une règle fautive agit en masse | Observation obligatoire, bridage, plafond journalier, suspension sur emballement, interrupteur global |
| Une action rendue autonome par mégarde | Défaut à « à valider », bascule journalisée, confirmation renforcée si elle écrit dans le domaine |
| Une file de propositions oubliée gèle le domaine | Expiration des propositions non décidées |
| Le moteur ralentit une requête utilisateur | L'écouteur ne fait qu'écrire une ligne ; tout le reste vit dans l'ordonnanceur |
| Deux vocabulaires de conditions divergent | Il n'y en a qu'un — celui du lot 1, partagé avec le marketing |
