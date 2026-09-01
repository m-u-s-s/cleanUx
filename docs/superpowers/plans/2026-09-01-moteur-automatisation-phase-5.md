# Moteur d'automatisation — phase 5 : les cinq règles

> **Pour les agents :** SOUS-COMPÉTENCE REQUISE — utiliser `superpowers:subagent-driven-development`
> pour exécuter ce plan tâche par tâche. Les étapes sont cochables (`- [ ]`).

**But :** que les cinq alertes du chemin de l'argent, aujourd'hui figées dans le code, deviennent
cinq règles qu'un administrateur peut lire, modifier et armer sans déploiement.

**Architecture :** un seeder idempotent pose cinq règles **en brouillon**. Elles se branchent aux
déclencheurs livrés à la phase 2, filtrent sur l'entité `alerte`, et notifient les administrateurs.
Personne ne les arme à leur place : l'administrateur les observe, lit leur journal, puis décide.

**Pile :** Laravel 12, PHP 8.5, PHPUnit sur SQLite, application sur MySQL.

**Spec :** `docs/superpowers/specs/2026-08-30-moteur-automatisation-design.md`

---

## Ce que les quatre phases précédentes ont livré

Mesuré le 2026-09-01.

| Ce dont la phase a besoin | Où c'est |
|---|---|
| Les cinq déclencheurs | `alerte.payment_capture_failed`, `alerte.payout_failed`, `alerte.webhook_backlog`, `alerte.stuck_mission_holding_funds`, `alerte.reconciliation_divergence` — enregistrés par `AutomationServiceProvider` |
| L'entité `alerte` | `AlerteDescriptor` — champs `cle`, `niveau`, `entite_type`, `entite_id`, `levee_le` |
| L'action | `notifier.admins` — `toucheAuDomaine() === false`, champ `message` de type `texte` |
| Le chemin d'un événement | `BusinessAlerts` lève `BusinessAlertRaised` → `EnregistrerLAlerteMetier` persiste dans `business_alertes` et dépose dans `automation_reevaluations` → `automation:executer` draine |
| Les états | `brouillon` → `observation` → `armee`, par `EtatDeRegle` ; armer **refuse** sans journal d'observation |
| Les écrans | liste, constructeur, journal, réglages d'actions, file des propositions — tous joignables |
| L'interrupteur | `FeatureFlagService::isEnabled('automation')`, à `false` dans `config/features.php` |

**La voie Sentry n'est pas touchée.** `BusinessAlertSentryListener` reste branché. La spec l'interdit
explicitement : on ne retire pas un chemin d'alerte sur l'argent dans le lot qui construit son
remplaçant. Les règles **s'ajoutent**.

---

## Global Constraints

- **Les cinq règles naissent en `brouillon`.** Aucun seeder, aucune migration, aucun test ne les
  arme. C'est l'administrateur qui les observe puis les arme, par le chemin réel. Un seeder qui
  armerait contournerait la garde fondatrice du moteur.
- **Le seeder est idempotent** : le relancer ne crée pas de doublon et n'écrase pas ce qu'un
  administrateur a modifié depuis. `updateOrCreate` sur une clé stable est le patron du dépôt —
  mais **mesure ce qu'il écrase** avant de choisir les colonnes à mettre à jour.
- **L'interrupteur global reste fermé.** Rien de cette phase ne l'ouvre.
- **Aucune règle n'emploie une action qui touche au domaine.** Les cinq notifient ; elles ne
  changent rien. Le contrepoids de la phase 4 reste entier.
- **Le vocabulaire vient des registres** : les clés de déclencheur, d'entité et d'action doivent
  exister au registre, et un test doit le prouver.
- **Un test de refus exige un témoin positif.**
- **Commentaires : deux lignes maximum.**
- Portails : `./vendor/bin/pint` sur les fichiers touchés, puis
  `./vendor/bin/phpstan analyse --no-progress` **sans argument de chemin**.
- **Un message de commit contenant des accents graves passe par `git commit -F -` et un heredoc.**

---

### Tâche 1 : ce que les cinq alertes font aujourd'hui

**Fichiers :**
- Créer : `tests/Feature/Automation/CeQueLesAlertesFontAujourdHuiTest.php`

**Cette tâche n'écrit aucun code de production.** Elle établit la référence : **ce que le
comportement figé fait aujourd'hui**, pour que les règles de la tâche 2 puissent être comparées à
quelque chose plutôt qu'à une intention.

**Mesure d'abord, dans le code réel** : `app/Support/Alerts/BusinessAlerts.php` et
`app/Listeners/Alerts/BusinessAlertSentryListener.php`. Pour chacune des cinq alertes, établis :
son niveau, les clés de son contexte, **qui est prévenu aujourd'hui et par quel canal**.

- [ ] **Étape 1 : écrire le test** — pour chaque alerte : la lever produit bien un
  `BusinessAlertRaised` portant le niveau et les clés de contexte attendus, **et** la voie Sentry
  reçoit l'événement. Le témoin : un événement d'une autre clé ne déclenche pas l'alerte mesurée.
- [ ] **Étape 2 : lancer, vérifier le vert** — ce test doit passer **sans** rien changer : il
  décrit l'existant. S'il échoue, ta lecture du code est fausse — corrige ta lecture, pas le code.
- [ ] **Étape 3 : portails, commit**

**Ce test est le témoin de toute la phase** : le jour où une règle prétendra « reproduire »
l'alerte, c'est à lui qu'on la comparera.

---

### Tâche 2 : le seeder des cinq règles

**Fichiers :**
- Créer : `database/seeders/ReglesDAlerteMetierSeeder.php`
- Modifier : le seeder de profil qui convient (**mesure-le** : `DatabaseSeeder` appelle des profils
  `demo`, `reference` et `production` ; les cinq règles sont des données de **référence**, pas de
  démonstration)
- Créer : `tests/Feature/Automation/ReglesDAlerteMetierSeederTest.php`

Pour chacune des cinq alertes, une règle :

| Ce que la règle porte | Valeur |
|---|---|
| `nom` | En français, lisible : « Capture de paiement en échec », etc. |
| `description` | Ce qu'elle surveille et pourquoi, en une phrase |
| `entite` | `alerte` |
| `declencheur` | `alerte.<cle>` |
| `conditions` | L'arbre qui filtre — au minimum `{field: 'cle', op: 'eq', value: '<cle>'}` |
| `actions` | `[{cle: 'notifier.admins', parametres: {message: '…'}}]` |
| `politique_reprise` | **Tranche et dis pourquoi.** Une alerte est un fait distinct à chaque fois |
| `etat` | `brouillon`, sans exception |
| `quota_par_passage`, `plafond_journalier` | **Tranche et dis pourquoi** — ce sont des alertes d'argent, leur volume n'est pas celui d'une règle de masse |

**Pourquoi une condition sur `cle` alors que le déclencheur filtre déjà ?** Parce que le déclencheur
dit *quand* la règle se réveille, et la condition *sur quoi* elle agit. Le drain restreint aux
entités déposées, mais une règle sans condition serait refusée par la garde de la phase 1 — et une
condition explicite se lit dans l'écran. **Vérifie ce raisonnement dans le code avant de l'appliquer.**

- [ ] **Étape 1 : écrire le test** — au minimum : les cinq règles existent après le seeder ;
  toutes en `brouillon` ; chaque `declencheur` existe au registre ; chaque `entite` existe au
  registre ; chaque clé d'action existe au registre **et supporte l'entité** ; **relancer le seeder
  ne crée pas de doublon** ; **et le témoin qui compte** : une modification faite par un
  administrateur — le nom, le quota — **survit** à un nouveau passage du seeder.
- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire le seeder**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 3 : les conditions tiennent vraiment

**Fichiers :**
- Créer : `tests/Feature/Automation/LesCinqReglesFiltrentTest.php`

Une règle dont les conditions ne sélectionnent rien est pire qu'absente : elle rassure sans agir.

- [ ] **Étape 1 : écrire le test** — pour **chacune** des cinq règles : passer ses conditions à
  `RuleTreeEvaluator` sur l'entité `alerte` sélectionne bien une alerte de sa clé, **et pas** une
  alerte d'une autre clé. Emploie le validateur `ValidateurDArbre` pour prouver au passage que
  chaque arbre est valide.
- [ ] **Étape 2 : lancer, vérifier l'échec** (écris d'abord une condition fausse dans le seeder
  pour voir le test tomber, puis remets la bonne — je veux cette sortie dans le rapport)
- [ ] **Étape 3 : portails, commit**

---

### Tâche 4 : le bout en bout, alerte réelle → proposition

**Fichiers :**
- Créer : `tests/Feature/Automation/BoutEnBoutDesCinqReglesTest.php`

Le chemin complet, sans raccourci, **pour au moins deux des cinq** — dont une qui porte une entité
liée (`stuck_mission_holding_funds`) et une qui n'en porte aucune (`webhook_backlog`) :

le seeder pose la règle → un administrateur la met en observation → `BusinessAlerts::<alerte>()` est
levée pour de vrai → `php artisan automation:executer` (interrupteur ouvert par
`config()->set('features.automation', true)`) → **une ligne `simulee` paraît au journal** → la règle
s'arme par le chemin réel → une seconde alerte est levée → une ligne `executee` paraît **et les
administrateurs sont prévenus**.

**Le témoin** : la voie Sentry a reçu les deux alertes, comme avant. Les règles s'ajoutent, elles ne
remplacent pas.

- [ ] **Étape 1 : écrire le test**
- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : portails, commit**

---

### Tâche 5 : l'écran montre les cinq, et l'interrupteur reste fermé

**Fichiers :**
- Créer : `tests/Feature/Admin/LesCinqReglesSontVisiblesTest.php`

`/admin/automation` cesse d'être une coquille — c'est la phrase de la spec, et cette tâche la vérifie.

- [ ] **Étape 1 : écrire le test** — après le seeder, un administrateur ouvre la liste et **voit les
  cinq règles** avec leur nom et le **libellé** de leur déclencheur ; il ouvre le journal de l'une
  d'elles ; il ouvre le constructeur de l'une d'elles et **ses conditions s'y affichent**. Et le
  témoin qui protège l'essentiel : **l'interrupteur global est toujours fermé** — rien de cette
  phase ne l'a ouvert.
- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : les trois familles de garde-fous, portails, commit**

---

### Tâche 6 : la vérification d'ensemble

**Fichiers :**
- Modifier : `tests/Feature/Automation/RegistresTest.php`
- Modifier : `docs/exploitation.md` si nécessaire

- [ ] **Étape 1 : étendre le garde-fou**

Toute règle posée par un seeder doit : exister en `brouillon`, viser une entité enregistrée, un
déclencheur enregistré, et n'employer que des actions enregistrées **qui ne touchent pas au
domaine**. Le témoin : le jeu de règles n'est pas vide.

- [ ] **Étape 2 : ce que l'exploitant doit savoir**

Les cinq règles arrivent en brouillon et **ne font rien** tant que personne ne les arme, ni tant que
le drapeau `automation` est fermé. **Mesure d'abord si `docs/exploitation.md` a un endroit qui le
dit déjà** — n'ajoute pas une dixième page, le dépôt en tient neuf.

- [ ] **Étape 3 : la suite complète**

```bash
git status --short          # l'arbre doit etre propre AVANT de lancer
composer test:parallele
```

**Ne modifier aucun fichier pendant que la suite tourne.**

- [ ] **Étape 4 : les deux portails, puis commit**

---

## Ce que la phase 5 ne fait pas

| Sujet | Pourquoi |
|---|---|
| Armer les cinq règles | C'est la décision de l'administrateur, après observation. Un seeder qui armerait contournerait la garde fondatrice |
| Ouvrir le drapeau `automation` | C'est une décision d'exploitation, pas de développement |
| Retirer la voie Sentry | Interdit par la spec : on ne retire pas un chemin d'alerte sur l'argent dans le lot qui construit son remplaçant |
| La règle multi-étapes | Hors du lot ; le point d'extension est la colonne `etape` |
| Filtrer les opérateurs par type de champ | Nommé à la phase 3 ; demanderait que `FieldBinding` déclare un type |
