# Moteur d'automatisation — plan d'implémentation, phase 1 (le noyau)

> **Pour les agents :** SOUS-SKILL REQUIS — employer `superpowers:subagent-driven-development`
> (recommandé) ou `superpowers:executing-plans` pour dérouler ce plan tâche par tâche. Les étapes
> emploient des cases à cocher (`- [ ]`).

**But :** livrer le noyau exécutable du moteur — une règle naît, observe, s'arme, balaie et pose des
actions inoffensives, sous quota et sous interrupteur — piloté en ligne de commande.

**Architecture :** trois tables, trois registres en code (entités, actions ; les déclencheurs
d'événements viennent en phase 2), un `RuleRunner` qui évalue en **une requête SQL** via
l'évaluateur du lot 1, et une commande d'ordonnanceur. Aucune action n'écrit dans le domaine.

**Pile :** PHP 8.5, Laravel 12, PHPUnit. Base applicative MySQL, base de tests SQLite.

**Spec :** `docs/superpowers/specs/2026-08-30-moteur-automatisation-design.md`

## Contraintes globales

- **Le vocabulaire reste en code.** Aucune table de champs, d'opérateurs ou d'actions.
- **Aucune dépendance neuve.**
- **Aucune action de la phase 1 n'écrit dans le domaine métier.** `toucheAuDomaine()` rend `false`
  pour les deux actions livrées.
- L'évaluation passe par `App\Services\Conditions\RuleTreeEvaluator::apply(Builder $racine, array $noeud, EntityDescriptor $entite): void` — **une seule requête SQL par règle et par passage**.
- **Politique de reprise : toujours par entité.** `une_fois` signifie une fois par entité.
- **Le quota bride, il ne suspend pas.** La suspension vient de **trois** plafonds consécutifs, ou
  de **trois** passages entièrement en échec.
- **Une règle au journal d'observation vide ne peut pas être armée.**
- L'interrupteur global est `FeatureFlagService::isEnabled('automation')`. **Un drapeau absent de
  `config('features')` rend `false`** : la clé doit y être ajoutée, à `false`.
- Commentaires : **deux lignes maximum**. Le code dit QUOI, le commit dit POURQUOI.
- Portails avant chaque commit : `./vendor/bin/pint <chemins>` puis
  `./vendor/bin/phpstan analyse --no-progress` (sans argument de chemin, niveau 6, génériques
  Eloquent exigés).
- Commits sur `main` — consigne du dépôt.
- **Un test de refus exige un témoin positif.**

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `database/migrations/2026_09_29_090000_socle_du_moteur_d_automatisation.php` | Les trois tables de la phase 1 |
| `app/Models/AutomationRule.php` | La règle, sa machine à états |
| `app/Models/AutomationRun.php` | Un passage |
| `app/Models/AutomationAction.php` | Journal + registre « déjà agi » |
| `app/Services/Automation/Contracts/Action.php` | Le contrat d'une action — **nommé `Action` et non `AutomationAction`** : ce dernier est déjà le modèle Eloquent du journal |
| `app/Services/Automation/ActionResult.php` | Le résultat d'une action |
| `app/Services/Automation/Actions/NotifierLesAdmins.php` | Action inoffensive n° 1 |
| `app/Services/Automation/Actions/Journaliser.php` | Action inoffensive n° 2 |
| `app/Notifications/Automation/RegleDeclencheeNotification.php` | Le message envoyé aux admins |
| `app/Services/Automation/Registre/ActionRegistre.php` | Clé → action |
| `app/Services/Automation/Registre/EntiteRegistre.php` | Clé → descripteur d'entité |
| `app/Services/Automation/Descripteurs/BookingDescriptor.php` | Les réservations, vues par une règle |
| `app/Services/Automation/RuleRunner.php` | Évalue, pose, journalise, bride |
| `app/Providers/AutomationServiceProvider.php` | Enregistre les deux registres |
| `app/Console/Commands/ExecuterLAutomatisation.php` | `automation:executer` |
| `config/features.php` | La clé `automation`, à `false` |

**Les tables `automation_reevaluations` et `automation_action_settings` ne sont PAS créées ici** :
elles servent aux phases 2 et 4. Chaque phase apporte sa migration.

---

### Tâche 1 : les trois tables et leurs modèles

**Fichiers :**
- Créer : `database/migrations/2026_09_29_090000_socle_du_moteur_d_automatisation.php`
- Créer : `app/Models/AutomationRule.php`, `app/Models/AutomationRun.php`, `app/Models/AutomationAction.php`
- Créer : `tests/Feature/Automation/SocleTest.php`

**Interfaces :**
- Produit : `AutomationRule` (`nom`, `entite`, `declencheur`, `cadence`, `conditions` json,
  `actions` json, `politique_reprise`, `etat`, `quota_par_passage`, `plafond_journalier`,
  `plafonds_consecutifs`, `echecs_consecutifs`, `dernier_passage_le`), `AutomationRun`,
  `AutomationAction` — le modèle Eloquent du journal. Le contrat d'action de la tâche 2 s'appelle
  `Action` précisément pour ne pas entrer en collision avec lui.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocleTest extends TestCase
{
    use RefreshDatabase;

    private function regle(array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Missions sans intervenant',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vu']]],
        ], $attributs));
    }

    public function test_une_regle_nait_en_brouillon_avec_ses_defauts(): void
    {
        $regle = $this->regle();

        $this->assertSame('brouillon', $regle->etat);
        $this->assertSame('une_fois', $regle->politique_reprise);
        $this->assertSame(50, $regle->quota_par_passage);
        $this->assertSame(0, $regle->plafonds_consecutifs);
    }

    public function test_les_colonnes_json_se_relisent_en_tableau(): void
    {
        $regle = $this->regle()->fresh();

        $this->assertSame('en_attente', $regle->conditions['value']);
        $this->assertSame('journaliser', $regle->actions[0]['cle']);
    }

    public function test_un_passage_et_ses_actions_appartiennent_a_leur_regle(): void
    {
        $regle = $this->regle();

        $passage = AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => 'observation',
            'demarre_le' => now(),
        ]);

        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => 'booking',
            'entite_id' => 42,
            'action_cle' => 'journaliser',
            'mode' => 'observation',
            'resultat' => 'simulee',
            'pose_le' => now(),
        ]);

        $this->assertSame(1, $regle->passages()->count());
        $this->assertSame(1, $regle->actionsPosees()->count());
        $this->assertSame($regle->id, AutomationAction::first()->regle->id);
    }

    /** TEMOIN — supprimer la regle emporte son journal, la contrainte est bien posee. */
    public function test_temoin_supprimer_une_regle_emporte_ses_lignes(): void
    {
        $regle = $this->regle();

        AutomationRun::create(['automation_rule_id' => $regle->id, 'mode' => 'observation', 'demarre_le' => now()]);

        $regle->delete();

        $this->assertSame(0, AutomationRun::count());
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Automation/SocleTest.php`
Attendu : ÉCHEC — `Class "App\Models\AutomationRule" not found`.

- [ ] **Étape 3 : écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('entite');
            $table->string('declencheur')->default('cadence');
            $table->string('cadence')->nullable();
            $table->json('conditions');
            $table->json('actions');
            $table->string('politique_reprise')->default('une_fois');
            $table->string('etat')->default('brouillon');
            $table->unsignedInteger('quota_par_passage')->default(50);
            $table->unsignedInteger('plafond_journalier')->default(500);
            $table->unsignedTinyInteger('plafonds_consecutifs')->default(0);
            $table->unsignedTinyInteger('echecs_consecutifs')->default(0);
            $table->timestamp('dernier_passage_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['etat', 'declencheur']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('mode');
            $table->timestamp('demarre_le');
            $table->timestamp('termine_le')->nullable();
            $table->unsignedInteger('entites_vues')->default(0);
            $table->unsignedInteger('actions_posees')->default(0);
            $table->string('statut')->default('ok');
            $table->text('message')->nullable();

            $table->index(['automation_rule_id', 'demarre_le']);
        });

        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entite_type');
            $table->unsignedBigInteger('entite_id');
            $table->string('action_cle');
            $table->json('parametres')->nullable();
            $table->string('mode');
            $table->string('resultat');
            $table->foreignId('decide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->text('motif')->nullable();
            $table->unsignedInteger('etape')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('pose_le');

            $table->index(
                ['automation_rule_id', 'entite_type', 'entite_id', 'pose_le'],
                'automation_actions_registre_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
    }
};
```

- [ ] **Étape 4 : écrire les trois modèles**

`app/Models/AutomationRule.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une regle d'automatisation : un declencheur, des conditions, des actions. */
class AutomationRule extends Model
{
    public const ETAT_BROUILLON = 'brouillon';

    public const ETAT_OBSERVATION = 'observation';

    public const ETAT_ARMEE = 'armee';

    public const ETAT_SUSPENDUE = 'suspendue';

    public const ETAT_DESACTIVEE = 'desactivee';

    protected $fillable = [
        'nom', 'description', 'entite', 'declencheur', 'cadence', 'conditions', 'actions',
        'politique_reprise', 'etat', 'quota_par_passage', 'plafond_journalier',
        'plafonds_consecutifs', 'echecs_consecutifs', 'dernier_passage_le', 'cree_par',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'quota_par_passage' => 'integer',
        'plafond_journalier' => 'integer',
        'plafonds_consecutifs' => 'integer',
        'echecs_consecutifs' => 'integer',
        'dernier_passage_le' => 'datetime',
    ];

    /** @return HasMany<AutomationRun, $this> */
    public function passages(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /** @return HasMany<AutomationAction, $this> */
    public function actionsPosees(): HasMany
    {
        return $this->hasMany(AutomationAction::class);
    }
}
```

`app/Models/AutomationRun.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un passage d'une regle : ce qu'elle a vu, ce qu'elle a pose, pourquoi elle s'est arretee. */
class AutomationRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'automation_rule_id', 'mode', 'demarre_le', 'termine_le',
        'entites_vues', 'actions_posees', 'statut', 'message',
    ];

    protected $casts = [
        'demarre_le' => 'datetime',
        'termine_le' => 'datetime',
        'entites_vues' => 'integer',
        'actions_posees' => 'integer',
    ];

    /** @return BelongsTo<AutomationRule, $this> */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
```

`app/Models/AutomationAction.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal d'audit, registre « deja agi » et file des propositions — le meme objet. */
class AutomationAction extends Model
{
    public $timestamps = false;

    public const RESULTAT_SIMULEE = 'simulee';

    public const RESULTAT_EXECUTEE = 'executee';

    public const RESULTAT_PROPOSEE = 'proposee';

    public const RESULTAT_VALIDEE = 'validee';

    public const RESULTAT_REFUSEE = 'refusee';

    public const RESULTAT_ECHOUEE = 'echouee';

    public const RESULTAT_EXPIREE = 'expiree';

    protected $fillable = [
        'automation_rule_id', 'automation_run_id', 'entite_type', 'entite_id', 'action_cle',
        'parametres', 'mode', 'resultat', 'decide_par', 'decide_le', 'motif', 'etape',
        'message', 'pose_le',
    ];

    protected $casts = [
        'parametres' => 'array',
        'entite_id' => 'integer',
        'etape' => 'integer',
        'decide_le' => 'datetime',
        'pose_le' => 'datetime',
    ];

    /** @return BelongsTo<AutomationRule, $this> */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
```

- [ ] **Étape 5 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/SocleTest.php`
Attendu : 4 tests verts.

- [ ] **Étape 6 : portails et commit**

```bash
./vendor/bin/pint app/Models database/migrations tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add database/migrations app/Models tests/Feature/Automation
git commit -m "feat(automation): le socle — regles, passages, journal"
```

---

### Tâche 2 : le contrat d'action, et les deux actions inoffensives

**Fichiers :**
- Créer : `app/Services/Automation/Contracts/Action.php`
- Créer : `app/Services/Automation/ActionResult.php`
- Créer : `app/Services/Automation/Actions/Journaliser.php`
- Créer : `app/Services/Automation/Actions/NotifierLesAdmins.php`
- Créer : `app/Notifications/Automation/RegleDeclencheeNotification.php`
- Créer : `app/Services/Automation/Registre/ActionRegistre.php`
- Créer : `tests/Feature/Automation/ActionsTest.php`

**Interfaces :**
- Produit :
  - `interface App\Services\Automation\Contracts\Action` — `cle(): string`,
    `libelle(): string`, `entitesSupportees(): array`, `champs(): array`,
    `toucheAuDomaine(): bool`, `executer(Model $entite, array $parametres): ActionResult`
  - `ActionResult::reussie(?string $message = null): self`, `ActionResult::echouee(string $message): self`,
    propriétés `readonly bool $reussie`, `readonly ?string $message`
  - `ActionRegistre::enregistrer(Action $a): void`, `trouver(string $cle): ?Action`,
    `toutes(): array<string, Action>`

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\Automation\RegleDeclencheeNotification;
use App\Services\Automation\Actions\Journaliser;
use App\Services\Automation\Actions\NotifierLesAdmins;
use App\Services\Automation\Registre\ActionRegistre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_journaliser_ecrit_dans_le_journal_d_activite(): void
    {
        $reservation = Booking::factory()->create();

        $resultat = (new Journaliser)->executer($reservation, ['message' => 'reperee']);

        $this->assertTrue($resultat->reussie);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.note']);
    }

    public function test_notifier_les_admins_les_previent_tous(): void
    {
        Notification::fake();

        User::factory()->admin()->count(2)->create(['is_active' => true]);
        $reservation = Booking::factory()->create();

        $resultat = (new NotifierLesAdmins)->executer($reservation, ['message' => 'a traiter']);

        $this->assertTrue($resultat->reussie);
        Notification::assertCount(2);
    }

    /** Sans destinataire, l'action ECHOUE au lieu de faire semblant d'avoir reussi. */
    public function test_notifier_sans_aucun_admin_actif_echoue(): void
    {
        Notification::fake();

        $reservation = Booking::factory()->create();

        $resultat = (new NotifierLesAdmins)->executer($reservation, ['message' => 'a traiter']);

        $this->assertFalse($resultat->reussie);
        Notification::assertNothingSent();
    }

    public function test_aucune_action_de_la_phase_1_n_ecrit_dans_le_domaine(): void
    {
        foreach (app(ActionRegistre::class)->toutes() as $action) {
            $this->assertFalse(
                $action->toucheAuDomaine(),
                "L'action {$action->cle()} ecrit dans le domaine : interdit en phase 1."
            );
        }
    }

    /** TEMOIN — le registre porte bien les deux actions. Sans lui, le test ci-dessus
     *  passerait au vert sur un registre vide. */
    public function test_temoin_le_registre_porte_les_deux_actions(): void
    {
        $cles = array_keys(app(ActionRegistre::class)->toutes());

        sort($cles);

        $this->assertSame(['journaliser', 'notifier.admins'], $cles);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Automation/ActionsTest.php`
Attendu : ÉCHEC — `Class "App\Services\Automation\Actions\Journaliser" not found`.

- [ ] **Étape 3 : écrire le contrat et le résultat**

`app/Services/Automation/Contracts/Action.php` :

```php
<?php

namespace App\Services\Automation\Contracts;

use App\Services\Automation\ActionResult;
use Illuminate\Database\Eloquent\Model;

/** Ce qu'une action doit savoir dire d'elle-meme, et savoir faire. */
interface Action
{
    public function cle(): string;

    public function libelle(): string;

    /** @return list<string> les cles d'entite que cette action accepte */
    public function entitesSupportees(): array;

    /** @return array<string, string> nom du parametre => type, pour construire le formulaire */
    public function champs(): array;

    /** Ecrit-elle dans le domaine metier ? Ne decide PAS de l'autonomie — voir la spec. */
    public function toucheAuDomaine(): bool;

    /** @param array<string, mixed> $parametres */
    public function executer(Model $entite, array $parametres): ActionResult;
}
```

`app/Services/Automation/ActionResult.php` :

```php
<?php

namespace App\Services\Automation;

/** Le resultat d'une action : reussie ou non, et de quoi le journaliser. */
final class ActionResult
{
    private function __construct(
        public readonly bool $reussie,
        public readonly ?string $message,
    ) {}

    public static function reussie(?string $message = null): self
    {
        return new self(true, $message);
    }

    public static function echouee(string $message): self
    {
        return new self(false, $message);
    }
}
```

- [ ] **Étape 4 : écrire les deux actions et la notification**

`app/Services/Automation/Actions/Journaliser.php` :

```php
<?php

namespace App\Services\Automation\Actions;

use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/** Ecrit une ligne au journal d'activite. N'ecrit rien dans le domaine. */
class Journaliser implements Action
{
    public function cle(): string
    {
        return 'journaliser';
    }

    public function libelle(): string
    {
        return 'Écrire au journal d’activité';
    }

    public function entitesSupportees(): array
    {
        return ['booking'];
    }

    public function champs(): array
    {
        return ['message' => 'texte'];
    }

    public function toucheAuDomaine(): bool
    {
        return false;
    }

    public function executer(Model $entite, array $parametres): ActionResult
    {
        ActivityLogger::log('automation.note', $entite, [
            'message' => (string) ($parametres['message'] ?? ''),
        ]);

        return ActionResult::reussie();
    }
}
```

`app/Notifications/Automation/RegleDeclencheeNotification.php` :

```php
<?php

namespace App\Notifications\Automation;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/** Ce qu'un administrateur recoit quand une regle le previent. */
class RegleDeclencheeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $message,
        protected Model $entite,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'automation',
            'message' => $this->message,
            'entite_type' => $this->entite->getMorphClass(),
            'entite_id' => $this->entite->getKey(),
        ];
    }
}
```

`app/Services/Automation/Actions/NotifierLesAdmins.php` :

```php
<?php

namespace App\Services\Automation\Actions;

use App\Models\User;
use App\Notifications\Automation\RegleDeclencheeNotification;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/** Previent les administrateurs actifs. N'ecrit rien dans le domaine. */
class NotifierLesAdmins implements Action
{
    public function cle(): string
    {
        return 'notifier.admins';
    }

    public function libelle(): string
    {
        return 'Notifier les administrateurs';
    }

    public function entitesSupportees(): array
    {
        return ['booking'];
    }

    public function champs(): array
    {
        return ['message' => 'texte'];
    }

    public function toucheAuDomaine(): bool
    {
        return false;
    }

    public function executer(Model $entite, array $parametres): ActionResult
    {
        $admins = User::query()->admins()->where('is_active', true)->get();

        // Sans destinataire, on ECHOUE : une action qui n'a prevenu personne n'a pas reussi.
        if ($admins->isEmpty()) {
            return ActionResult::echouee('Aucun administrateur actif à notifier.');
        }

        Notification::send($admins, new RegleDeclencheeNotification(
            (string) ($parametres['message'] ?? ''),
            $entite
        ));

        return ActionResult::reussie($admins->count().' administrateur(s) notifié(s).');
    }
}
```

- [ ] **Étape 5 : écrire le registre**

`app/Services/Automation/Registre/ActionRegistre.php` :

```php
<?php

namespace App\Services\Automation\Registre;

use App\Services\Automation\Contracts\Action;

/** Cle => action. Le vocabulaire des actions vit en code, jamais en base. */
class ActionRegistre
{
    /** @var array<string, Action> */
    protected array $actions = [];

    public function enregistrer(Action $action): void
    {
        $this->actions[$action->cle()] = $action;
    }

    public function trouver(string $cle): ?Action
    {
        return $this->actions[$cle] ?? null;
    }

    /** @return array<string, Action> */
    public function toutes(): array
    {
        return $this->actions;
    }
}
```

- [ ] **Étape 6 : enregistrer le registre dans un fournisseur**

Créer `app/Providers/AutomationServiceProvider.php` :

```php
<?php

namespace App\Providers;

use App\Services\Automation\Actions\Journaliser;
use App\Services\Automation\Actions\NotifierLesAdmins;
use App\Services\Automation\Registre\ActionRegistre;
use Illuminate\Support\ServiceProvider;

/** Le vocabulaire du moteur : ce qui existe, et rien d'autre. */
class AutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActionRegistre::class, function (): ActionRegistre {
            $registre = new ActionRegistre;
            $registre->enregistrer(new Journaliser);
            $registre->enregistrer(new NotifierLesAdmins);

            return $registre;
        });
    }
}
```

Puis l'enregistrer dans **`config/app.php`** — ce dépôt n'a pas de `bootstrap/providers.php`. Le
fichier importe chaque fournisseur en tête (`use App\Providers\…;`) et les liste dans son tableau
`providers`. Ajouter les deux : l'import, et `AutomationServiceProvider::class` dans la liste.

- [ ] **Étape 7 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/ActionsTest.php`
Attendu : 5 tests verts.

- [ ] **Étape 8 : portails et commit**

```bash
./vendor/bin/pint app/Services/Automation app/Notifications/Automation app/Providers tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Automation app/Notifications/Automation app/Providers config/app.php tests/Feature/Automation
git commit -m "feat(automation): deux actions qui ne touchent a rien, et leur registre"
```

---

### Tâche 3 : le registre d'entités et le descripteur des réservations

**Fichiers :**
- Créer : `app/Services/Automation/Registre/EntiteRegistre.php`
- Créer : `app/Services/Automation/Descripteurs/BookingDescriptor.php`
- Modifier : `app/Providers/AutomationServiceProvider.php`
- Créer : `tests/Feature/Automation/BookingDescriptorTest.php`

**Interfaces :**
- Consomme : `App\Services\Conditions\{EntityDescriptor, FieldBinding, RuleTreeEvaluator}` (lot 1).
  `FieldBinding::colonne(string)`, `RuleTreeEvaluator::OPERATEURS_CONNUS`.
- Produit : `EntiteRegistre::enregistrer(string $cle, string $classe): void`,
  `descripteur(string $cle): ?EntityDescriptor`, `cles(): list<string>`.
  `BookingDescriptor` sous la clé `booking`.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\Booking;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDescriptorTest extends TestCase
{
    use RefreshDatabase;

    private function compter(array $noeud): int
    {
        $entite = app(EntiteRegistre::class)->descripteur('booking');
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);

        return $requete->count();
    }

    public function test_le_registre_sert_le_descripteur_des_reservations(): void
    {
        $this->assertContains('booking', app(EntiteRegistre::class)->cles());
        $this->assertNotNull(app(EntiteRegistre::class)->descripteur('booking'));
    }

    public function test_une_cle_inconnue_ne_rend_rien(): void
    {
        $this->assertNull(app(EntiteRegistre::class)->descripteur('licorne'));
    }

    public function test_le_statut_filtre(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        Booking::factory()->create(['status' => 'confirme']);

        $this->assertSame(1, $this->compter(['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente']));
    }

    public function test_la_ville_filtre(): void
    {
        Booking::factory()->create(['ville' => 'Ixelles']);
        Booking::factory()->create(['ville' => 'Anvers']);

        $this->assertSame(1, $this->compter(['field' => 'ville', 'op' => 'eq', 'value' => 'Ixelles']));
    }

    /**
     * CHAQUE CHAMP DECLARE DOIT S'EXECUTER — le garde-fou du lot 1, applique ici.
     * Un champ pointant une colonne absente ne casserait qu'en production.
     */
    public function test_chaque_champ_declare_produit_une_requete_qui_s_execute(): void
    {
        $echecs = [];

        foreach (array_keys(app(EntiteRegistre::class)->descripteur('booking')->fields()) as $champ) {
            $entite = app(EntiteRegistre::class)->descripteur('booking');
            $requete = $entite->baseQuery();

            try {
                app(RuleTreeEvaluator::class)->apply(
                    $requete,
                    ['and' => [
                        ['field' => $champ, 'op' => 'is_not_null', 'value' => null],
                        ['field' => $champ, 'op' => 'is_not_null', 'value' => null],
                    ]],
                    $entite
                );
                $requete->count();
            } catch (\Throwable $e) {
                $echecs[] = $champ.' : '.substr($e->getMessage(), 0, 90);
            }
        }

        $this->assertSame([], $echecs, "Ces champs ne s'executent pas :\n".implode("\n", $echecs));
    }

    /** TEMOIN — le descripteur declare bien des champs. */
    public function test_temoin_le_descripteur_declare_des_champs(): void
    {
        $this->assertNotEmpty(app(EntiteRegistre::class)->descripteur('booking')->fields());
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Automation/BookingDescriptorTest.php`
Attendu : ÉCHEC — `Class "App\Services\Automation\Registre\EntiteRegistre" not found`.

- [ ] **Étape 3 : écrire le registre d'entités**

```php
<?php

namespace App\Services\Automation\Registre;

use App\Services\Conditions\EntityDescriptor;

/** Cle => descripteur d'entite. Une instance NEUVE a chaque resolution. */
class EntiteRegistre
{
    /** @var array<string, class-string<EntityDescriptor>> */
    protected array $entites = [];

    /** @param class-string<EntityDescriptor> $classe */
    public function enregistrer(string $cle, string $classe): void
    {
        $this->entites[$cle] = $classe;
    }

    public function descripteur(string $cle): ?EntityDescriptor
    {
        if (! isset($this->entites[$cle])) {
            return null;
        }

        return app($this->entites[$cle]);
    }

    /** @return list<string> */
    public function cles(): array
    {
        return array_keys($this->entites);
    }
}
```

- [ ] **Étape 4 : écrire le descripteur des réservations**

```php
<?php

namespace App\Services\Automation\Descripteurs;

use App\Models\Booking;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les reservations, vues par une regle d'automatisation.
 *
 * « Qui intervient » n'est PAS expose : la reponse fait autorite dans `missions`, pas dans
 * `bookings.employe_id`. Un champ nomme ainsi tromperait. Il viendra a une phase ulterieure.
 */
class BookingDescriptor implements EntityDescriptor
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
            'statut' => FieldBinding::colonne('bookings.status'),
            'priorite' => FieldBinding::colonne('bookings.priorite'),
            'date' => FieldBinding::colonne('bookings.date'),
            'ville' => FieldBinding::colonne('bookings.ville'),
            'code_postal' => FieldBinding::colonne('bookings.code_postal'),
            'zone_id' => FieldBinding::colonne('bookings.service_zone_id'),
            'prix_estime' => FieldBinding::colonne('bookings.estimated_price'),
            'cree_le' => FieldBinding::colonne('bookings.created_at'),
        ];
    }

    /** @return list<string> */
    public function operators(): array
    {
        return RuleTreeEvaluator::OPERATEURS_CONNUS;
    }

    /** L'invariance des generiques Eloquent interdit `Booking::query()` ici — voir le lot 1. */
    protected function modele(): Model
    {
        return new Booking;
    }
}
```

- [ ] **Étape 5 : enregistrer l'entité dans le fournisseur**

Dans `app/Providers/AutomationServiceProvider.php`, méthode `register()`, ajouter :

```php
        $this->app->singleton(EntiteRegistre::class, function (): EntiteRegistre {
            $registre = new EntiteRegistre;
            $registre->enregistrer('booking', BookingDescriptor::class);

            return $registre;
        });
```

avec les `use` correspondants.

- [ ] **Étape 6 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/BookingDescriptorTest.php`
Attendu : 6 tests verts.

- [ ] **Étape 7 : portails et commit**

```bash
./vendor/bin/pint app/Services/Automation app/Providers tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Automation app/Providers tests/Feature/Automation
git commit -m "feat(automation): les reservations, vues par une regle"
```

---

### Tâche 4 : `RuleRunner` — évaluer, poser, journaliser

**Fichiers :**
- Créer : `app/Services/Automation/RuleRunner.php`
- Créer : `tests/Feature/Automation/RuleRunnerTest.php`

**Interfaces :**
- Consomme : `EntiteRegistre::descripteur()`, `ActionRegistre::trouver()`,
  `RuleTreeEvaluator::apply()`, les modèles de la tâche 1.
- Produit : `RuleRunner::executer(AutomationRule $regle, ?array $identifiants = null): AutomationRun`

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\User;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RuleRunnerTest extends TestCase
{
    use RefreshDatabase;

    private function regle(string $etat, array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => $etat,
        ], $attributs));
    }

    public function test_en_observation_la_regle_journalise_et_n_agit_pas(): void
    {
        Booking::factory()->count(2)->create(['status' => 'en_attente']);
        Booking::factory()->create(['status' => 'confirme']);   // TEMOIN : hors conditions

        $passage = app(RuleRunner::class)->executer($this->regle(AutomationRule::ETAT_OBSERVATION));

        $this->assertSame('observation', $passage->mode);
        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame(2, AutomationAction::where('resultat', 'simulee')->count());
        // L'observation n'ecrit RIEN dans le journal d'activite.
        $this->assertDatabaseMissing('activity_logs', ['action' => 'automation.note']);
    }

    /** TEMOIN de l'observation — armee, la meme regle ecrit vraiment. */
    public function test_temoin_armee_la_meme_regle_agit(): void
    {
        Booking::factory()->count(2)->create(['status' => 'en_attente']);

        $passage = app(RuleRunner::class)->executer($this->regle(AutomationRule::ETAT_ARMEE));

        $this->assertSame('armee', $passage->mode);
        $this->assertSame(2, AutomationAction::where('resultat', 'executee')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.note']);
    }

    public function test_une_action_qui_echoue_n_emporte_pas_le_passage(): void
    {
        Notification::fake();

        // Aucun administrateur : `notifier.admins` echoue pour chaque entite.
        Booking::factory()->count(3)->create(['status' => 'en_attente']);

        $regle = $this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [['cle' => 'notifier.admins', 'parametres' => ['message' => 'x']]],
        ]);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(3, $passage->entites_vues);
        $this->assertSame(3, AutomationAction::where('resultat', 'echouee')->count());
    }

    public function test_une_action_inconnue_est_journalisee_en_echec(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->regle(AutomationRule::ETAT_ARMEE, [
            'actions' => [['cle' => 'action.qui.n.existe.pas', 'parametres' => []]],
        ]);

        app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, AutomationAction::where('resultat', 'echouee')->count());
    }

    public function test_restreindre_a_des_identifiants_limite_le_balayage(): void
    {
        $vise = Booking::factory()->create(['status' => 'en_attente']);
        Booking::factory()->create(['status' => 'en_attente']);   // TEMOIN : non vise

        $passage = app(RuleRunner::class)->executer(
            $this->regle(AutomationRule::ETAT_ARMEE),
            [$vise->id]
        );

        $this->assertSame(1, $passage->entites_vues);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Automation/RuleRunnerTest.php`
Attendu : ÉCHEC — `Class "App\Services\Automation\RuleRunner" not found`.

- [ ] **Étape 3 : écrire le runner**

```php
<?php

namespace App\Services\Automation;

use App\Models\AutomationAction as LigneDeJournal;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Evalue une regle en UNE requete, pose ses actions, et journalise tout ce qu'il fait. */
class RuleRunner
{
    public function __construct(
        protected EntiteRegistre $entites,
        protected ActionRegistre $actions,
        protected RuleTreeEvaluator $evaluateur,
    ) {}

    /** @param list<int>|null $identifiants restreint le balayage, pour le drain d'evenements */
    public function executer(AutomationRule $regle, ?array $identifiants = null): AutomationRun
    {
        $observation = $regle->etat === AutomationRule::ETAT_OBSERVATION;

        $passage = AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => $observation ? 'observation' : 'armee',
            'demarre_le' => now(),
        ]);

        $entite = $this->entites->descripteur($regle->entite);

        if ($entite === null) {
            $passage->forceFill([
                'statut' => 'echec',
                'message' => "Entité inconnue : {$regle->entite}",
                'termine_le' => now(),
            ])->save();

            return $passage;
        }

        $requete = $entite->baseQuery();
        $this->evaluateur->apply($requete, $regle->conditions ?? [], $entite);

        if ($identifiants !== null) {
            $requete->whereKey($identifiants);
        }

        $lignes = $requete->get();
        $posees = 0;

        foreach ($lignes as $ligne) {
            foreach (($regle->actions ?? []) as $demande) {
                $this->poser($regle, $passage, $ligne, (array) $demande, $observation);
                $posees++;
            }
        }

        $passage->forceFill([
            'entites_vues' => $lignes->count(),
            'actions_posees' => $posees,
            'termine_le' => now(),
        ])->save();

        $regle->forceFill(['dernier_passage_le' => now()])->save();

        return $passage;
    }

    /** @param array<string, mixed> $demande */
    protected function poser(
        AutomationRule $regle,
        AutomationRun $passage,
        Model $entite,
        array $demande,
        bool $observation,
    ): void {
        $cle = (string) ($demande['cle'] ?? '');
        $parametres = (array) ($demande['parametres'] ?? []);

        $ligne = [
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => $regle->entite,
            'entite_id' => (int) $entite->getKey(),
            'action_cle' => $cle,
            'parametres' => $parametres,
            'mode' => $observation ? 'observation' : 'armee',
            'pose_le' => now(),
        ];

        $action = $this->actions->trouver($cle);

        if ($action === null) {
            LigneDeJournal::create($ligne + [
                'resultat' => LigneDeJournal::RESULTAT_ECHOUEE,
                'message' => "Action inconnue : {$cle}",
            ]);

            return;
        }

        // EN OBSERVATION, ON N'APPELLE PAS L'ACTION. On ecrit ce qu'on AURAIT fait.
        if ($observation) {
            LigneDeJournal::create($ligne + ['resultat' => LigneDeJournal::RESULTAT_SIMULEE]);

            return;
        }

        try {
            $resultat = $action->executer($entite, $parametres);
        } catch (Throwable $e) {
            $resultat = ActionResult::echouee(substr($e->getMessage(), 0, 250));
        }

        LigneDeJournal::create($ligne + [
            'resultat' => $resultat->reussie
                ? LigneDeJournal::RESULTAT_EXECUTEE
                : LigneDeJournal::RESULTAT_ECHOUEE,
            'message' => $resultat->message,
        ]);
    }
}
```

- [ ] **Étape 4 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/RuleRunnerTest.php`
Attendu : 5 tests verts.

- [ ] **Étape 5 : portails et commit**

```bash
./vendor/bin/pint app/Services/Automation tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Automation tests/Feature/Automation
git commit -m "feat(automation): le runner evalue en une requete, pose, et journalise"
```

---

### Tâche 5 : l'idempotence — les trois politiques de reprise

**Fichiers :**
- Modifier : `app/Services/Automation/RuleRunner.php`
- Créer : `tests/Feature/Automation/IdempotenceTest.php`

**Interfaces :**
- Consomme : `RuleRunner::executer()` (tâche 4).
- Produit : rien de neuf ; `executer()` exclut désormais les entités déjà traitées.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotenceTest extends TestCase
{
    use RefreshDatabase;

    private function regle(string $politique): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_ARMEE,
            'politique_reprise' => $politique,
        ]);
    }

    public function test_une_fois_n_agit_qu_une_seule_fois_par_entite(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle('une_fois');

        app(RuleRunner::class)->executer($regle);
        $second = app(RuleRunner::class)->executer($regle);

        $this->assertSame(0, $second->entites_vues);
        $this->assertSame(1, AutomationAction::count());
    }

    public function test_chaque_passage_agit_a_chaque_fois(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle('chaque_passage');

        app(RuleRunner::class)->executer($regle);
        $second = app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $second->entites_vues);
        $this->assertSame(2, AutomationAction::count());
    }

    public function test_une_fois_par_jour_reagit_le_lendemain(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle('une_fois_par_jour');

        app(RuleRunner::class)->executer($regle);

        $memeJour = app(RuleRunner::class)->executer($regle);
        $this->assertSame(0, $memeJour->entites_vues);

        $this->travel(25)->hours();

        $lendemain = app(RuleRunner::class)->executer($regle);
        $this->assertSame(1, $lendemain->entites_vues);
    }

    /**
     * TEMOIN — l'exclusion vise l'ENTITE, pas la regle entiere. Une reservation neuve
     * est vue au passage suivant, meme en politique `une_fois`.
     */
    public function test_temoin_une_entite_neuve_est_vue_au_passage_suivant(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle('une_fois');

        app(RuleRunner::class)->executer($regle);

        Booking::factory()->create(['status' => 'en_attente']);

        $this->assertSame(1, app(RuleRunner::class)->executer($regle)->entites_vues);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Automation/IdempotenceTest.php`
Attendu : ÉCHEC — le second passage voit encore l'entité (`1` au lieu de `0`).

- [ ] **Étape 3 : exclure le déjà-agi dans le runner**

Dans `RuleRunner::executer`, **après** l'application de `whereKey($identifiants)` et **avant**
`$requete->get()`, insérer :

```php
        $this->exclureLeDejaAgi($requete, $regle);
```

Et ajouter la méthode :

```php
    /**
     * @param  Builder<Model>  $requete
     *
     * La politique porte TOUJOURS sur l'entite : « une fois » veut dire une fois par entite.
     */
    protected function exclureLeDejaAgi(Builder $requete, AutomationRule $regle): void
    {
        if ($regle->politique_reprise === 'chaque_passage') {
            return;
        }

        $deja = LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('entite_type', $regle->entite)
            ->whereNotIn('resultat', [
                LigneDeJournal::RESULTAT_REFUSEE,
                LigneDeJournal::RESULTAT_EXPIREE,
            ])
            ->when(
                $regle->politique_reprise === 'une_fois_par_jour',
                fn (Builder $q) => $q->where('pose_le', '>=', now()->subDay())
            )
            ->select('entite_id');

        $requete->whereNotIn($requete->getModel()->getQualifiedKeyName(), $deja);
    }
```

Une action **refusée** ou **expirée** ne compte pas comme « déjà agi » : la règle doit pouvoir
repasser sur cette entité.

- [ ] **Étape 4 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/IdempotenceTest.php`
Attendu : 4 tests verts.

- [ ] **Étape 5 : vérifier que la tâche 4 n'a pas régressé**

Lancer : `php artisan test tests/Feature/Automation/`
Attendu : tout vert.

- [ ] **Étape 6 : portails et commit**

```bash
./vendor/bin/pint app/Services/Automation tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Automation tests/Feature/Automation
git commit -m "feat(automation): une regle n'agit pas cent fois sur la meme entite"
```

---

### Tâche 6 : le quota bride, l'emballement suspend

**Fichiers :**
- Modifier : `app/Services/Automation/RuleRunner.php`
- Créer : `tests/Feature/Automation/QuotaTest.php`

**Interfaces :**
- Produit : `AutomationRun::$statut` vaut `plafond_atteint` quand le quota a bridé ;
  `AutomationRule::$plafonds_consecutifs` compte ; à **3**, `etat` devient `suspendue`.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    use RefreshDatabase;

    private function regle(int $quota): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_ARMEE,
            'politique_reprise' => 'chaque_passage',
            'quota_par_passage' => $quota,
        ]);
    }

    public function test_le_quota_bride_le_passage_sans_suspendre_la_regle(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame('plafond_atteint', $passage->statut);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_trois_plafonds_consecutifs_suspendent_la_regle(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);

        app(RuleRunner::class)->executer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);
    }

    /**
     * TEMOIN — un passage SOUS le plafond remet le compteur a zero. Sans lui, une regle
     * saine finirait suspendue au bout de trois passages charges espaces dans le temps.
     */
    public function test_temoin_un_passage_sous_le_plafond_remet_le_compteur_a_zero(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);

        app(RuleRunner::class)->executer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        $this->assertSame(2, $regle->fresh()->plafonds_consecutifs);

        Booking::query()->update(['status' => 'confirme']);   // plus rien a traiter

        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $regle->fresh()->plafonds_consecutifs);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_le_plafond_journalier_arrete_la_regle(): void
    {
        Booking::factory()->count(4)->create(['status' => 'en_attente']);

        $regle = $this->regle(10);
        $regle->forceFill(['plafond_journalier' => 2])->save();

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, AutomationAction::count());
        $this->assertSame('plafond_atteint', $passage->statut);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Automation/QuotaTest.php`
Attendu : ÉCHEC — le premier passage voit 5 entités au lieu de 2.

- [ ] **Étape 3 : brider dans le runner**

Dans `RuleRunner::executer`, remplacer `$lignes = $requete->get();` par :

```php
        // LE +1 EST LE SIGNAL : sans lui, « exactement le quota » et « mille » sont
        // indiscernables, et l'emballement ne se voit jamais.
        $restantAujourdhui = max(0, $regle->plafond_journalier - $this->poseesAujourdhui($regle));
        $quota = min($regle->quota_par_passage, $restantAujourdhui);

        $lignes = $requete->limit($quota + 1)->get();
        $bride = $lignes->count() > $quota;
        $lignes = $lignes->take($quota);
```

Après la boucle, remplacer la mise à jour du passage par :

```php
        $passage->forceFill([
            'entites_vues' => $lignes->count(),
            'actions_posees' => $posees,
            'statut' => $bride ? 'plafond_atteint' : 'ok',
            'termine_le' => now(),
        ])->save();

        $this->comptabiliserLePlafond($regle, $bride);
```

Et ajouter les deux méthodes :

```php
    protected function poseesAujourdhui(AutomationRule $regle): int
    {
        return LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('pose_le', '>=', now()->startOfDay())
            ->count();
    }

    /** Le quota BRIDE. C'est l'emballement — trois plafonds d'affilee — qui suspend. */
    protected function comptabiliserLePlafond(AutomationRule $regle, bool $bride): void
    {
        $consecutifs = $bride ? $regle->plafonds_consecutifs + 1 : 0;

        $regle->forceFill([
            'plafonds_consecutifs' => $consecutifs,
            'dernier_passage_le' => now(),
            'etat' => $consecutifs >= 3 ? AutomationRule::ETAT_SUSPENDUE : $regle->etat,
        ])->save();
    }
```

Retirer l'ancienne ligne `$regle->forceFill(['dernier_passage_le' => now()])->save();` — elle est
désormais faite par `comptabiliserLePlafond`.

- [ ] **Étape 4 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/QuotaTest.php`
Attendu : 4 tests verts.

- [ ] **Étape 5 : vérifier l'absence de régression**

Lancer : `php artisan test tests/Feature/Automation/`
Attendu : tout vert.

- [ ] **Étape 6 : portails et commit**

```bash
./vendor/bin/pint app/Services/Automation tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Automation tests/Feature/Automation
git commit -m "feat(automation): le quota bride, l'emballement suspend"
```

---

### Tâche 7 : la machine à états — armer exige un journal d'observation

**Fichiers :**
- Modifier : `app/Models/AutomationRule.php`
- Créer : `app/Services/Automation/EtatDeRegle.php`
- Créer : `tests/Feature/Automation/MachineAEtatsTest.php`

**Interfaces :**
- Produit : `EtatDeRegle::observer(AutomationRule): void`, `armer(AutomationRule): void`,
  `suspendre(AutomationRule, string $motif): void`, `desactiver(AutomationRule): void`.
  `armer()` lève `App\Services\Automation\ArmementRefuse` si le journal d'observation est vide.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\ArmementRefuse;
use App\Services\Automation\EtatDeRegle;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineAEtatsTest extends TestCase
{
    use RefreshDatabase;

    private function regle(): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
        ]);
    }

    public function test_armer_une_regle_au_journal_vide_est_refuse(): void
    {
        $regle = $this->regle();
        app(EtatDeRegle::class)->observer($regle);

        $this->expectException(ArmementRefuse::class);

        app(EtatDeRegle::class)->armer($regle);
    }

    /**
     * TEMOIN — la meme regle s'arme des qu'elle a observe quelque chose. Sans lui, le refus
     * ci-dessus passerait au vert sur un armement casse pour tout le monde.
     */
    public function test_temoin_apres_un_passage_d_observation_l_armement_passe(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);

        $regle = $this->regle();
        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh());

        app(EtatDeRegle::class)->armer($regle->fresh());

        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_un_passage_d_observation_sans_correspondance_ne_suffit_pas(): void
    {
        // Aucune reservation : le passage n'ecrit aucune ligne, donc le journal reste vide.
        $regle = $this->regle();
        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh());

        $this->expectException(ArmementRefuse::class);

        app(EtatDeRegle::class)->armer($regle->fresh());
    }

    public function test_suspendre_et_desactiver_posent_l_etat(): void
    {
        $regle = $this->regle();

        app(EtatDeRegle::class)->suspendre($regle, 'emballement');
        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);

        app(EtatDeRegle::class)->desactiver($regle->fresh());
        $this->assertSame(AutomationRule::ETAT_DESACTIVEE, $regle->fresh()->etat);
    }

    public function test_chaque_transition_est_journalisee(): void
    {
        app(EtatDeRegle::class)->observer($this->regle());

        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.regle_observation']);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Automation/MachineAEtatsTest.php`
Attendu : ÉCHEC — `Class "App\Services\Automation\EtatDeRegle" not found`.

- [ ] **Étape 3 : écrire l'exception et la machine à états**

`app/Services/Automation/ArmementRefuse.php` :

```php
<?php

namespace App\Services\Automation;

use RuntimeException;

/** Une regle qui n'a jamais rien observe ne peut pas etre armee. */
class ArmementRefuse extends RuntimeException {}
```

`app/Services/Automation/EtatDeRegle.php` :

```php
<?php

namespace App\Services\Automation;

use App\Models\AutomationAction as LigneDeJournal;
use App\Models\AutomationRule;
use App\Support\ActivityLogger;

/** Les transitions d'une regle, et la seule qui refuse. */
class EtatDeRegle
{
    public function observer(AutomationRule $regle): void
    {
        $this->poser($regle, AutomationRule::ETAT_OBSERVATION, 'observation');
    }

    /** @throws ArmementRefuse */
    public function armer(AutomationRule $regle): void
    {
        $observees = LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('mode', 'observation')
            ->count();

        if ($observees === 0) {
            throw new ArmementRefuse(
                'Cette règle n’a rien observé : il n’y a rien à lire avant de l’armer.'
            );
        }

        $this->poser($regle, AutomationRule::ETAT_ARMEE, 'armee', ['observees' => $observees]);
    }

    public function suspendre(AutomationRule $regle, string $motif): void
    {
        $this->poser($regle, AutomationRule::ETAT_SUSPENDUE, 'suspendue', ['motif' => $motif]);
    }

    public function desactiver(AutomationRule $regle): void
    {
        $this->poser($regle, AutomationRule::ETAT_DESACTIVEE, 'desactivee');
    }

    /** @param array<string, mixed> $meta */
    protected function poser(AutomationRule $regle, string $etat, string $suffixe, array $meta = []): void
    {
        $regle->forceFill(['etat' => $etat, 'plafonds_consecutifs' => 0])->save();

        ActivityLogger::log('automation.regle_'.$suffixe, $regle, $meta);
    }
}
```

- [ ] **Étape 4 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/MachineAEtatsTest.php`
Attendu : 5 tests verts.

- [ ] **Étape 5 : portails et commit**

```bash
./vendor/bin/pint app/Services/Automation tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Automation tests/Feature/Automation
git commit -m "feat(automation): on n'arme pas une regle qui n'a rien observe"
```

---

### Tâche 8 : la commande, l'interrupteur et l'ordonnanceur

**Fichiers :**
- Créer : `app/Console/Commands/ExecuterLAutomatisation.php`
- Modifier : `config/features.php`
- Modifier : `app/Console/Kernel.php`
- Créer : `tests/Feature/Automation/CommandeTest.php`

**Interfaces :**
- Consomme : `RuleRunner::executer()`, `FeatureFlagService::isEnabled('automation')`.
- Produit : la commande `automation:executer`.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandeTest extends TestCase
{
    use RefreshDatabase;

    private function regle(string $etat, string $cadence = 'chaque_minute'): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => $cadence,
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => $etat,
        ]);
    }

    public function test_l_interrupteur_ferme_coupe_tout(): void
    {
        config()->set('features.automation', false);

        Booking::factory()->create(['status' => 'en_attente']);
        $this->regle(AutomationRule::ETAT_ARMEE);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::count());
    }

    /** TEMOIN — interrupteur ouvert, la meme regle agit. */
    public function test_temoin_l_interrupteur_ouvert_laisse_passer(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        $this->regle(AutomationRule::ETAT_ARMEE);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::count());
    }

    public function test_une_regle_en_brouillon_ou_desactivee_ne_tourne_pas(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        $this->regle(AutomationRule::ETAT_BROUILLON);
        $this->regle(AutomationRule::ETAT_DESACTIVEE);
        $this->regle(AutomationRule::ETAT_SUSPENDUE);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::count());
    }

    public function test_une_regle_en_observation_tourne_et_journalise(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        $this->regle(AutomationRule::ETAT_OBSERVATION);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::where('resultat', 'simulee')->count());
    }

    public function test_une_cadence_non_due_est_sautee(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle(AutomationRule::ETAT_ARMEE, 'jour');
        $regle->forceFill(['dernier_passage_le' => now()->subHour()])->save();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::count());
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Automation/CommandeTest.php`
Attendu : ÉCHEC — `The command "automation:executer" does not exist.`

- [ ] **Étape 3 : déclarer le drapeau**

Dans `config/features.php`, ajouter à la liste des drapeaux :

```php
    // L'interrupteur general du moteur d'automatisation. Ferme par defaut.
    'automation' => false,
```

**Un drapeau absent de cette liste rend `false`** : sans cette clé, l'interrupteur serait fermé
sans que personne sache pourquoi.

- [ ] **Étape 4 : écrire la commande**

```php
<?php

namespace App\Console\Commands;

use App\Models\AutomationRule;
use App\Services\Automation\RuleRunner;
use App\Services\FeatureFlag\FeatureFlagService;
use Illuminate\Console\Command;

/** Le seul vehicule d'execution du moteur : jamais dans la requete d'un utilisateur. */
class ExecuterLAutomatisation extends Command
{
    protected $signature = 'automation:executer';

    protected $description = 'Exécute les règles d’automatisation dont le tour est venu.';

    /** Les etats qui tournent. Le brouillon, la suspendue et la desactivee ne tournent pas. */
    private const ETATS_ACTIFS = [
        AutomationRule::ETAT_OBSERVATION,
        AutomationRule::ETAT_ARMEE,
    ];

    private const CADENCES = [
        'chaque_minute' => 1,
        'quart_heure' => 15,
        'heure' => 60,
        'jour' => 1440,
    ];

    public function handle(RuleRunner $runner, FeatureFlagService $drapeaux): int
    {
        if (! $drapeaux->isEnabled('automation')) {
            $this->info('Moteur d’automatisation coupé (drapeau « automation »).');

            return self::SUCCESS;
        }

        $regles = AutomationRule::query()
            ->whereIn('etat', self::ETATS_ACTIFS)
            ->where('declencheur', 'cadence')
            ->get()
            ->filter(fn (AutomationRule $regle): bool => $this->estDue($regle));

        foreach ($regles as $regle) {
            $passage = $runner->executer($regle);

            $this->line(sprintf(
                '%s : %d entité(s), %d action(s), %s',
                $regle->nom,
                $passage->entites_vues,
                $passage->actions_posees,
                $passage->statut
            ));
        }

        return self::SUCCESS;
    }

    protected function estDue(AutomationRule $regle): bool
    {
        if ($regle->dernier_passage_le === null) {
            return true;
        }

        $minutes = self::CADENCES[$regle->cadence] ?? 15;

        return $regle->dernier_passage_le->addMinutes($minutes)->isPast();
    }
}
```

- [ ] **Étape 5 : brancher l'ordonnanceur**

Dans `app/Console/Kernel.php`, méthode `schedule()`, ajouter à la suite des autres :

```php
        $schedule->command('automation:executer')->everyMinute()->withoutOverlapping();
```

- [ ] **Étape 6 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/CommandeTest.php`
Attendu : 5 tests verts.

- [ ] **Étape 7 : portails et commit**

```bash
./vendor/bin/pint app/Console config tests/Feature/Automation
./vendor/bin/phpstan analyse --no-progress
git add app/Console config/features.php tests/Feature/Automation
git commit -m "feat(automation): la commande, l'interrupteur, et l'ordonnanceur"
```

---

### Tâche 9 : le garde-fou des registres, et la vérification d'ensemble

**Fichiers :**
- Créer : `tests/Feature/Automation/RegistresTest.php`

**Interfaces :**
- Consomme : `ActionRegistre::toutes()`, `EntiteRegistre::cles()`.
- Produit : rien. Ce test couvrira gratuitement les actions et entités des phases suivantes.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Automation;

use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ce qui doit rester vrai de TOUTE action et de TOUTE entite, y compris celles a venir. */
class RegistresTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_action_declare_une_cle_un_libelle_et_des_entites(): void
    {
        $ecarts = [];

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            if ($action->cle() !== $cle) {
                $ecarts[] = "{$cle} : la cle du registre ne correspond pas a cle()";
            }
            if (trim($action->libelle()) === '') {
                $ecarts[] = "{$cle} : libelle vide";
            }
            if ($action->entitesSupportees() === []) {
                $ecarts[] = "{$cle} : aucune entite supportee";
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    public function test_chaque_action_ne_supporte_que_des_entites_enregistrees(): void
    {
        $connues = app(EntiteRegistre::class)->cles();
        $ecarts = [];

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            foreach ($action->entitesSupportees() as $entite) {
                if (! in_array($entite, $connues, true)) {
                    $ecarts[] = "{$cle} : entite inconnue « {$entite} »";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    public function test_chaque_entite_n_expose_que_des_operateurs_connus(): void
    {
        $ecarts = [];

        foreach (app(EntiteRegistre::class)->cles() as $cle) {
            foreach (app(EntiteRegistre::class)->descripteur($cle)->operators() as $op) {
                if (! in_array($op, RuleTreeEvaluator::OPERATEURS_CONNUS, true)) {
                    $ecarts[] = "{$cle} : operateur inconnu « {$op} »";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /** TEMOIN — les deux registres ne sont pas vides. Sans lui, les trois tests ci-dessus
     *  passeraient au vert sur des registres sans rien dedans. */
    public function test_temoin_les_deux_registres_portent_quelque_chose(): void
    {
        $this->assertNotEmpty(app(ActionRegistre::class)->toutes());
        $this->assertNotEmpty(app(EntiteRegistre::class)->cles());
    }
}
```

- [ ] **Étape 2 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Automation/RegistresTest.php`
Attendu : 4 tests verts. Un échec nomme l'action ou l'entité fautive.

- [ ] **Étape 3 : la suite complète**

```bash
git status --short          # l'arbre doit etre propre AVANT de lancer
composer test:parallele
```

Attendu : aucun échec. **Ne modifier aucun fichier pendant que la suite tourne.**

- [ ] **Étape 4 : les deux portails**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
```

- [ ] **Étape 5 : commit**

```bash
git add tests/Feature/Automation/RegistresTest.php
git commit -m "test(automation): une action ou une entite mal declaree tombe au test"
```

---

## Ce que la phase 1 ne fait pas

| Sujet | Phase |
|---|---|
| Les déclencheurs d'événements, la file de réévaluation, son drain | 2 |
| Les écrans d'administration ; `/admin/automation` reste une coquille | 3 |
| Les réglages d'actions, la file des propositions, les actions qui écrivent dans le domaine | 4 |
| Les cinq règles reproduisant `BusinessAlerts` | 5 |
| Le champ « qui intervient » sur les réservations — la réponse fait autorité dans `missions`, pas dans `bookings.employe_id` | 2 ou 3 |
| Les tables `automation_reevaluations` et `automation_action_settings` | 2 et 4 |
