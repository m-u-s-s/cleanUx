# Moteur d'automatisation — phase 2 : les événements

> **Pour les agents :** SOUS-COMPÉTENCE REQUISE — utiliser `superpowers:subagent-driven-development`
> pour exécuter ce plan tâche par tâche. Les étapes sont cochables (`- [ ]`).

**But :** qu'un événement du produit fasse repasser une règle sur la seule entité concernée, sans
jamais évaluer quoi que ce soit dans la requête de l'utilisateur qui vient d'agir.

**Architecture :** un écouteur écrit une ligne dans `automation_reevaluations` et rend la main.
`automation:executer` draine cette file au passage suivant et appelle `RuleRunner::executer()` avec
la liste d'identifiants. Les cinq alertes de `BusinessAlerts` deviennent cinq déclencheurs.

**Pile :** Laravel 12, PHP 8.5, PHPUnit sur SQLite, application sur MySQL.

**Spec :** `docs/superpowers/specs/2026-08-30-moteur-automatisation-design.md`

---

## Ce que la mesure a corrigé dans la spec

La spec suppose que chaque alerte est un événement distinct portant une entité. **Le code réel dit
autre chose**, mesuré le 2026-08-30 dans `app/Support/Alerts/BusinessAlerts.php` :

1. **Les cinq alertes lèvent UNE SEULE classe d'événement**, `BusinessAlertRaised`, discriminée par
   sa propriété `key` (`payment_capture_failed`, `payout_failed`, `webhook_backlog`,
   `stuck_mission_holding_funds`, `reconciliation_divergence`). Le contrat `AutomationTrigger` de la
   spec discrimine sur la classe seule : il ne sait pas les distinguer. **Il lui manque une méthode.**

2. **Deux des cinq ne portent aucune entité** : `webhookBacklog(int $count)` et
   `reconciliationDivergence(array $detail)`. Une troisième porte un `ProviderPayout`, pour lequel
   aucun descripteur n'est prévu. Le modèle « entité + conditions » ne les accueille pas.

3. **Rien ne persiste ces alertes** : le seul écouteur les envoie à Sentry
   (`app/Listeners/Alerts/BusinessAlertSentryListener.php`). Il n'en reste aucune trace dans le
   produit.

**Arbitrages, qui ne se rediscutent pas dans les tâches :**

- **L'alerte devient une entité du moteur.** Une table `automation_alertes` reçoit chaque alerte
  levée ; le descripteur `alerte` l'expose. Les cinq alertes deviennent alors uniformes, leurs
  conditions deviennent écrivables (`cle`, `niveau`, `entite_type`), et le produit gagne une trace
  de ses défaillances d'argent, qu'il n'avait pas.
- **Le contrat de déclencheur gagne `sApplique(object $evenement): bool`.** Sans elle, « cet
  événement ne me concerne pas » et « je n'ai pas trouvé d'entité » se diraient tous les deux par
  un `null` — deux notions, une valeur de retour, le défaut dominant de ce dépôt.
- **Deux écouteurs, une seule porte.** Un événement qui *désigne* une entité existante et un
  événement qui *EST* le fait ne se traitent pas pareil : deux écouteurs. Mais tous deux écrivent
  par le même service `FileDeReevaluation`, seule porte de la file.
- **La voie Sentry n'est pas touchée.** La spec l'interdit explicitement.

---

## Contraintes globales

Elles lient **chaque** tâche.

- **Un écouteur écrit et rend la main.** Aucune évaluation, aucune action, aucune notification dans
  un écouteur. `QUEUE_CONNECTION=sync` : tout ce qu'un écouteur fait se paie dans la requête de
  l'utilisateur.
- **L'interrupteur global reste `FeatureFlagService::isEnabled('automation')`**, lu en tête de la
  commande. Un drapeau absent de `config('features')` rend `false`.
- **Une règle n'agit jamais sans être passée par l'observation.** La garde vit dans
  `RuleRunner::executer()` depuis le correctif B de la phase 1 ; le drain passe par elle.
- **L'index unique de `automation_reevaluations` fait l'idempotence.** Deux occurrences du même
  événement sur la même entité ne créent qu'une ligne.
- **Un test de refus exige un témoin positif.** Sans contrôle prouvant que le chemin fonctionne
  quand il doit fonctionner, un test « ceci est refusé » passe au vert en mesurant une panne.
- **Commentaires : deux lignes maximum.** Le code dit QUOI, le commit dit POURQUOI.
- Portails avant chaque commit : `./vendor/bin/pint` sur les fichiers touchés, puis
  `./vendor/bin/phpstan analyse --no-progress` **sans argument de chemin**.
- Base de test SQLite, application MySQL : pas de SQL brut, pas de fonction de date propriétaire.

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `database/migrations/…_la_file_de_reevaluation_et_les_alertes.php` | Les deux tables de la phase |
| `app/Models/AutomationReevaluation.php` | Une entrée de file |
| `app/Models/AlerteMetier.php` | Une alerte levée, persistée |
| `app/Services/Automation/FileDeReevaluation.php` | **La seule porte** de la file : déposer, lire, purger |
| `app/Services/Automation/Contracts/Declencheur.php` | Le contrat |
| `app/Services/Automation/Registre/DeclencheurRegistre.php` | Clé → déclencheur |
| `app/Services/Automation/Declencheurs/AlerteMetierDeclencheur.php` | Les cinq, une classe paramétrée |
| `app/Services/Automation/Descripteurs/AlerteDescriptor.php` | L'entité `alerte` |
| `app/Services/Automation/Descripteurs/MissionDescriptor.php` | L'entité `mission` |
| `app/Listeners/Automation/EnregistrerLAlerteMetier.php` | Persiste l'alerte, puis dépose |
| `app/Listeners/Automation/DeposerLaReevaluation.php` | L'écouteur générique, piloté par le registre |
| `app/Console/Commands/ExecuterLAutomatisation.php` | Le drain, avant les règles à cadence |

---

### Tâche 1 : les deux tables et leurs modèles

**Fichiers :**
- Créer : `database/migrations/2026_09_30_090000_la_file_de_reevaluation_et_les_alertes.php`
- Créer : `app/Models/AutomationReevaluation.php`, `app/Models/AlerteMetier.php`
- Créer : `tests/Feature/Automation/FileEtAlertesSocleTest.php`

**Interfaces :**
- Produit : `AutomationReevaluation` (`evenement`, `entite_type`, `entite_id`, `depose_le`) ;
  `AlerteMetier` (`cle`, `niveau`, `message`, `contexte`, `entite_type`, `entite_id`, `levee_le`).

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\AutomationReevaluation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FileEtAlertesSocleTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_deux_tables_existent_avec_leurs_colonnes(): void
    {
        $attendues = [
            'automation_reevaluations' => ['evenement', 'entite_type', 'entite_id', 'depose_le'],
            'business_alertes' => ['cle', 'niveau', 'message', 'contexte', 'entite_type', 'entite_id', 'levee_le'],
        ];

        $manquantes = [];

        foreach ($attendues as $table => $colonnes) {
            foreach ($colonnes as $colonne) {
                if (! Schema::hasColumn($table, $colonne)) {
                    $manquantes[] = "{$table}.{$colonne}";
                }
            }
        }

        $this->assertSame([], $manquantes, 'Colonnes manquantes : '.implode(', ', $manquantes));
    }

    /** L'IDEMPOTENCE EST DANS L'INDEX. Deux fois le meme evenement sur la meme entite = une ligne. */
    public function test_deux_depots_identiques_ne_font_qu_une_ligne(): void
    {
        AutomationReevaluation::create([
            'evenement' => 'alerte.payout_failed',
            'entite_type' => 'alerte',
            'entite_id' => 7,
            'depose_le' => now(),
        ]);

        $this->expectException(QueryException::class);

        AutomationReevaluation::create([
            'evenement' => 'alerte.payout_failed',
            'entite_type' => 'alerte',
            'entite_id' => 7,
            'depose_le' => now(),
        ]);
    }

    /** TEMOIN — l'unicite porte sur le TRIPLET, pas sur l'evenement seul. */
    public function test_temoin_deux_entites_differentes_font_deux_lignes(): void
    {
        AutomationReevaluation::create([
            'evenement' => 'alerte.payout_failed',
            'entite_type' => 'alerte',
            'entite_id' => 7,
            'depose_le' => now(),
        ]);

        AutomationReevaluation::create([
            'evenement' => 'alerte.payout_failed',
            'entite_type' => 'alerte',
            'entite_id' => 8,
            'depose_le' => now(),
        ]);

        $this->assertSame(2, AutomationReevaluation::count());
    }

    public function test_le_contexte_d_une_alerte_se_relit_en_tableau(): void
    {
        $alerte = AlerteMetier::create([
            'cle' => 'webhook_backlog',
            'niveau' => 'critical',
            'message' => 'File de webhooks trop profonde',
            'contexte' => ['count' => 412],
            'levee_le' => now(),
        ]);

        $this->assertSame(412, $alerte->fresh()->contexte['count']);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

`php artisan test tests/Feature/Automation/FileEtAlertesSocleTest.php`
Attendu : échec, les tables n'existent pas.

- [ ] **Étape 3 : la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_reevaluations', function (Blueprint $table) {
            $table->id();
            $table->string('evenement');
            $table->string('entite_type');
            $table->unsignedBigInteger('entite_id');
            $table->timestamp('depose_le');

            $table->unique(['evenement', 'entite_type', 'entite_id'], 'automation_reevaluations_unicite');
        });

        Schema::create('business_alertes', function (Blueprint $table) {
            $table->id();
            $table->string('cle');
            $table->string('niveau');
            $table->text('message');
            $table->json('contexte')->nullable();
            $table->string('entite_type')->nullable();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->timestamp('levee_le');

            $table->index(['cle', 'levee_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_alertes');
        Schema::dropIfExists('automation_reevaluations');
    }
};
```

- [ ] **Étape 4 : les deux modèles**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une entree de la file : un evenement a repasser sur une entite. */
class AutomationReevaluation extends Model
{
    protected $table = 'automation_reevaluations';

    public $timestamps = false;

    protected $fillable = ['evenement', 'entite_type', 'entite_id', 'depose_le'];

    protected $casts = [
        'entite_id' => 'integer',
        'depose_le' => 'datetime',
    ];
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une alerte metier levee, persistee. Jusqu'ici elle n'existait que dans Sentry. */
class AlerteMetier extends Model
{
    protected $table = 'business_alertes';

    public $timestamps = false;

    protected $fillable = [
        'cle', 'niveau', 'message', 'contexte', 'entite_type', 'entite_id', 'levee_le',
    ];

    protected $casts = [
        'contexte' => 'array',
        'entite_id' => 'integer',
        'levee_le' => 'datetime',
    ];
}
```

- [ ] **Étape 5 : relancer, vérifier le vert, puis commit**

```bash
php artisan test tests/Feature/Automation/FileEtAlertesSocleTest.php
./vendor/bin/pint app/Models database/migrations tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add -A && git commit -m "feat(automation): la file de reevaluation et les alertes persistees"
```

---

### Tâche 2 : `FileDeReevaluation`, la porte unique

**Fichiers :**
- Créer : `app/Services/Automation/FileDeReevaluation.php`
- Créer : `tests/Feature/Automation/FileDeReevaluationTest.php`

**Interfaces :**
- Consomme : `AutomationReevaluation`.
- Produit : `deposer(string $evenement, string $entiteType, ?int $entiteId): bool` (rend `false`
  quand le dépôt est un doublon ou quand l'identifiant est `null`) ;
  `parEvenement(): array<string, array{entite: string, identifiants: list<int>, lignes: list<int>}>`
  — `lignes` porte les identifiants **des lignes de file**, dont la tâche 8 a besoin pour ne purger
  que ce qu'elle a lu ; `purger(list<int> $ids): void`.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationReevaluation;
use App\Services\Automation\FileDeReevaluation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FileDeReevaluationTest extends TestCase
{
    use RefreshDatabase;

    private function file(): FileDeReevaluation
    {
        return app(FileDeReevaluation::class);
    }

    /** DEUX FOIS LE MEME DEPOT = UNE LIGNE, et le second dit `false` sans lever. */
    public function test_un_depot_en_double_ne_leve_pas_et_ne_duplique_pas(): void
    {
        $this->assertTrue($this->file()->deposer('alerte.payout_failed', 'alerte', 7));
        $this->assertFalse($this->file()->deposer('alerte.payout_failed', 'alerte', 7));

        $this->assertSame(1, AutomationReevaluation::count());
    }

    /** TEMOIN — un depot NEUF rend bien `true` et ecrit. Sans lui, le test ci-dessus
     *  resterait vert si `deposer()` ne faisait jamais rien. */
    public function test_temoin_un_depot_neuf_ecrit_et_rend_vrai(): void
    {
        $this->assertTrue($this->file()->deposer('alerte.payout_failed', 'alerte', 7));
        $this->assertTrue($this->file()->deposer('alerte.payout_failed', 'alerte', 8));

        $this->assertSame(2, AutomationReevaluation::count());
    }

    /** Un identifiant absent n'entre PAS dans la file : rien a reevaluer. */
    public function test_un_identifiant_nul_n_est_pas_depose(): void
    {
        $this->assertFalse($this->file()->deposer('alerte.payout_failed', 'alerte', null));

        $this->assertSame(0, AutomationReevaluation::count());
    }

    public function test_la_file_se_lit_groupee_par_evenement(): void
    {
        $this->file()->deposer('alerte.payout_failed', 'alerte', 7);
        $this->file()->deposer('alerte.payout_failed', 'alerte', 9);
        $this->file()->deposer('booking.annulee', 'booking', 3);

        $lue = $this->file()->parEvenement();

        $this->assertSame(['alerte.payout_failed', 'booking.annulee'], array_keys($lue));
        $this->assertSame([7, 9], $lue['alerte.payout_failed']['identifiants']);
        $this->assertSame('alerte', $lue['alerte.payout_failed']['entite']);
        $this->assertSame([3], $lue['booking.annulee']['identifiants']);
    }

    /** TEMOIN DU RATTRAPAGE — une panne qui n'est PAS un doublon doit remonter. Sans lui,
     *  un `catch` trop large ferait taire une table absente et la file cesserait de se
     *  remplir sans que rien ne le dise. */
    public function test_une_panne_qui_n_est_pas_un_doublon_remonte(): void
    {
        Schema::drop('automation_reevaluations');

        $this->expectException(QueryException::class);

        $this->file()->deposer('alerte.payout_failed', 'alerte', 7);
    }

    public function test_la_purge_ne_retire_que_les_lignes_nommees(): void
    {
        $this->file()->deposer('alerte.payout_failed', 'alerte', 7);
        $this->file()->deposer('booking.annulee', 'booking', 3);

        $garder = AutomationReevaluation::where('evenement', 'booking.annulee')->value('id');
        $retirer = AutomationReevaluation::where('evenement', 'alerte.payout_failed')->pluck('id')->all();

        $this->file()->purger($retirer);

        $this->assertSame([$garder], AutomationReevaluation::pluck('id')->all());
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

- [ ] **Étape 3 : le service**

```php
<?php

namespace App\Services\Automation;

use App\Models\AutomationReevaluation;
use Illuminate\Database\QueryException;

/** La SEULE porte de la file. Deux ecouteurs y ecrivent ; la commande y lit. */
class FileDeReevaluation
{
    /** SQLSTATE d'atteinte a l'integrite : rendu par PDO sur MySQL comme sur SQLite. */
    private const DOUBLON = '23000';

    /**
     * L'unicite est tenue par l'index : on tente, et un doublon rend `false` sans lever.
     *
     * On ne rattrape QUE le doublon. Un `catch (QueryException)` nu ferait taire une table
     * absente ou une colonne renommee : la file cesserait de se remplir sans que rien ne le dise.
     */
    public function deposer(string $evenement, string $entiteType, ?int $entiteId): bool
    {
        if ($entiteId === null) {
            return false;
        }

        try {
            AutomationReevaluation::create([
                'evenement' => $evenement,
                'entite_type' => $entiteType,
                'entite_id' => $entiteId,
                'depose_le' => now(),
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== self::DOUBLON) {
                throw $e;
            }

            return false;
        }

        return true;
    }

    /** @return array<string, array{entite: string, identifiants: list<int>, lignes: list<int>}> */
    public function parEvenement(): array
    {
        $groupes = [];

        foreach (AutomationReevaluation::query()->orderBy('id')->get() as $ligne) {
            $groupes[$ligne->evenement] ??= [
                'entite' => $ligne->entite_type,
                'identifiants' => [],
                'lignes' => [],
            ];

            $groupes[$ligne->evenement]['identifiants'][] = $ligne->entite_id;
            $groupes[$ligne->evenement]['lignes'][] = $ligne->id;
        }

        return $groupes;
    }

    /** @param list<int> $ids */
    public function purger(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        AutomationReevaluation::whereIn('id', $ids)->delete();
    }
}
```

- [ ] **Étape 4 : relancer, vérifier le vert, portails, commit**

---

### Tâche 3 : le contrat de déclencheur, son registre et son garde-fou

**Fichiers :**
- Créer : `app/Services/Automation/Contracts/Declencheur.php`
- Créer : `app/Services/Automation/Registre/DeclencheurRegistre.php`
- Créer : `tests/Feature/Automation/DeclencheurRegistreTest.php`

**Interfaces :**
- Produit : `Declencheur` (`cle`, `evenement`, `entite`, `sApplique`, `identifiant`, `libelle`) ;
  `DeclencheurRegistre::enregistrer(Declencheur)`, `toutes()`, `trouver(string)`,
  `pourEvenement(object $evenement): list<Declencheur>`.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Services\Automation\Contracts\Declencheur;
use App\Services\Automation\Registre\DeclencheurRegistre;
use Tests\TestCase;

class DeclencheurRegistreTest extends TestCase
{
    private function faux(string $cle, string $classe, bool $applique): Declencheur
    {
        return new class($cle, $classe, $applique) implements Declencheur
        {
            public function __construct(
                private string $cle,
                private string $classe,
                private bool $applique,
            ) {}

            public function cle(): string
            {
                return $this->cle;
            }

            public function evenement(): string
            {
                return $this->classe;
            }

            public function entite(): string
            {
                return 'alerte';
            }

            public function sApplique(object $evenement): bool
            {
                return $this->applique;
            }

            public function identifiant(object $evenement): ?int
            {
                return 42;
            }

            public function libelle(): string
            {
                return 'Un declencheur';
            }
        };
    }

    /** LA CLASSE NE SUFFIT PAS. Cinq alertes partagent un evenement : c'est `sApplique`
     *  qui les separe. Sans elle, un depot partirait pour les cinq. */
    public function test_seuls_les_declencheurs_qui_s_appliquent_sont_rendus(): void
    {
        $registre = new DeclencheurRegistre;
        $registre->enregistrer($this->faux('a', \stdClass::class, true));
        $registre->enregistrer($this->faux('b', \stdClass::class, false));

        $trouves = $registre->pourEvenement(new \stdClass);

        $this->assertSame(['a'], array_map(fn (Declencheur $d): string => $d->cle(), $trouves));
    }

    /** TEMOIN — un declencheur qui s'applique ET dont la classe correspond EST rendu. */
    public function test_temoin_un_declencheur_applicable_est_rendu(): void
    {
        $registre = new DeclencheurRegistre;
        $registre->enregistrer($this->faux('a', \stdClass::class, true));

        $this->assertCount(1, $registre->pourEvenement(new \stdClass));
    }

    public function test_une_classe_d_evenement_differente_n_est_jamais_rendue(): void
    {
        $registre = new DeclencheurRegistre;
        $registre->enregistrer($this->faux('a', \RuntimeException::class, true));

        $this->assertSame([], $registre->pourEvenement(new \stdClass));
    }

    public function test_le_registre_retrouve_un_declencheur_par_sa_cle(): void
    {
        $registre = new DeclencheurRegistre;
        $registre->enregistrer($this->faux('a', \stdClass::class, true));

        $this->assertNotNull($registre->trouver('a'));
        $this->assertNull($registre->trouver('inconnu'));
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

- [ ] **Étape 3 : le contrat**

```php
<?php

namespace App\Services\Automation\Contracts;

/** Ce qu'un evenement du produit doit savoir dire au moteur. */
interface Declencheur
{
    /** La cle stockee dans `automation_rules.declencheur`, ex. `alerte.payout_failed`. */
    public function cle(): string;

    /** @return class-string La classe d'evenement ecoutee. */
    public function evenement(): string;

    /** La cle d'entite visee, ex. `alerte`. */
    public function entite(): string;

    /**
     * Cet evenement-ci me concerne-t-il ?
     *
     * Separee d'`identifiant()` a dessein : cinq alertes partagent une classe d'evenement, et
     * « ce n'est pas moi » ne doit pas se confondre avec « je n'ai pas trouve d'entite ».
     */
    public function sApplique(object $evenement): bool;

    /** L'entite visee, ou `null` si l'evenement n'en designe aucune. */
    public function identifiant(object $evenement): ?int;

    public function libelle(): string;
}
```

- [ ] **Étape 4 : le registre**

```php
<?php

namespace App\Services\Automation\Registre;

use App\Services\Automation\Contracts\Declencheur;

/** Cle => declencheur. Le vocabulaire des evenements, en code. */
class DeclencheurRegistre
{
    /** @var array<string, Declencheur> */
    protected array $declencheurs = [];

    public function enregistrer(Declencheur $declencheur): void
    {
        $this->declencheurs[$declencheur->cle()] = $declencheur;
    }

    /** @return array<string, Declencheur> */
    public function toutes(): array
    {
        return $this->declencheurs;
    }

    public function trouver(string $cle): ?Declencheur
    {
        return $this->declencheurs[$cle] ?? null;
    }

    /** @return list<Declencheur> */
    public function pourEvenement(object $evenement): array
    {
        return array_values(array_filter($this->declencheurs, function (Declencheur $d) use ($evenement): bool {
            // La classe d'abord, la discrimination ensuite : `sApplique` n'a pas a se defendre
            // contre un evenement d'un autre type.
            $classe = $d->evenement();

            return $evenement instanceof $classe && $d->sApplique($evenement);
        }));
    }
}
```

- [ ] **Étape 5 : relancer, vérifier le vert, portails, commit**

---

### Tâche 4 : le descripteur `alerte` et le descripteur `mission`

**Fichiers :**
- Créer : `app/Services/Automation/Descripteurs/AlerteDescriptor.php`
- Créer : `app/Services/Automation/Descripteurs/MissionDescriptor.php`
- Modifier : `app/Providers/AutomationServiceProvider.php`
- Créer : `tests/Feature/Automation/DescripteursPhase2Test.php`

**Interfaces :**
- Consomme : `EntityDescriptor`, `FieldBinding::colonne()`, `RuleTreeEvaluator::OPERATEURS_CONNUS`.
- Produit : les entités `alerte` et `mission` dans `EntiteRegistre`.

**Le modèle à suivre est `BookingDescriptor`** : `baseQuery()` rend `$this->modele()->newQuery()`,
`fields()` mémoïse dans `$this->champs ??= [...]`, `operators()` rend les opérateurs connus, et
`modele()` rend une instance neuve — **l'invariance des génériques Eloquent interdit
`Modele::query()` dans ce contexte**, le lot 1 l'a déjà payé.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DescripteursPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_les_deux_entites_sont_enregistrees(): void
    {
        $cles = app(EntiteRegistre::class)->cles();

        $this->assertContains('alerte', $cles);
        $this->assertContains('mission', $cles);
    }

    /** CHAQUE CHAMP DECLARE DOIT S'EXECUTER. Un champ qui nomme une colonne absente ne se
     *  voit qu'a l'execution : SQLite prend un identifiant inconnu pour une chaine litterale. */
    public function test_chaque_champ_declare_s_execute_vraiment(): void
    {
        $registre = app(EntiteRegistre::class);
        $ecarts = [];

        foreach (['alerte', 'mission'] as $cle) {
            $descripteur = $registre->descripteur($cle);

            foreach (array_keys($descripteur->fields()) as $champ) {
                try {
                    $requete = $descripteur->baseQuery();
                    app(RuleTreeEvaluator::class)->apply(
                        $requete,
                        ['field' => $champ, 'op' => 'is_not_null', 'value' => null],
                        $descripteur
                    );
                    $requete->limit(1)->get();
                } catch (\Throwable $e) {
                    $ecarts[] = "{$cle}.{$champ} : ".$e->getMessage();
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    public function test_une_condition_sur_la_cle_d_alerte_selectionne(): void
    {
        AlerteMetier::create(['cle' => 'payout_failed', 'niveau' => 'critical', 'message' => 'a', 'levee_le' => now()]);
        AlerteMetier::create(['cle' => 'webhook_backlog', 'niveau' => 'critical', 'message' => 'b', 'levee_le' => now()]);

        $descripteur = app(EntiteRegistre::class)->descripteur('alerte');
        $requete = $descripteur->baseQuery();

        app(RuleTreeEvaluator::class)->apply(
            $requete,
            ['field' => 'cle', 'op' => 'eq', 'value' => 'payout_failed'],
            $descripteur
        );

        $this->assertSame(1, $requete->count());
    }

    /** TEMOIN — sans condition, les deux alertes sont bien la. Sans lui, le test ci-dessus
     *  passerait au vert sur une requete qui ne rend jamais rien. */
    public function test_temoin_la_requete_de_base_voit_les_deux_alertes(): void
    {
        AlerteMetier::create(['cle' => 'payout_failed', 'niveau' => 'critical', 'message' => 'a', 'levee_le' => now()]);
        AlerteMetier::create(['cle' => 'webhook_backlog', 'niveau' => 'critical', 'message' => 'b', 'levee_le' => now()]);

        $this->assertSame(2, app(EntiteRegistre::class)->descripteur('alerte')->baseQuery()->count());
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

- [ ] **Étape 3 : `AlerteDescriptor`**

```php
<?php

namespace App\Services\Automation\Descripteurs;

use App\Models\AlerteMetier;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les alertes metier, vues par une regle.
 *
 * `contexte` n'est PAS expose : son contenu change d'une alerte a l'autre, et MySQL reordonne
 * les cles JSON. Un champ qui promettrait de le filtrer mentirait.
 */
class AlerteDescriptor implements EntityDescriptor
{
    /** @var array<string, FieldBinding>|null */
    protected ?array $champs = null;

    /** @return Builder<Model> */
    public function baseQuery(): Builder
    {
        return $this->modele()->newQuery();
    }

    /** @return array<string, FieldBinding> */
    public function fields(): array
    {
        return $this->champs ??= [
            'cle' => FieldBinding::colonne('business_alertes.cle'),
            'niveau' => FieldBinding::colonne('business_alertes.niveau'),
            'entite_type' => FieldBinding::colonne('business_alertes.entite_type'),
            'levee_le' => FieldBinding::colonne('business_alertes.levee_le'),
        ];
    }

    /** @return list<string> */
    public function operators(): array
    {
        return RuleTreeEvaluator::OPERATEURS_CONNUS;
    }

    protected function modele(): Model
    {
        return new AlerteMetier;
    }
}
```

- [ ] **Étape 4 : `MissionDescriptor`**

**Mesure d'abord, écris ensuite.** Ouvre `app/Models/Mission.php` et la migration de la table
`missions`, et n'expose **que** des colonnes qui existent vraiment. Vise ces notions, en gardant
le nom réel de la colonne : le statut, la réservation liée, le prestataire, la date de début, la
date de fin, l'horodatage de création. **N'invente aucun nom** — le test de l'étape 1 exécute
chaque champ, et un nom faux y tombera.

- [ ] **Étape 5 : enregistrer les deux entités**

Dans `AutomationServiceProvider`, à côté de l'enregistrement de `booking`.

- [ ] **Étape 6 : relancer, vérifier le vert, portails, commit**

---

### Tâche 5 : les cinq déclencheurs d'alerte

**Fichiers :**
- Créer : `app/Services/Automation/Declencheurs/AlerteMetierDeclencheur.php`
- Modifier : `app/Providers/AutomationServiceProvider.php`
- Créer : `tests/Feature/Automation/DeclencheursDAlerteTest.php`

**Interfaces :**
- Consomme : `Declencheur`, `BusinessAlertRaised`.
- Produit : cinq déclencheurs enregistrés, de clés `alerte.payment_capture_failed`,
  `alerte.payout_failed`, `alerte.webhook_backlog`, `alerte.stuck_mission_holding_funds`,
  `alerte.reconciliation_divergence`.

**Une seule classe, paramétrée.** Les cinq ne diffèrent que par leur clé d'alerte et leur libellé :
cinq classes identiques à une chaîne près seraient de la duplication.

**`identifiant()` rend toujours `null`** : ces déclencheurs visent l'entité `alerte`, dont la ligne
n'existe pas encore au moment où l'événement est levé. C'est l'écouteur de la tâche 6 qui la crée
puis dépose. Le registre sert ici de **catalogue** : il nomme les déclencheurs sélectionnables dans
le constructeur de règles, et fait autorité sur les clés que l'écouteur emploie.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Events\BusinessAlertRaised;
use App\Services\Automation\Contracts\Declencheur;
use App\Services\Automation\Registre\DeclencheurRegistre;
use App\Support\Alerts\BusinessAlerts;
use Tests\TestCase;

class DeclencheursDAlerteTest extends TestCase
{
    /** Les cinq cles emises par BusinessAlerts, relevees dans le code le 2026-08-30. */
    private const CLES_EMISES = [
        'payment_capture_failed',
        'payout_failed',
        'webhook_backlog',
        'stuck_mission_holding_funds',
        'reconciliation_divergence',
    ];

    public function test_chaque_alerte_emise_a_son_declencheur(): void
    {
        $registre = app(DeclencheurRegistre::class);
        $manquants = [];

        foreach (self::CLES_EMISES as $cle) {
            if ($registre->trouver('alerte.'.$cle) === null) {
                $manquants[] = $cle;
            }
        }

        $this->assertSame([], $manquants, 'Alertes sans declencheur : '.implode(', ', $manquants));
    }

    /** L'INVERSE AUSSI : un declencheur qui ecoute une alerte que personne ne leve est mort. */
    public function test_aucun_declencheur_d_alerte_n_ecoute_dans_le_vide(): void
    {
        $source = (string) file_get_contents(app_path('Support/Alerts/BusinessAlerts.php'));
        $orphelins = [];

        foreach (app(DeclencheurRegistre::class)->toutes() as $cle => $declencheur) {
            if (! str_starts_with($cle, 'alerte.')) {
                continue;
            }

            $alerte = substr($cle, strlen('alerte.'));

            if (! str_contains($source, "'".$alerte."'")) {
                $orphelins[] = $cle;
            }
        }

        $this->assertSame([], $orphelins, 'Declencheurs sans emetteur : '.implode(', ', $orphelins));
    }

    public function test_un_declencheur_ne_s_applique_qu_a_sa_propre_alerte(): void
    {
        $declencheur = app(DeclencheurRegistre::class)->trouver('alerte.payout_failed');

        $sien = new BusinessAlertRaised('critical', 'payout_failed', 'x');
        $autre = new BusinessAlertRaised('critical', 'webhook_backlog', 'y');

        $this->assertTrue($declencheur->sApplique($sien));
        $this->assertFalse($declencheur->sApplique($autre));
    }

    /** TEMOIN — le registre separe bien les cinq : un evenement n'en reveille qu'UN. */
    public function test_temoin_un_evenement_ne_reveille_qu_un_declencheur(): void
    {
        $trouves = app(DeclencheurRegistre::class)
            ->pourEvenement(new BusinessAlertRaised('critical', 'payout_failed', 'x'));

        $this->assertCount(1, $trouves);
        $this->assertSame('alerte.payout_failed', $trouves[0]->cle());
    }

    public function test_les_cinq_declencheurs_visent_l_entite_alerte(): void
    {
        foreach (app(DeclencheurRegistre::class)->toutes() as $cle => $declencheur) {
            if (str_starts_with($cle, 'alerte.')) {
                $this->assertSame('alerte', $declencheur->entite(), $cle);
            }
        }
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

- [ ] **Étape 3 : la classe paramétrée**

```php
<?php

namespace App\Services\Automation\Declencheurs;

use App\Events\BusinessAlertRaised;
use App\Services\Automation\Contracts\Declencheur;

/** Une alerte metier devient un declencheur. Les cinq ne different que par leur cle. */
class AlerteMetierDeclencheur implements Declencheur
{
    public function __construct(
        protected string $alerte,
        protected string $libelle,
    ) {}

    public function cle(): string
    {
        return 'alerte.'.$this->alerte;
    }

    public function evenement(): string
    {
        return BusinessAlertRaised::class;
    }

    public function entite(): string
    {
        return 'alerte';
    }

    public function sApplique(object $evenement): bool
    {
        return $evenement instanceof BusinessAlertRaised && $evenement->key === $this->alerte;
    }

    /** La ligne d'alerte n'existe pas encore ici : c'est l'ecouteur qui la cree, puis depose. */
    public function identifiant(object $evenement): ?int
    {
        return null;
    }

    public function libelle(): string
    {
        return $this->libelle;
    }
}
```

- [ ] **Étape 4 : enregistrer les cinq**

Dans `AutomationServiceProvider`, avec des libellés en français lisibles par un administrateur :
« La capture d'un paiement a échoué », « Un versement prestataire a échoué », « La file de webhooks
déborde », « Une mission bloquée retient des fonds », « La réconciliation diverge ».

- [ ] **Étape 5 : relancer, vérifier le vert, portails, commit**

---

### Tâche 6 : l'écouteur des alertes — persister, puis déposer

**Fichiers :**
- Créer : `app/Listeners/Automation/EnregistrerLAlerteMetier.php`
- Modifier : `app/Providers/EventServiceProvider.php`
- Créer : `tests/Feature/Automation/EcouteurDAlerteTest.php`

**Interfaces :**
- Consomme : `FileDeReevaluation`, `DeclencheurRegistre`, `AlerteMetier`.
- Produit : une ligne `business_alertes` et, si un déclencheur existe, une ligne
  `automation_reevaluations`.

**L'écouteur Sentry existant n'est pas touché.** Le nouvel écouteur s'ajoute à côté, dans le même
tableau de `EventServiceProvider` (l'entrée `BusinessAlertRaised::class` existe déjà, ligne 35).

**L'entité liée** : quand `contexte` porte `booking_id`, l'alerte note `entite_type = 'booking'` et
`entite_id` ; idem pour `mission_id`. Sinon les deux restent `null`. **Ne devine aucune autre
clé** — lis `BusinessAlerts.php` et n'accepte que celles qui y sont écrites.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\AutomationReevaluation;
use App\Models\Booking;
use App\Support\Alerts\BusinessAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcouteurDAlerteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_alerte_levee_est_persistee_et_deposee(): void
    {
        BusinessAlerts::webhookBacklog(412);

        $alerte = AlerteMetier::sole();

        $this->assertSame('webhook_backlog', $alerte->cle);
        $this->assertSame(412, $alerte->contexte['count']);
        $this->assertNull($alerte->entite_type);

        $depot = AutomationReevaluation::sole();

        $this->assertSame('alerte.webhook_backlog', $depot->evenement);
        $this->assertSame('alerte', $depot->entite_type);
        $this->assertSame($alerte->id, $depot->entite_id);
    }

    public function test_une_alerte_qui_porte_une_reservation_la_note(): void
    {
        $booking = Booking::factory()->create();

        BusinessAlerts::paymentCaptureFailed($booking);

        $alerte = AlerteMetier::sole();

        $this->assertSame('booking', $alerte->entite_type);
        $this->assertSame($booking->id, $alerte->entite_id);
    }

    /** L'ECOUTEUR ECRIT ET REND LA MAIN. Aucune regle ne tourne dans la requete de
     *  l'utilisateur : `QUEUE_CONNECTION=sync`, tout s'y paierait comptant. */
    public function test_l_ecouteur_ne_declenche_aucun_passage(): void
    {
        BusinessAlerts::webhookBacklog(412);

        $this->assertSame(0, \App\Models\AutomationRun::count());
        $this->assertSame(0, \App\Models\AutomationAction::count());
    }

    /** TEMOIN — l'ecouteur a bien tourne : sans lui, le test ci-dessus serait vert
     *  en mesurant une absence totale d'ecouteur. */
    public function test_temoin_l_ecouteur_a_bien_ecrit(): void
    {
        BusinessAlerts::webhookBacklog(412);

        $this->assertSame(1, AlerteMetier::count());
        $this->assertSame(1, AutomationReevaluation::count());
    }

    public function test_deux_alertes_identiques_font_deux_lignes_et_deux_depots(): void
    {
        BusinessAlerts::webhookBacklog(412);
        BusinessAlerts::webhookBacklog(500);

        // Chaque alerte est un FAIT distinct : deux lignes, deux entites, donc deux depots.
        $this->assertSame(2, AlerteMetier::count());
        $this->assertSame(2, AutomationReevaluation::count());
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

- [ ] **Étape 3 : l'écouteur**

```php
<?php

namespace App\Listeners\Automation;

use App\Events\BusinessAlertRaised;
use App\Models\AlerteMetier;
use App\Services\Automation\FileDeReevaluation;
use App\Services\Automation\Registre\DeclencheurRegistre;

/** Persiste l'alerte, puis depose une reevaluation. Rien d'autre : la requete attend. */
class EnregistrerLAlerteMetier
{
    public function __construct(
        protected FileDeReevaluation $file,
        protected DeclencheurRegistre $declencheurs,
    ) {}

    public function handle(BusinessAlertRaised $evenement): void
    {
        $alerte = AlerteMetier::create([
            'cle' => $evenement->key,
            'niveau' => $evenement->level,
            'message' => $evenement->message,
            'contexte' => $evenement->context,
            'entite_type' => $this->typeLie($evenement),
            'entite_id' => $this->identifiantLie($evenement),
            'levee_le' => now(),
        ]);

        foreach ($this->declencheurs->pourEvenement($evenement) as $declencheur) {
            $this->file->deposer($declencheur->cle(), $declencheur->entite(), $alerte->id);
        }
    }
}
```

`typeLie()` et `identifiantLie()` lisent `booking_id` puis `mission_id` dans le contexte, et rendent
`null` quand ni l'une ni l'autre n'y est. **Aucune autre clé.**

- [ ] **Étape 4 : brancher dans `EventServiceProvider`**

Ajouter la classe au tableau existant de `BusinessAlertRaised::class`, **sans retirer** l'écouteur
Sentry.

- [ ] **Étape 5 : relancer, vérifier le vert, portails, commit**

---

### Tâche 7 : l'écouteur générique, piloté par le registre

**Fichiers :**
- Créer : `app/Listeners/Automation/DeposerLaReevaluation.php`
- Créer : `tests/Feature/Automation/EcouteurGeneriqueTest.php`

**Interfaces :**
- Consomme : `DeclencheurRegistre`, `FileDeReevaluation`.
- Produit : `handle(object $evenement): void` — dépose une ligne par déclencheur applicable.

Cet écouteur sert les événements qui **désignent** une entité déjà existante. Aucun n'est branché
en phase 2 — les cinq alertes passent par l'écouteur de la tâche 6 — mais il est le point
d'accroche des phases suivantes, et il se teste dès maintenant avec un déclencheur de test.

**Attention** : c'est exactement le profil du défaut le plus fréquent de ce dépôt — du code complet
que personne n'appelle. Il est écrit ici **parce que la tâche 8 en a besoin pour le drain** et
qu'il est couvert par ses propres tests ; **note-le dans ton rapport** si tu constates qu'il reste
sans appelant à la fin de la phase.

- [ ] **Étape 1 : écrire le test** — un déclencheur anonyme, un événement `stdClass`, et la
      vérification qu'un `identifiant()` rendant `null` ne dépose rien. Le témoin : un
      `identifiant()` qui rend un entier dépose bien.

- [ ] **Étape 2 : lancer, vérifier l'échec**

- [ ] **Étape 3 : l'écouteur**

```php
<?php

namespace App\Listeners\Automation;

use App\Services\Automation\FileDeReevaluation;
use App\Services\Automation\Registre\DeclencheurRegistre;

/** Un evenement qui DESIGNE une entite : on depose, on rend la main. */
class DeposerLaReevaluation
{
    public function __construct(
        protected DeclencheurRegistre $declencheurs,
        protected FileDeReevaluation $file,
    ) {}

    public function handle(object $evenement): void
    {
        foreach ($this->declencheurs->pourEvenement($evenement) as $declencheur) {
            $this->file->deposer(
                $declencheur->cle(),
                $declencheur->entite(),
                $declencheur->identifiant($evenement)
            );
        }
    }
}
```

- [ ] **Étape 4 : relancer, vérifier le vert, portails, commit**

---

### Tâche 8 : le drain, dans la commande

**Fichiers :**
- Modifier : `app/Console/Commands/ExecuterLAutomatisation.php`
- Créer : `tests/Feature/Automation/DrainTest.php`

**Interfaces :**
- Consomme : `FileDeReevaluation::parEvenement()` et `purger()`, `RuleRunner::executer($regle, $identifiants)`.

**L'ordre compte** : le drain passe **avant** les règles à cadence. Une alerte qui vient d'être
levée doit être traitée au premier passage, pas au suivant.

**La règle visée** est celle dont `declencheur` vaut la clé de l'événement **et** dont `etat` est
actif. Les mêmes gardes qu'à cadence s'appliquent — l'interrupteur, l'observation obligatoire, le
quota — parce que tout passe par `RuleRunner::executer()`.

**La cadence ne s'applique PAS au drain** : une règle événementielle n'a pas de cadence, elle
répond à ce qui arrive.

**La purge** : les lignes drainées se retirent **après** le passage, et **seulement celles qui ont
été lues**. Une ligne déposée pendant le passage ne doit pas disparaître sans avoir été traitée —
c'est pour cela que `parEvenement()` rend aussi les identifiants de lignes.

**Un événement sans règle branchée** : la ligne se purge quand même. Sinon la file grossit sans
fin, et le même événement se relit chaque minute pour rien.

- [ ] **Étape 1 : écrire le test** — six cas au minimum :
  1. une alerte levée puis un passage de commande pose bien une action sur cette alerte ;
  2. **témoin** : sans alerte, le même passage ne pose rien ;
  3. la file est vide après le passage ;
  4. une règle événementielle ne voit **que** l'entité déposée, pas les autres alertes de la table ;
  5. un dépôt dont aucune règle ne porte le déclencheur est purgé quand même ;
  6. interrupteur fermé : rien n'est drainé **et la file n'est pas purgée** — la coupure ne doit pas
     manger les événements.

- [ ] **Étape 2 : lancer, vérifier l'échec**

- [ ] **Étape 3 : le drain**

`handle()` reçoit `FileDeReevaluation` en plus, et appelle `drainer()` **après** la garde du
drapeau et **avant** la boucle des règles à cadence.

```php
    /** Le drain passe AVANT la cadence : une alerte levee se traite au premier passage. */
    protected function drainer(RuleRunner $runner, FileDeReevaluation $file): void
    {
        foreach ($file->parEvenement() as $evenement => $groupe) {
            $regles = AutomationRule::query()
                ->whereIn('etat', self::ETATS_ACTIFS)
                ->where('declencheur', $evenement)
                ->get();

            foreach ($regles as $regle) {
                $passage = $runner->executer($regle, $groupe['identifiants']);

                $this->line(sprintf(
                    '%s (%s) : %d entité(s), %d action(s), %s',
                    $regle->nom,
                    $evenement,
                    $passage->entites_vues,
                    $passage->actions_posees,
                    $passage->statut
                ));
            }

            // On purge MEME sans regle branchee : sinon la file grossit sans fin et le
            // meme evenement se relit chaque minute pour rien.
            $file->purger($groupe['lignes']);
        }
    }
```

**Ne touche pas à `estDue()`** : la cadence ne concerne que les règles dont `declencheur` vaut
`cadence`, et le drain ne la consulte jamais.

- [ ] **Étape 4 : relancer, vérifier le vert, portails, commit**

---

### Tâche 9 : le bout en bout, le garde-fou et la vérification d'ensemble

**Fichiers :**
- Créer : `tests/Feature/Automation/BoutEnBoutEvenementielTest.php`
- Modifier : `tests/Feature/Automation/RegistresTest.php`

- [ ] **Étape 1 : le test de bout en bout**

Un seul test, qui suit le chemin entier sans raccourci : une règle sur l'entité `alerte`, de
déclencheur `alerte.payout_failed`, mise en observation puis armée par le chemin réel ;
`BusinessAlerts::payoutFailed($payout)` levée ; `php artisan automation:executer` ; et la
vérification qu'une ligne `executee` a été posée sur l'alerte, que la file est vide, et que
**rien n'a été posé sur une alerte d'une autre clé** présente dans la table.

Le témoin : la même chaîne avec l'interrupteur fermé ne pose rien.

- [ ] **Étape 2 : étendre le garde-fou des registres**

Ajouter à `RegistresTest.php` les invariants des déclencheurs : chaque déclencheur déclare une clé
non vide égale à celle du registre, un libellé non vide, une classe d'événement qui existe
(`class_exists`), et une entité **enregistrée** dans `EntiteRegistre`. Plus le témoin : le registre
des déclencheurs n'est pas vide.

- [ ] **Étape 3 : la suite complète**

```bash
git status --short          # l'arbre doit etre propre AVANT de lancer
composer test:parallele
```

**Ne modifier aucun fichier pendant que la suite tourne.**

- [ ] **Étape 4 : les deux portails**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
```

- [ ] **Étape 5 : commit**

---

## Ce que la phase 2 ne fait pas

| Sujet | Pourquoi |
|---|---|
| Les écrans | Phase 3. La phase 2 se pilote en ligne de commande, comme la phase 1 |
| Les cinq règles livrées | Phase 5. La phase 2 livre les **déclencheurs**, pas les règles qui s'y branchent |
| Les actions qui écrivent dans le domaine | Phase 4, et seulement après les deux vitesses |
| Retirer la voie Sentry | Interdit par la spec : on ne retire pas un chemin d'alerte sur l'argent dans le lot qui construit son remplaçant |
| Un descripteur `provider_payout` | L'alerte de versement vise l'entité `alerte`, qui porte déjà sa clé et son contexte |
