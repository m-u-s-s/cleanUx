# Moteur d'automatisation — phase 3 : les écrans

> **Pour les agents :** SOUS-COMPÉTENCE REQUISE — utiliser `superpowers:subagent-driven-development`
> pour exécuter ce plan tâche par tâche. Les étapes sont cochables (`- [ ]`).

**But :** que `/admin/automation` cesse d'être une coquille, et qu'un administrateur puisse écrire,
observer, armer et relire une règle sans déploiement.

**Architecture :** un écran de liste, un constructeur, un journal. Le vocabulaire vient des trois
registres livrés aux phases 1 et 2 ; les conditions passent par l'évaluateur du lot 1, seul moteur.
Aucun champ n'exécute de SQL ni de PHP.

**Pile :** Laravel 12, PHP 8.5, Livewire 3, PHPUnit sur SQLite, application sur MySQL.

**Spec :** `docs/superpowers/specs/2026-08-30-moteur-automatisation-design.md`

---

## Ce que les phases 1 et 2 ont livré, et sur quoi les écrans s'appuient

Mesuré le 2026-08-31, dans le code réel.

| Ce dont l'écran a besoin | Où c'est |
|---|---|
| Les règles | `App\Models\AutomationRule` — `nom`, `description`, `entite`, `declencheur`, `cadence`, `conditions` (array), `actions` (array), `politique_reprise`, `etat`, `quota_par_passage`, `plafond_journalier`, `plafonds_consecutifs`, `echecs_consecutifs`, `dernier_passage_le`, `cree_par` |
| Les états | `ETAT_BROUILLON`, `ETAT_OBSERVATION`, `ETAT_ARMEE`, `ETAT_SUSPENDUE`, `ETAT_DESACTIVEE` |
| Les transitions | `App\Services\Automation\EtatDeRegle` — `observer()`, `armer()` (lève `ArmementRefuse`), `suspendre($motif)`, `desactiver()`, `aDejaObserve()` |
| Les entités | `EntiteRegistre::cles()`, `::descripteur($cle)` → `EntityDescriptor` (`fields(): array<string, FieldBinding>`, `operators()`, `baseQuery()`) |
| Les actions | `ActionRegistre::toutes()`, `::trouver($cle)` → `Action` (`cle`, `libelle`, `entitesSupportees(): list<string>`, `champs(): array<string,string>`, `toucheAuDomaine(): bool`) |
| Les déclencheurs | `DeclencheurRegistre::toutes()`, `::trouver($cle)` → `Declencheur` (`cle`, `evenement`, `entite`, `sApplique`, `identifiant`, `libelle`) |
| Les opérateurs et les bornes | `RuleTreeEvaluator::OPERATEURS_CONNUS`, `::PROFONDEUR_MAX` (10), `::NOEUDS_MAX` (200) ; `apply()` lève `RuleTreeTooComplex` |
| Les passages | `AutomationRun` — `mode`, `demarre_le`, `termine_le`, `entites_vues`, `entites_eligibles`, `entites_finies`, `actions_posees`, `statut` (`ok`/`plafond_atteint`/`echec`), `message` |
| Les lignes posées | `AutomationAction` — `entite_type`, `entite_id`, `action_cle`, `parametres`, `mode`, `resultat` (`simulee`/`executee`/`proposee`/`validee`/`refusee`/`echouee`/`expiree`), `message`, `pose_le` |
| L'interrupteur | `FeatureFlagService::isEnabled('automation')` ; la clé vit dans `config/features.php`, à `false` |

**La route existe déjà et n'est pas à écrire.** `routes/missing-route-fixes-advanced.php:197-202` sert
`/admin/automation` par un helper qui rend la **première classe existante** parmi
`App\Livewire\Admin\AutomationCenter` et `AdminAutomationCenter`, sinon un gabarit de repli. Les
deux `use` sont déjà en tête du fichier. **Créer `AutomationCenter` suffit à brancher l'écran.**

---

## Global Constraints

Elles lient **chaque** tâche.

- **Aucun champ n'exécute de SQL ni de PHP libre.** La porte JSON écrit le même arbre que le
  constructeur visuel, et cet arbre est validé avant d'être stocké.
- **Un seul moteur d'évaluation** : `RuleTreeEvaluator`. Ne réimplémente ni la traduction des
  conditions, ni la liste des opérateurs.
- **Le vocabulaire vient des registres, jamais d'une liste écrite dans une vue.** Une action, une
  entité ou un déclencheur ajouté en code doit apparaître dans l'écran sans qu'on y touche.
- **Système `brio-*`, jetons de couleur, mode sombre.** Aucune couleur ni espacement en dur : un
  garde-fou de test balaie les vues, et il lit aussi la prose des commentaires.
- **Une vue Livewire n'a qu'UNE racine.** Une modale posée à côté ne s'affiche jamais, sans erreur.
- **Toute modale rendue dans une section de verre passe par `@teleport('body')`**, et sa condition
  d'ouverture se pose **avant** le téléport.
- **Un `h2` sort en Allura.** `x-page-shell` porte le `h1` de la page ; un titre d'interface s'écrit
  en `h3`.
- **Toute propriété Livewire publique qui garde un accès porte `#[Locked]`** — le navigateur peut la
  retourner par `$set`.
- **`#[Computed]` ne met en cache que l'accès par propriété** (`$this->truc`), pas `$this->truc()`.
- **Un test de refus exige un témoin positif.**
- **Tout composant Livewire du dossier `App\Livewire\Admin` emploie le trait
  `EnforcesAdminAccess`.** Ce n'est PAS un doublon du garde de route : celui-ci protège l'affichage
  de la page, tandis que les actions Livewire passent par `/livewire/update`, qui ne porte aucun
  intermédiaire de cette route. Sans le trait, n'importe quel compte authentifié peut invoquer une
  méthode du composant. Un test de sécurité du dépôt l'exige pour les 105 composants admin :
  `tests/Feature/Security/AdminComponentGuardTest.php`. **Lance-le avant chaque commit** d'un
  composant neuf.
- **Commentaires : deux lignes maximum.**
- **Les trois familles de garde-fous se lancent avant tout commit d'une vue ou d'un composant
  neuf** : `tests/Feature/Security/`, `tests/Feature/DesignSystem/`, `tests/Feature/A11y/`. Elles
  prennent quelques secondes et balaient **tout** le dépôt, pas seulement ce que la tâche a écrit.
  Deux tâches de cette phase ont laissé `main` rouge en ne lançant que leurs suites ciblées — l'une
  sur la garde d'accès des 105 composants admin, l'autre sur des `<label>` qui ne reliaient rien.
  Une suite ciblée verte ne dit rien de ces trois-là.
- Portails avant chaque commit : `./vendor/bin/pint` sur les fichiers touchés, puis
  `./vendor/bin/phpstan analyse --no-progress` **sans argument de chemin**.

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `app/Services/Automation/Catalogue.php` | Le vocabulaire, mis en forme pour un écran : entités, champs, opérateurs, actions, déclencheurs |
| `app/Services/Automation/ValidateurDArbre.php` | Valide un arbre de conditions contre un descripteur, sans l'exécuter pour de bon |
| `app/Livewire/Admin/AutomationCenter.php` | La liste, l'interrupteur, les transitions d'état |
| `app/Livewire/Admin/Automation/ConstructeurDeRegle.php` | Créer et modifier une règle |
| `app/Livewire/Admin/Automation/JournalDeRegle.php` | Les passages et les lignes posées |
| `resources/views/livewire/admin/automation/*.blade.php` | Les trois vues |

---

### Tâche 1 : le catalogue du vocabulaire

**Fichiers :**
- Créer : `app/Services/Automation/Catalogue.php`
- Créer : `tests/Feature/Automation/CatalogueTest.php`

**Interfaces :**
- Consomme : `EntiteRegistre`, `ActionRegistre`, `DeclencheurRegistre`, `RuleTreeEvaluator::OPERATEURS_CONNUS`.
- Produit : `entites(): array<string, array{cle: string, champs: list<string>, operateurs: list<string>}>` ;
  `actions(?string $entite = null): array<string, array{cle: string, libelle: string, champs: array<string,string>, touche_au_domaine: bool}>` ;
  `declencheurs(?string $entite = null): array<string, array{cle: string, libelle: string, entite: string}>`.

Le catalogue **ne décide de rien** : il met en forme ce que les registres portent déjà, pour qu'une
vue n'ait jamais à les interroger elle-même ni à écrire une liste en dur.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Services\Automation\Catalogue;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\DeclencheurRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function catalogue(): Catalogue
    {
        return app(Catalogue::class);
    }

    /** ANCRE — un registre vide rendrait tous les tests ci-dessous verts a vide. */
    public function test_temoin_le_catalogue_porte_quelque_chose(): void
    {
        $this->assertNotEmpty($this->catalogue()->entites());
        $this->assertNotEmpty($this->catalogue()->actions());
        $this->assertNotEmpty($this->catalogue()->declencheurs());
    }

    public function test_chaque_entite_du_registre_est_au_catalogue(): void
    {
        $attendues = app(EntiteRegistre::class)->cles();

        $this->assertSame($attendues, array_keys($this->catalogue()->entites()));
    }

    public function test_les_champs_d_une_entite_viennent_de_son_descripteur(): void
    {
        $descripteur = app(EntiteRegistre::class)->descripteur('booking');

        $this->assertSame(
            array_keys($descripteur->fields()),
            $this->catalogue()->entites()['booking']['champs']
        );
    }

    public function test_les_operateurs_viennent_de_l_evaluateur(): void
    {
        $this->assertSame(
            RuleTreeEvaluator::OPERATEURS_CONNUS,
            $this->catalogue()->entites()['booking']['operateurs']
        );
    }

    /** LE FILTRE PAR ENTITE EST LA GARDE : proposer a l'admin une action que la regle ne
     *  peut pas executer produirait une ligne `echouee` a chaque passage. */
    public function test_les_actions_se_filtrent_par_entite(): void
    {
        $toutes = array_keys($this->catalogue()->actions());
        $pourAlerte = array_keys($this->catalogue()->actions('alerte'));

        $ecarts = [];

        foreach ($pourAlerte as $cle) {
            if (! in_array('alerte', app(ActionRegistre::class)->trouver($cle)->entitesSupportees(), true)) {
                $ecarts[] = $cle;
            }
        }

        $this->assertSame([], $ecarts, 'Actions proposees a tort : '.implode(', ', $ecarts));
        $this->assertNotEmpty($pourAlerte, 'Aucune action pour « alerte » : le filtre mesure une panne.');
        $this->assertLessThanOrEqual(count($toutes), count($pourAlerte));
    }

    /** TEMOIN — sans filtre, on obtient bien TOUTES les actions. */
    public function test_temoin_sans_entite_le_catalogue_rend_toutes_les_actions(): void
    {
        $this->assertSame(
            array_keys(app(ActionRegistre::class)->toutes()),
            array_keys($this->catalogue()->actions())
        );
    }

    public function test_les_declencheurs_se_filtrent_par_entite(): void
    {
        foreach ($this->catalogue()->declencheurs('alerte') as $cle => $declencheur) {
            $this->assertSame('alerte', $declencheur['entite'], $cle);
        }

        $this->assertNotEmpty($this->catalogue()->declencheurs('alerte'));
    }

    public function test_chaque_action_expose_son_libelle_et_ses_champs(): void
    {
        $ecarts = [];

        foreach ($this->catalogue()->actions() as $cle => $action) {
            if (trim($action['libelle']) === '') {
                $ecarts[] = "{$cle} : libelle vide";
            }
            if (! array_key_exists('champs', $action)) {
                $ecarts[] = "{$cle} : champs absents";
            }
            if (! array_key_exists('touche_au_domaine', $action)) {
                $ecarts[] = "{$cle} : touche_au_domaine absent";
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

`php artisan test tests/Feature/Automation/CatalogueTest.php`
Attendu : échec, la classe n'existe pas.

- [ ] **Étape 3 : écrire le catalogue**

Un service simple, injecté par le conteneur, qui parcourt les trois registres. **Aucune liste
écrite en dur.** `actions(?string $entite)` filtre sur `entitesSupportees()` ; `declencheurs(?string
$entite)` filtre sur `entite()`.

- [ ] **Étape 4 : relancer, vérifier le vert, portails, commit**

---

### Tâche 2 : la validation d'un arbre de conditions

**Fichiers :**
- Créer : `app/Services/Automation/ValidateurDArbre.php`
- Créer : `tests/Feature/Automation/ValidateurDArbreTest.php`

**Interfaces :**
- Consomme : `EntityDescriptor`, `RuleTreeEvaluator`.
- Produit : `valider(array $arbre, EntityDescriptor $entite): list<string>` — la liste des erreurs,
  vide quand l'arbre est bon.

**Pourquoi ce service existe.** La porte JSON accepte un arbre écrit à la main : il faut pouvoir
dire à l'administrateur **ce qui ne va pas**, champ par champ, avant d'enregistrer. `apply()` ne le
dit pas — il lève à la première borne dépassée, ou pire, laisse passer un nom de champ inconnu que
SQLite prendra pour une chaîne littérale.

**Ce que le validateur vérifie :**
1. la forme : un nœud est soit `{field, op, value}`, soit `{and: [...]}`, `{or: [...]}` ou `{not: {...}}` — MESURE dans `RuleTreeEvaluator::apply()`, c'est lui qui fait autorite ;
2. `field` existe dans `$entite->fields()` ;
3. `op` figure dans `$entite->operators()` ;
4. les bornes de `RuleTreeEvaluator` — profondeur et nombre de nœuds ;
5. **et, en dernier, que l'arbre s'applique vraiment** : `apply()` sur une requête jetable, dans un
   `try`, pour attraper ce que la forme seule ne dit pas. C'est le seul moyen d'être sûr que la
   validation et l'exécution parlent du même langage — deux vocabulaires qui divergent est le
   défaut dominant de ce dépôt.

- [ ] **Étape 1 : écrire le test** — au minimum ces cas, chacun avec son message attendu :
  un arbre vide (accepté ou refusé — **tranche et écris pourquoi** : rappelle-toi que le moteur
  refuse une règle sans condition, sauf restreinte par identifiants) ; un `field` inconnu ; un `op`
  inconnu ; un nœud mal formé ; un arbre trop profond ; un arbre de plus de 200 nœuds ; un `and`
  vide ; un arbre valide (**le témoin**, qui rend une liste d'erreurs vide).

- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire le validateur**
- [ ] **Étape 4 : relancer, vérifier le vert, portails, commit**

---

### Tâche 3 : l'écran de liste

**Fichiers :**
- Créer : `app/Livewire/Admin/AutomationCenter.php`
- Créer : `resources/views/livewire/admin/automation-center.blade.php`
- Créer : `tests/Feature/Admin/AutomationCenterTest.php`

**Interfaces :**
- Consomme : `AutomationRule`, `AutomationAction`, `FeatureFlagService`.
- Produit : la route `admin.automation` cesse de servir le gabarit de repli.

**Ce que la liste montre**, par règle : le nom, l'entité, le déclencheur (son **libellé**, pas sa
clé), l'état, la date du dernier passage, et ce qu'elle a posé sur sept jours. Plus, **visible en
tête et non caché dans un réglage**, l'état de l'interrupteur global.

**Mesure d'abord :** ouvre deux écrans admin existants (`resources/views/livewire/admin/` en compte
plusieurs dizaines) et suis leur forme — `<x-page-shell>` pour l'en-tête, `<x-table-shell>` pour le
tableau, `brio-chip` pour les états, `<x-empty-state>` quand il n'y a aucune règle. **Ne réinvente
aucun composant.**

- [ ] **Étape 1 : écrire le test**

Le patron du dépôt, relevé dans `tests/Feature/Admin/AgendaHebdomadaireActionsTest.php` — suis-le :
un administrateur se fabrique par `User::factory()->admin()->create([...])` en posant
`access_scope`, `managed_service_zone_id` et `is_active`, et le composant se pilote par
`Livewire::test(...)` sous `actingAs`. Voici l'ancre des deux premiers cas, à étendre :

```php
    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
        ]);
    }

    /** LA COQUILLE TOMBE. Tant qu'aucune classe n'existe, la route sert un gabarit de repli
     *  et l'administrateur clique sur une tuile pour arriver dans le vide. */
    public function test_la_route_sert_le_composant_et_non_le_gabarit_de_repli(): void
    {
        $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation'))
            ->assertOk()
            ->assertSeeLivewire(AutomationCenter::class);
    }

    /** TEMOIN DE LA PORTE — mesure d'abord OU la porte est posee (l'intermediaire de module du
     *  groupe de routes, avec `'gate' => 'manage-automation'` dans `config/modules.php`), et
     *  ecris le test CONTRE elle. N'ajoute pas une seconde garde dans le composant. */
    public function test_un_non_administrateur_n_atteint_pas_l_ecran(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('admin.automation'))
            ->assertForbidden();
    }
```

**Vérifie `assertForbidden()` contre le comportement réel** — selon la porte, le refus peut être une
redirection plutôt qu'un 403. Mesure-le, puis écris l'assertion juste ; ne force pas le code à
suivre une assertion devinée.

Les autres cas, au minimum :
  1. la route `admin.automation` rend bien le composant (et non le gabarit de repli) ;
  2. **la porte** : un utilisateur non administrateur ne peut pas l'atteindre. **Mesure d'abord
     comment la porte est posée** — le groupe de routes porte un intermédiaire de module, et
     `config/modules.php` déclare `'gate' => 'manage-automation'`. Écris le test contre la porte
     réelle, n'en ajoute pas une seconde dans le composant ;
  3. **témoin** : un administrateur, lui, l'atteint ;
  4. la liste montre le **libellé** du déclencheur, pas sa clé ;
  5. le compte sur sept jours ne compte que les lignes des sept derniers jours — avec son témoin,
     une ligne plus ancienne ne compte pas ;
  6. l'état de l'interrupteur est affiché, dans les deux sens (`config()->set('features.automation', …)`).

- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire le composant et la vue**
- [ ] **Étape 4 : relancer, vérifier le vert**
- [ ] **Étape 5 : le garde-fou du système de design**

Lance le balayage des couleurs en dur du dépôt (cherche le test qui le porte dans
`tests/Feature/DesignSystem/`) et corrige ce qu'il signale **dans ta vue**, sans toucher au reste.

- [ ] **Étape 6 : portails et commit**

---

### Tâche 4 : les transitions d'état depuis la liste

**Fichiers :**
- Modifier : `app/Livewire/Admin/AutomationCenter.php`, sa vue
- Créer : `tests/Feature/Admin/AutomationTransitionsTest.php`

**Interfaces :**
- Consomme : `EtatDeRegle` (`observer`, `armer`, `suspendre`, `desactiver`), `ArmementRefuse`.

**Le point qui compte.** `EtatDeRegle::armer()` **refuse** une règle au journal d'observation vide,
en levant `ArmementRefuse`. L'écran doit attraper cette exception et **montrer son message**, pas
planter ni l'avaler. C'est la garde fondatrice du moteur : elle doit se voir.

**`#[Locked]` sur l'identifiant de la règle visée** — sans quoi le navigateur pourrait le retourner
par `$set` et agir sur une autre règle.

- [ ] **Étape 1 : écrire le test** — au minimum :
  1. armer une règle sans observation **échoue** et affiche le motif ;
  2. **témoin** : la même règle, après un passage d'observation, s'arme ;
  3. observer, suspendre, désactiver mènent bien à l'état attendu ;
  4. chaque transition écrit une ligne de journal (`ActivityLogger`) ;
  5. un non-administrateur ne peut déclencher aucune transition ;
  6. **la garde `#[Locked]`** : la propriété qui porte l'identifiant ne peut pas être retournée
     depuis le navigateur.

- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire les actions**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 5 : le constructeur — la règle sans ses conditions

**Fichiers :**
- Créer : `app/Livewire/Admin/Automation/ConstructeurDeRegle.php`, sa vue
- Créer : `tests/Feature/Admin/ConstructeurDeRegleTest.php`

**Ce que le constructeur pose**, dans cet ordre : le nom et la description, l'**entité**, le
**déclencheur** (et la cadence si le déclencheur vaut `cadence`), les **actions** avec leurs
paramètres, la **politique de reprise**, le **quota par passage** et le **plafond journalier**.

**Les listes viennent du catalogue de la tâche 1**, filtrées par l'entité choisie. Changer l'entité
doit remettre à zéro les actions et le déclencheur qui ne lui conviennent plus — sinon on
enregistre une règle incohérente que le moteur refusera en silence.

**Une règle naît en `brouillon`.** Le constructeur ne l'arme jamais.

- [ ] **Étape 1 : écrire le test** — au minimum : la création pose bien tous les champs ; l'entité
  filtre les actions et les déclencheurs proposés ; changer d'entité vide les choix devenus
  invalides ; les bornes numériques sont validées ; une règle créée est en `brouillon` ; les
  paramètres d'action déclarés par `champs()` apparaissent au formulaire ; **le témoin** : une
  règle valide s'enregistre.

- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire le composant et la vue**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 6 : l'arbre de conditions — le constructeur visuel

**Fichiers :**
- Modifier : `ConstructeurDeRegle.php` et sa vue
- Créer : `tests/Feature/Admin/ArbreDeConditionsTest.php`

Ajouter, retirer, imbriquer des nœuds ; choisir `and`, `or` ou `not` — la forme reelle, mesuree dans l'evaluateur, PAS `all`/`any` ; choisir un champ parmi
ceux de l'entité, un opérateur parmi ceux qu'elle accepte, et une valeur.

**Les bornes se voient dans l'écran** : profondeur 10, 200 nœuds. Un administrateur qui les dépasse
doit le lire avant d'enregistrer, pas après.

- [ ] **Étape 1 : écrire le test** — l'arbre construit dans l'écran produit **exactement** le JSON
  que l'évaluateur attend ; un champ hors de l'entité ne peut pas être choisi ; les bornes sont
  refusées avec leur message ; **le témoin** : un arbre valide s'enregistre et sélectionne bien les
  bonnes entités quand on le passe à `RuleTreeEvaluator`.

- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 7 : la porte JSON

**Fichiers :**
- Modifier : `ConstructeurDeRegle.php` et sa vue
- Créer : `tests/Feature/Admin/PorteJsonTest.php`

Un champ de texte où l'expert colle un arbre. Il passe par `ValidateurDArbre` (tâche 2) et, s'il
est bon, **remplit le constructeur visuel** — les deux écrivent le même JSON, il n'y a pas deux
formats.

**Ce champ n'exécute rien.** Ni SQL, ni PHP, ni expression. Il lit du JSON et le valide.

- [ ] **Étape 1 : écrire le test** — un JSON malformé est refusé avec son message ; un arbre invalide
  liste ses erreurs ; un arbre valide remplit le constructeur ; **et le test qui compte** : l'arbre
  produit par le constructeur visuel, relu par la porte JSON, redonne le même arbre — aller-retour
  sans perte. Plus le témoin : un arbre valide s'enregistre et s'exécute.

- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 8 : le journal d'une règle

**Fichiers :**
- Créer : `app/Livewire/Admin/Automation/JournalDeRegle.php`, sa vue
- Créer : `tests/Feature/Admin/JournalDeRegleTest.php`

**C'est l'écran qu'on lit avant d'armer.** Sans lui, l'observation obligatoire ne sert à rien.

Les **passages** (mode, début, fin, entités éligibles, vues, actions posées, statut, message) et les
**lignes posées** (entité, action, mode, résultat, message, date), filtrables par résultat.

- [ ] **Étape 1 : écrire le test** — le journal montre les passages et les lignes ; le filtre par
  résultat filtre vraiment, **avec son témoin** (sans filtre, tout est là) ; une règle sans passage
  affiche un état vide plutôt qu'un tableau vide ; le message d'un passage en échec est **visible** — c'est ce qui explique une suspension ; un non-administrateur n'y accède pas.

- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 9 : la joignabilité, les garde-fous et la vérification d'ensemble

**Fichiers :**
- Créer : `tests/Feature/Admin/AutomationEcransJoignablesTest.php`
- Modifier : `tests/Feature/Automation/RegistresTest.php`

**Le défaut dominant de ce dépôt est l'écran complet que personne ne peut atteindre.** Cette tâche
existe pour qu'il ne se reproduise pas ici.

- [ ] **Étape 1 : le test de joignabilité**

Pour **chaque** écran de la phase : une route le sert, un administrateur l'atteint, et il rend un
contenu reconnaissable. Et le chemin complet, sans raccourci : depuis la liste, ouvrir le
constructeur ; enregistrer une règle ; ouvrir son journal ; la mettre en observation ; lancer
`automation:executer` ; revenir au journal et **y lire la ligne simulée**.

- [ ] **Étape 2 : étendre le garde-fou des registres**

Toute action exposée par le catalogue déclare des `champs()` dont les types sont connus du
formulaire ; tout déclencheur exposé vise une entité enregistrée. Le témoin : le catalogue n'est pas
vide.

- [ ] **Étape 3 : le balayage du système de design**

Lance les garde-fous de `tests/Feature/DesignSystem/` et le balayage d'espacement
(`npm run qa:espacement` s'il existe encore — **vérifie-le, ne le suppose pas**). Corrige ce qu'ils
signalent dans les vues de cette phase.

- [ ] **Étape 4 : la suite complète**

```bash
git status --short          # l'arbre doit etre propre AVANT de lancer
composer test:parallele
```

**Ne modifier aucun fichier pendant que la suite tourne.**

- [ ] **Étape 5 : les deux portails, puis commit**

---

## Ce que la phase 3 ne fait pas

| Sujet | Pourquoi |
|---|---|
| La file des propositions et l'écran de validation | Phase 4, avec les actions à deux vitesses |
| Les réglages d'actions (autonome / à valider) | Phase 4 — la ligne se règle là où elle est tenue |
| Les cinq règles reproduisant `BusinessAlerts` | Phase 5 |
| Les actions qui écrivent dans le domaine | Phase 4, et seulement après les contrepoids |
| Filtrer les opérateurs par TYPE de champ (`contains` sur une date passe en silence) | Demanderait que `FieldBinding` déclare un type — un contrat du lot 1, partagé avec le segment marketing. Mesuré et nommé, pas oublié |
| Valider `declencheur` ↔ `entite` à la création | **Si**, le constructeur le fait — c'est le point reporté de la phase 2 |
