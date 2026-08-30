# Évaluateur de conditions partagé — plan d'implémentation (lot 1)

> **Pour les agents :** SOUS-SKILL REQUIS — employer `superpowers:subagent-driven-development`
> (recommandé) ou `superpowers:executing-plans` pour dérouler ce plan tâche par tâche. Les étapes
> emploient des cases à cocher (`- [ ]`).

**But :** extraire de `SegmentEngine` un évaluateur de conditions indépendant de l'entité, réparer
les trois champs dérivés qui plantent, et rendre l'échappement `LIKE` portable et borné.

**Architecture :** trois objets neufs dans `App\Services\Conditions` — `RuleTreeEvaluator` (parcours
de l'arbre et opérateurs), `EntityDescriptor` (contrat d'une entité), `FieldBinding` (liaison d'un
champ). `SegmentEngine` garde sa signature publique et devient un adaptateur au-dessus.

**Pile :** PHP 8.5, Laravel 12, Livewire 3, PHPUnit. Base applicative MySQL, base de tests SQLite.

**Spec :** `docs/superpowers/specs/2026-08-30-evaluateur-conditions-partage-design.md`

## Contraintes globales

- **Le vocabulaire reste en code.** Champs et opérateurs se déclarent en PHP. Aucune table de
  configuration dans ce lot.
- **Aucune dépendance neuve.** Pas de `symfony/expression-language`, pas de moteur de règles tiers.
- **Caractère d'échappement `LIKE` : `!`** — mesuré identique sur MySQL et SQLite. L'antislash est
  une erreur de syntaxe sur MySQL (`ESCAPE '\'` → 1064) et un refus sur SQLite (`ESCAPE '\\'` →
  « must be a single character »).
- **Bornes de l'arbre : profondeur ≤ 10, nœuds ≤ 200.**
- **Les 19 tests existants** (`tests/Feature/Marketing/SegmentEngineTest.php`,
  `tests/Feature/Marketing/SegmentEngineCoverageBatch7Test.php`) **ne se modifient pas.** Si l'un
  demande à être retouché, l'extraction a changé un comportement : c'est un signal d'arrêt.
- **Commentaires : deux lignes maximum.** Le code dit QUOI, le commit dit POURQUOI.
- **Portails avant chaque commit :** `./vendor/bin/pint --test` et
  `./vendor/bin/phpstan analyse --no-progress` (sans argument de chemin).
- Les noms de méthodes publiques de `SegmentEngine` (`compute`, `preview`) **ne changent pas**.

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `app/Services/Conditions/FieldBinding.php` | La liaison d'un champ exposé vers ce que SQL reçoit. Trois formes. |
| `app/Services/Conditions/EntityDescriptor.php` | Contrat : requête de départ, champs exposés, opérateurs permis. |
| `app/Services/Conditions/RuleTreeEvaluator.php` | Parcours `and`/`or`/`not`, les 15 opérateurs, bornes, échappement. |
| `app/Services/Conditions/RuleTreeTooComplex.php` | Exception de domaine levée quand une borne est dépassée. |
| `app/Services/Marketing/UserSegmentDescriptor.php` | Le descripteur des utilisateurs : 9 champs, dont 3 dérivés. |
| `app/Services/Marketing/SegmentEngine.php` | Devient un adaptateur ; garde adhésions, compteurs, journal. |

---

### Tâche 1 : caractériser les champs dérivés, sur le code actuel

Objectif : écrire noir sur blanc ce que font aujourd'hui `bookings_count`, `last_booking_at` et
`total_spent_cents`. Ils **plantent** ; le test le documente avant qu'on y touche.

**Fichiers :**
- Créer : `tests/Feature/Marketing/SegmentDerivedFieldsCharacterizationTest.php`

**Interfaces :**
- Consomme : `App\Services\Marketing\SegmentEngine::preview(array $rules, int $limit = 25): array`
- Produit : rien. Ce test disparaît en tâche 6, remplacé par les tests de réparation.

- [ ] **Étape 1 : écrire le test qui documente le plantage**

```php
<?php

namespace Tests\Feature\Marketing;

use App\Services\Marketing\SegmentEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CE QUE FONT LES CHAMPS DERIVES AUJOURD'HUI — ils plantent.
 *
 * `buildQuery` enveloppe l'arbre dans `where(function ($q) {...})`, et la jointure du champ
 * derive est posee sur ce constructeur imbrique : elle n'est jamais compilee.
 */
class SegmentDerivedFieldsCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public static function champsDerives(): array
    {
        return [
            'bookings_count' => ['bookings_count', 'gt', 2],
            'last_booking_at' => ['last_booking_at', 'is_not_null', null],
            'total_spent_cents' => ['total_spent_cents', 'gte', 100],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('champsDerives')]
    public function test_un_champ_derive_leve_une_erreur_de_colonne_inconnue(string $champ, string $op, mixed $valeur): void
    {
        $this->expectException(QueryException::class);

        app(SegmentEngine::class)->preview([
            'field' => $champ,
            'op' => $op,
            'value' => $valeur,
        ]);
    }

    /** TEMOIN — un champ simple, lui, repond. Sans lui, le test ci-dessus passerait au vert
     *  sur un moteur entierement casse. */
    public function test_temoin_un_champ_simple_repond_normalement(): void
    {
        $resultat = app(SegmentEngine::class)->preview([
            'field' => 'role',
            'op' => 'eq',
            'value' => 'client',
        ]);

        $this->assertIsArray($resultat);
    }
}
```

- [ ] **Étape 2 : lancer le test, vérifier qu'il PASSE sur le code actuel**

Lancer : `php artisan test tests/Feature/Marketing/SegmentDerivedFieldsCharacterizationTest.php`
Attendu : **4 tests verts**. S'ils échouent, le défaut n'est pas celui décrit — arrêter et remesurer.

- [ ] **Étape 3 : commiter**

```bash
git add tests/Feature/Marketing/SegmentDerivedFieldsCharacterizationTest.php
git commit -m "test(segments): les trois champs derives plantent, et rien ne le disait"
```

---

### Tâche 2 : `FieldBinding`

**Fichiers :**
- Créer : `app/Services/Conditions/FieldBinding.php`
- Créer : `tests/Unit/Conditions/FieldBindingTest.php`

**Interfaces :**
- Produit :
  - `FieldBinding::colonne(string $colonne): self`
  - `FieldBinding::jointe(Closure $jointure): self` — `Closure(EloquentBuilder $racine): ?string`
  - `FieldBinding::indisponible(): self`
  - propriétés publiques en lecture : `?string $colonne`, `?Closure $jointure`, `bool $servable`

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Unit\Conditions;

use App\Services\Conditions\FieldBinding;
use PHPUnit\Framework\TestCase;

class FieldBindingTest extends TestCase
{
    public function test_une_colonne_porte_son_nom_et_est_servable(): void
    {
        $liaison = FieldBinding::colonne('users.locale');

        $this->assertSame('users.locale', $liaison->colonne);
        $this->assertNull($liaison->jointure);
        $this->assertTrue($liaison->servable);
    }

    public function test_une_jointure_porte_sa_fermeture_et_aucune_colonne(): void
    {
        $liaison = FieldBinding::jointe(fn ($racine) => 'agg.valeur');

        $this->assertNull($liaison->colonne);
        $this->assertNotNull($liaison->jointure);
        $this->assertTrue($liaison->servable);
    }

    public function test_une_liaison_indisponible_ne_porte_rien(): void
    {
        $liaison = FieldBinding::indisponible();

        $this->assertNull($liaison->colonne);
        $this->assertNull($liaison->jointure);
        $this->assertFalse($liaison->servable);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Unit/Conditions/FieldBindingTest.php`
Attendu : ÉCHEC — `Class "App\Services\Conditions\FieldBinding" not found`.

- [ ] **Étape 3 : écrire l'implémentation minimale**

```php
<?php

namespace App\Services\Conditions;

use Closure;

/** La liaison d'un champ expose vers ce que SQL recevra. Trois formes, jamais melangees. */
final class FieldBinding
{
    private function __construct(
        public readonly ?string $colonne,
        public readonly ?Closure $jointure,
        public readonly bool $servable,
    ) {}

    public static function colonne(string $colonne): self
    {
        return new self($colonne, null, true);
    }

    /**
     * @param  Closure(\Illuminate\Database\Eloquent\Builder): ?string  $jointure
     *
     * La fermeture recoit la requete RACINE — jamais le noeud courant : une jointure
     * posee sur un constructeur imbrique n'est pas compilee.
     */
    public static function jointe(Closure $jointure): self
    {
        return new self(null, $jointure, true);
    }

    public static function indisponible(): self
    {
        return new self(null, null, false);
    }
}
```

- [ ] **Étape 4 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Unit/Conditions/FieldBindingTest.php`
Attendu : 3 tests verts.

- [ ] **Étape 5 : portails et commit**

```bash
./vendor/bin/pint app/Services/Conditions tests/Unit/Conditions
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Conditions/FieldBinding.php tests/Unit/Conditions/FieldBindingTest.php
git commit -m "feat(conditions): la liaison d'un champ, en trois formes"
```

---

### Tâche 3 : `EntityDescriptor` et `RuleTreeEvaluator` — parcours et opérateurs

**Fichiers :**
- Créer : `app/Services/Conditions/EntityDescriptor.php`
- Créer : `app/Services/Conditions/RuleTreeEvaluator.php`
- Créer : `tests/Feature/Conditions/RuleTreeEvaluatorTest.php`

**Interfaces :**
- Consomme : `FieldBinding` (tâche 2).
- Produit :
  - `interface EntityDescriptor { baseQuery(): Builder; fields(): array; operators(): array; }`
  - `RuleTreeEvaluator::apply(Builder $racine, array $noeud, EntityDescriptor $entite): void`

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Conditions;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleTreeEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private function entite(): EntityDescriptor
    {
        return new class implements EntityDescriptor
        {
            public function baseQuery(): Builder
            {
                return User::query();
            }

            public function fields(): array
            {
                return [
                    'role' => FieldBinding::colonne('users.role'),
                    'locale' => FieldBinding::colonne('users.locale'),
                    'absent' => FieldBinding::indisponible(),
                ];
            }

            public function operators(): array
            {
                return ['eq', 'neq', 'in', 'is_null'];
            }
        };
    }

    private function compter(array $noeud): int
    {
        $entite = $this->entite();
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);

        return $requete->count();
    }

    public function test_une_feuille_filtre_sur_sa_colonne(): void
    {
        User::factory()->create(['role' => 'client']);
        User::factory()->create(['role' => 'admin']);

        $this->assertSame(1, $this->compter(['field' => 'role', 'op' => 'eq', 'value' => 'client']));
    }

    public function test_le_noeud_and_cumule_les_contraintes(): void
    {
        User::factory()->create(['role' => 'client', 'locale' => 'fr']);
        User::factory()->create(['role' => 'client', 'locale' => 'nl']);

        $this->assertSame(1, $this->compter(['and' => [
            ['field' => 'role', 'op' => 'eq', 'value' => 'client'],
            ['field' => 'locale', 'op' => 'eq', 'value' => 'fr'],
        ]]));
    }

    public function test_le_noeud_or_reunit_les_contraintes(): void
    {
        User::factory()->create(['role' => 'client']);
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'employe']);

        $this->assertSame(2, $this->compter(['or' => [
            ['field' => 'role', 'op' => 'eq', 'value' => 'client'],
            ['field' => 'role', 'op' => 'eq', 'value' => 'admin'],
        ]]));
    }

    public function test_le_noeud_not_exclut(): void
    {
        User::factory()->create(['role' => 'client']);
        User::factory()->create(['role' => 'admin']);

        $this->assertSame(1, $this->compter([
            'not' => ['field' => 'role', 'op' => 'eq', 'value' => 'client'],
        ]));
    }

    public function test_un_champ_inconnu_ne_correspond_a_personne(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(0, $this->compter(['field' => 'inexistant', 'op' => 'eq', 'value' => 'x']));
    }

    public function test_un_operateur_hors_liste_ne_correspond_a_personne(): void
    {
        User::factory()->count(3)->create(['role' => 'client']);

        $this->assertSame(0, $this->compter(['field' => 'role', 'op' => 'contains', 'value' => 'cli']));
    }

    public function test_un_champ_indisponible_ne_correspond_a_personne(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(0, $this->compter(['field' => 'absent', 'op' => 'eq', 'value' => 'x']));
    }

    /** TEMOIN — sans filtre, tout le monde repond. Sans lui, les trois refus ci-dessus
     *  passeraient au vert sur une table vide. */
    public function test_temoin_un_arbre_vide_ne_filtre_rien(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(3, $this->compter([]));
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Conditions/RuleTreeEvaluatorTest.php`
Attendu : ÉCHEC — `Interface "App\Services\Conditions\EntityDescriptor" not found`.

- [ ] **Étape 3 : écrire le contrat**

```php
<?php

namespace App\Services\Conditions;

use Illuminate\Database\Eloquent\Builder;

/** Ce qu'une entite doit savoir dire pour etre filtrable par un arbre de conditions. */
interface EntityDescriptor
{
    /** @return Builder<\Illuminate\Database\Eloquent\Model> */
    public function baseQuery(): Builder;

    /** @return array<string, FieldBinding> les cles SONT la liste blanche des champs */
    public function fields(): array;

    /** @return list<string> les operateurs permis pour cette entite */
    public function operators(): array;
}
```

- [ ] **Étape 4 : écrire l'évaluateur**

```php
<?php

namespace App\Services\Conditions;

use Illuminate\Database\Eloquent\Builder;

/** Le parcours d'un arbre de conditions et sa traduction en Eloquent. Ne connait aucune entite. */
class RuleTreeEvaluator
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $racine
     * @param  array<string, mixed>  $noeud
     */
    public function apply(Builder $racine, array $noeud, EntityDescriptor $entite): void
    {
        if ($noeud === []) {
            return;
        }

        $racine->where(function (Builder $groupe) use ($racine, $noeud, $entite) {
            $this->appliquerNoeud($groupe, $racine, $noeud, $entite);
        });
    }

    /**
     * `$racine` voyage a cote de `$groupe` : une jointure posee sur un constructeur
     * imbrique n'est jamais compilee.
     *
     * @param  array<string, mixed>  $noeud
     */
    protected function appliquerNoeud(Builder $groupe, Builder $racine, array $noeud, EntityDescriptor $entite): void
    {
        if (isset($noeud['and']) && is_array($noeud['and'])) {
            $groupe->where(function (Builder $interne) use ($racine, $noeud, $entite) {
                foreach ($noeud['and'] as $sous) {
                    $interne->where(function (Builder $w) use ($racine, $sous, $entite) {
                        $this->appliquerNoeud($w, $racine, $sous, $entite);
                    });
                }
            });

            return;
        }

        if (isset($noeud['or']) && is_array($noeud['or'])) {
            $groupe->where(function (Builder $interne) use ($racine, $noeud, $entite) {
                foreach ($noeud['or'] as $sous) {
                    $interne->orWhere(function (Builder $w) use ($racine, $sous, $entite) {
                        $this->appliquerNoeud($w, $racine, $sous, $entite);
                    });
                }
            });

            return;
        }

        if (isset($noeud['not'])) {
            $groupe->whereNot(function (Builder $interne) use ($racine, $noeud, $entite) {
                $this->appliquerNoeud($interne, $racine, (array) $noeud['not'], $entite);
            });

            return;
        }

        $this->appliquerFeuille($groupe, $racine, $noeud, $entite);
    }

    /** @param array<string, mixed> $feuille */
    protected function appliquerFeuille(Builder $groupe, Builder $racine, array $feuille, EntityDescriptor $entite): void
    {
        $champ = (string) ($feuille['field'] ?? '');
        $op = (string) ($feuille['op'] ?? '');
        $valeur = $feuille['value'] ?? null;

        $liaison = $entite->fields()[$champ] ?? null;

        if ($liaison === null || ! $liaison->servable || ! in_array($op, $entite->operators(), true)) {
            $groupe->whereRaw('1=0');

            return;
        }

        $colonne = $liaison->colonne ?? ($liaison->jointure)($racine);

        if ($colonne === null) {
            $groupe->whereRaw('1=0');

            return;
        }

        $this->appliquerOperateur($groupe, $colonne, $op, $valeur);
    }

    protected function appliquerOperateur(Builder $q, string $colonne, string $op, mixed $valeur): void
    {
        match ($op) {
            'eq' => $q->where($colonne, '=', $valeur),
            'neq' => $q->where($colonne, '!=', $valeur),
            'in' => $q->whereIn($colonne, (array) $valeur),
            'not_in' => $q->whereNotIn($colonne, (array) $valeur),
            'gt' => $q->where($colonne, '>', $valeur),
            'gte' => $q->where($colonne, '>=', $valeur),
            'lt' => $q->where($colonne, '<', $valeur),
            'lte' => $q->where($colonne, '<=', $valeur),
            'older_than_days' => $q->where($colonne, '<=', now()->subDays((int) $valeur)),
            'newer_than_days' => $q->where($colonne, '>=', now()->subDays((int) $valeur)),
            'is_null' => $q->whereNull($colonne),
            'is_not_null' => $q->whereNotNull($colonne),
            default => $q->whereRaw('1=0'),
        };
    }
}
```

- [ ] **Étape 5 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Conditions/RuleTreeEvaluatorTest.php`
Attendu : 8 tests verts.

- [ ] **Étape 6 : portails et commit**

```bash
./vendor/bin/pint app/Services/Conditions tests/Feature/Conditions
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Conditions tests/Feature/Conditions/RuleTreeEvaluatorTest.php
git commit -m "feat(conditions): l'evaluateur d'arbre, independant de l'entite"
```

---

### Tâche 4 : l'échappement `LIKE`, portable

Les trois opérateurs de chaîne manquent encore à `appliquerOperateur`. On les ajoute avec le
caractère d'échappement `!`, mesuré identique sur MySQL et SQLite.

**Fichiers :**
- Modifier : `app/Services/Conditions/RuleTreeEvaluator.php`
- Créer : `tests/Feature/Conditions/LikeEscapingTest.php`

**Interfaces :**
- Produit : `RuleTreeEvaluator::CARACTERE_ECHAPPEMENT = '!'` (constante publique).

- [ ] **Étape 1 : écrire le test, avec ses témoins**

```php
<?php

namespace Tests\Feature\Conditions;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'ECHAPPEMENT DES JOKERS SQL, SUR LES DEUX MOTEURS.
 *
 * L'antislash ne peut pas servir : `ESCAPE '\'` est une erreur 1064 sur MySQL, et
 * `ESCAPE '\\'` un refus sur SQLite. `!` passe des deux cotes.
 */
class LikeEscapingTest extends TestCase
{
    use RefreshDatabase;

    private function entite(): EntityDescriptor
    {
        return new class implements EntityDescriptor
        {
            public function baseQuery(): Builder
            {
                return User::query();
            }

            public function fields(): array
            {
                return ['name' => FieldBinding::colonne('users.name')];
            }

            public function operators(): array
            {
                return ['contains', 'starts_with', 'ends_with'];
            }
        };
    }

    private function noms(string $op, string $valeur): array
    {
        $entite = $this->entite();
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, ['field' => 'name', 'op' => $op, 'value' => $valeur], $entite);

        return $requete->pluck('name')->sort()->values()->all();
    }

    public function test_le_pourcent_est_un_caractere_et_non_un_joker(): void
    {
        User::factory()->create(['name' => 'a%b']);
        User::factory()->create(['name' => 'axb']);   // TEMOIN : ne doit PAS remonter

        $this->assertSame(['a%b'], $this->noms('contains', 'a%b'));
    }

    public function test_le_souligne_est_un_caractere_et_non_un_joker(): void
    {
        User::factory()->create(['name' => 'a_b']);
        User::factory()->create(['name' => 'axb']);   // TEMOIN

        $this->assertSame(['a_b'], $this->noms('contains', 'a_b'));
    }

    public function test_le_caractere_d_echappement_lui_meme_reste_litteral(): void
    {
        User::factory()->create(['name' => 'a!b']);
        User::factory()->create(['name' => 'axb']);   // TEMOIN

        $this->assertSame(['a!b'], $this->noms('contains', 'a!b'));
    }

    public function test_starts_with_et_ends_with_ancrent_bien(): void
    {
        User::factory()->create(['name' => 'alpha']);
        User::factory()->create(['name' => 'beta-alpha']);

        $this->assertSame(['alpha'], $this->noms('starts_with', 'alp'));
        $this->assertSame(['alpha', 'beta-alpha'], $this->noms('ends_with', 'pha'));
    }

    /** TEMOIN — `contains` trouve bien quelque chose quand rien n'est echappe. Sans lui, les
     *  trois tests ci-dessus passeraient au vert sur un LIKE qui ne rend jamais rien. */
    public function test_temoin_contains_trouve_une_correspondance_ordinaire(): void
    {
        User::factory()->create(['name' => 'Marc Dubois']);

        $this->assertSame(['Marc Dubois'], $this->noms('contains', 'Dubo'));
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Conditions/LikeEscapingTest.php`
Attendu : ÉCHEC — les opérateurs de chaîne tombent dans `default` et rendent `1=0`, donc les
listes sont vides.

- [ ] **Étape 3 : ajouter les trois opérateurs à `appliquerOperateur`**

Dans `RuleTreeEvaluator`, ajouter la constante en tête de classe :

```php
    /** `!` et non `\` : mesure du 2026-08-30, l'antislash casse sur l'un ou l'autre moteur. */
    public const CARACTERE_ECHAPPEMENT = '!';
```

Remplacer la branche `default` du `match` par les trois cas puis le repli :

```php
            'contains' => $this->appliquerLike($q, $colonne, '%'.$this->echapper((string) $valeur).'%'),
            'starts_with' => $this->appliquerLike($q, $colonne, $this->echapper((string) $valeur).'%'),
            'ends_with' => $this->appliquerLike($q, $colonne, '%'.$this->echapper((string) $valeur)),
            default => $q->whereRaw('1=0'),
```

Et ajouter les deux méthodes :

```php
    /** Le caractere d'echappement D'ABORD, sinon on re-echappe ce qu'on vient d'ecrire. */
    protected function echapper(string $valeur): string
    {
        $e = self::CARACTERE_ECHAPPEMENT;

        return str_replace([$e, '%', '_'], [$e.$e, $e.'%', $e.'_'], $valeur);
    }

    /** Clause explicite : SQLite n'a AUCUN caractere d'echappement par defaut. */
    protected function appliquerLike(Builder $q, string $colonne, string $motif): Builder
    {
        $nom = $q->getQuery()->getGrammar()->wrap($colonne);

        return $q->whereRaw($nom." LIKE ? ESCAPE '".self::CARACTERE_ECHAPPEMENT."'", [$motif]);
    }
```

- [ ] **Étape 4 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Conditions/LikeEscapingTest.php`
Attendu : 5 tests verts.

- [ ] **Étape 5 : portails et commit**

```bash
./vendor/bin/pint app/Services/Conditions tests/Feature/Conditions
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Conditions/RuleTreeEvaluator.php tests/Feature/Conditions/LikeEscapingTest.php
git commit -m "fix(conditions): l'echappement LIKE ne marchait pas sur SQLite, et pas du tout pour _"
```

---

### Tâche 5 : les bornes de l'arbre

**Fichiers :**
- Créer : `app/Services/Conditions/RuleTreeTooComplex.php`
- Modifier : `app/Services/Conditions/RuleTreeEvaluator.php`
- Créer : `tests/Feature/Conditions/RuleTreeBoundsTest.php`

**Interfaces :**
- Produit :
  - `class RuleTreeTooComplex extends \RuntimeException`
  - `RuleTreeEvaluator::PROFONDEUR_MAX = 10`, `RuleTreeEvaluator::NOEUDS_MAX = 200`

- [ ] **Étape 1 : écrire le test, avec son témoin à la limite exacte**

```php
<?php

namespace Tests\Feature\Conditions;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Conditions\RuleTreeTooComplex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleTreeBoundsTest extends TestCase
{
    use RefreshDatabase;

    private function entite(): EntityDescriptor
    {
        return new class implements EntityDescriptor
        {
            public function baseQuery(): Builder
            {
                return User::query();
            }

            public function fields(): array
            {
                return ['role' => FieldBinding::colonne('users.role')];
            }

            public function operators(): array
            {
                return ['eq'];
            }
        };
    }

    private function feuille(): array
    {
        return ['field' => 'role', 'op' => 'eq', 'value' => 'client'];
    }

    /** Un arbre de profondeur $n : n-1 groupes `and` imbriques, puis une feuille. */
    private function profondeur(int $n): array
    {
        $noeud = $this->feuille();

        for ($i = 1; $i < $n; $i++) {
            $noeud = ['and' => [$noeud]];
        }

        return $noeud;
    }

    private function evaluer(array $noeud): void
    {
        $entite = $this->entite();
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);
    }

    public function test_un_arbre_a_la_profondeur_maximale_passe(): void
    {
        $this->evaluer($this->profondeur(RuleTreeEvaluator::PROFONDEUR_MAX));

        $this->assertTrue(true, 'Un arbre a la limite exacte doit passer.');
    }

    public function test_un_arbre_trop_profond_est_refuse(): void
    {
        $this->expectException(RuleTreeTooComplex::class);

        $this->evaluer($this->profondeur(RuleTreeEvaluator::PROFONDEUR_MAX + 1));
    }

    public function test_un_arbre_au_nombre_de_noeuds_maximal_passe(): void
    {
        // 1 groupe + (NOEUDS_MAX - 1) feuilles.
        $feuilles = array_fill(0, RuleTreeEvaluator::NOEUDS_MAX - 1, $this->feuille());

        $this->evaluer(['and' => $feuilles]);

        $this->assertTrue(true, 'Un arbre au nombre de noeuds exact doit passer.');
    }

    public function test_un_arbre_trop_large_est_refuse(): void
    {
        $this->expectException(RuleTreeTooComplex::class);

        $feuilles = array_fill(0, RuleTreeEvaluator::NOEUDS_MAX, $this->feuille());

        $this->evaluer(['and' => $feuilles]);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Conditions/RuleTreeBoundsTest.php`
Attendu : ÉCHEC — `Class "App\Services\Conditions\RuleTreeTooComplex" not found`.

- [ ] **Étape 3 : écrire l'exception**

```php
<?php

namespace App\Services\Conditions;

use RuntimeException;

/** Un arbre trop profond ou trop large : refuse, et non silencieusement vide. */
class RuleTreeTooComplex extends RuntimeException {}
```

- [ ] **Étape 4 : borner dans l'évaluateur**

Ajouter les constantes en tête de `RuleTreeEvaluator` :

```php
    public const PROFONDEUR_MAX = 10;

    public const NOEUDS_MAX = 200;
```

Dans `apply()`, avant le `where(...)` :

```php
        $this->verifierLesBornes($noeud);
```

Et la méthode :

```php
    /**
     * @param  array<string, mixed>  $noeud
     *
     * @throws RuleTreeTooComplex
     */
    protected function verifierLesBornes(array $noeud, int $profondeur = 1, int &$noeuds = 0): void
    {
        if ($profondeur > self::PROFONDEUR_MAX) {
            throw new RuleTreeTooComplex('Arbre trop profond : '.self::PROFONDEUR_MAX.' niveaux au plus.');
        }

        if (++$noeuds > self::NOEUDS_MAX) {
            throw new RuleTreeTooComplex('Arbre trop large : '.self::NOEUDS_MAX.' noeuds au plus.');
        }

        foreach (['and', 'or'] as $groupe) {
            foreach ((array) ($noeud[$groupe] ?? []) as $sous) {
                $this->verifierLesBornes((array) $sous, $profondeur + 1, $noeuds);
            }
        }

        if (isset($noeud['not'])) {
            $this->verifierLesBornes((array) $noeud['not'], $profondeur + 1, $noeuds);
        }
    }
```

- [ ] **Étape 5 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Conditions/RuleTreeBoundsTest.php`
Attendu : 4 tests verts.

- [ ] **Étape 6 : portails et commit**

```bash
./vendor/bin/pint app/Services/Conditions tests/Feature/Conditions
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Conditions tests/Feature/Conditions/RuleTreeBoundsTest.php
git commit -m "feat(conditions): un arbre trop profond ou trop large est refuse, pas vide"
```

---

### Tâche 6 : `UserSegmentDescriptor` — et la réparation des champs dérivés

**Fichiers :**
- Créer : `app/Services/Marketing/UserSegmentDescriptor.php`
- Créer : `tests/Feature/Marketing/UserSegmentDescriptorTest.php`
- Supprimer : `tests/Feature/Marketing/SegmentDerivedFieldsCharacterizationTest.php`

**Interfaces :**
- Consomme : `EntityDescriptor`, `FieldBinding` (tâches 2 et 3).
- Produit : `UserSegmentDescriptor` implémentant `EntityDescriptor`.

- [ ] **Étape 1 : écrire le test — les trois champs dérivés RÉPONDENT**

```php
<?php

namespace Tests\Feature\Marketing;

use App\Models\Booking;
use App\Models\User;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Marketing\UserSegmentDescriptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les trois champs derives plantaient : la jointure etait posee sur un constructeur imbrique. */
class UserSegmentDescriptorTest extends TestCase
{
    use RefreshDatabase;

    private function compter(array $noeud): int
    {
        $entite = app(UserSegmentDescriptor::class);
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);

        return $requete->distinct()->count('users.id');
    }

    public function test_bookings_count_filtre_sur_le_nombre_de_reservations(): void
    {
        $charge = User::factory()->client()->create();
        Booking::factory()->count(3)->create(['client_id' => $charge->id]);
        User::factory()->client()->create();   // TEMOIN : aucune reservation

        $this->assertSame(1, $this->compter(['field' => 'bookings_count', 'op' => 'gt', 'value' => 2]));
    }

    public function test_last_booking_at_repond(): void
    {
        $avec = User::factory()->client()->create();
        Booking::factory()->create(['client_id' => $avec->id]);
        User::factory()->client()->create();   // TEMOIN

        $this->assertSame(1, $this->compter(['field' => 'last_booking_at', 'op' => 'is_not_null', 'value' => null]));
    }

    public function test_total_spent_cents_repond(): void
    {
        $payeur = User::factory()->client()->create();

        // `final_price` n'est PAS `fillable` : passe au factory, il serait ecarte EN SILENCE
        // et le test echouerait sans dire pourquoi.
        Booking::factory()->create(['client_id' => $payeur->id])
            ->forceFill(['final_price' => 200])->save();

        User::factory()->client()->create();   // TEMOIN

        $this->assertSame(1, $this->compter(['field' => 'total_spent_cents', 'op' => 'gte', 'value' => 100]));
    }

    /** DEUX FOIS LE MEME CHAMP DERIVE : deux jointures de meme alias, erreur SQL 1066. */
    public function test_le_meme_champ_derive_deux_fois_ne_double_pas_la_jointure(): void
    {
        $bon = User::factory()->client()->create();
        Booking::factory()->count(5)->create(['client_id' => $bon->id]);

        $trop = User::factory()->client()->create();
        Booking::factory()->count(20)->create(['client_id' => $trop->id]);

        $this->assertSame(1, $this->compter(['and' => [
            ['field' => 'bookings_count', 'op' => 'gt', 'value' => 2],
            ['field' => 'bookings_count', 'op' => 'lt', 'value' => 10],
        ]]));
    }

    public function test_les_champs_declares_sont_ceux_de_la_configuration(): void
    {
        $this->assertSame(
            config('marketing.segment_fields'),
            array_keys(app(UserSegmentDescriptor::class)->fields())
        );
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test tests/Feature/Marketing/UserSegmentDescriptorTest.php`
Attendu : ÉCHEC — `Class "App\Services\Marketing\UserSegmentDescriptor" not found`.

- [ ] **Étape 3 : écrire le descripteur**

```php
<?php

namespace App\Services\Marketing;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Les utilisateurs, vus par un arbre de conditions. La configuration garde la main. */
class UserSegmentDescriptor implements EntityDescriptor
{
    /** @var array<string, true> les alias deja joints sur CETTE requete racine */
    protected array $jointures = [];

    /** @var array<string, FieldBinding>|null */
    protected ?array $champs = null;

    public function baseQuery(): Builder
    {
        $this->jointures = [];

        return User::query();
    }

    /** Memoise : l'evaluateur appelle `fields()` a CHAQUE feuille, jusqu'a 200 fois. */
    public function fields(): array
    {
        if ($this->champs !== null) {
            return $this->champs;
        }

        $liaisons = [];

        foreach ((array) config('marketing.segment_fields', []) as $champ) {
            $liaisons[$champ] = $this->liaison((string) $champ);
        }

        return $this->champs = $liaisons;
    }

    public function operators(): array
    {
        return array_values((array) config('marketing.segment_operators', []));
    }

    protected function liaison(string $champ): FieldBinding
    {
        return match ($champ) {
            // `wrapForDomain` rendait la valeur inchangee : une colonne suffit.
            'email_domain' => FieldBinding::colonne('users.email'),
            'bookings_count' => $this->agregat('b_count_agg', fn ($col) => DB::raw('COUNT(*) AS agg')),
            'last_booking_at' => $this->agregat('b_lastat_agg', fn ($col) => DB::raw('MAX(created_at) AS agg')),
            'total_spent_cents' => $this->agregatDeMontant(),
            default => FieldBinding::colonne('users.'.$champ),
        };
    }

    /** @param \Closure(string): mixed $selection */
    protected function agregat(string $alias, \Closure $selection): FieldBinding
    {
        return FieldBinding::jointe(function (Builder $racine) use ($alias, $selection): ?string {
            $client = $this->colonneClient();

            if ($client === null) {
                return null;
            }

            $this->joindreUneSeuleFois($racine, $alias, $client, $selection($client));

            return $alias.'.agg';
        });
    }

    protected function agregatDeMontant(): FieldBinding
    {
        return FieldBinding::jointe(function (Builder $racine): ?string {
            $client = $this->colonneClient();
            $montant = $this->colonneDeMontant();

            if ($client === null || $montant === null) {
                return null;
            }

            $this->joindreUneSeuleFois($racine, 'b_spent_agg', $client, DB::raw("SUM({$montant}) AS agg"));

            return 'b_spent_agg.agg';
        });
    }

    /** L'alias est fixe : deux emplois du meme champ posaient deux jointures identiques. */
    protected function joindreUneSeuleFois(Builder $racine, string $alias, string $client, mixed $selection): void
    {
        if (isset($this->jointures[$alias])) {
            return;
        }

        $sous = DB::table('bookings')
            ->select($selection, $client.' AS uid')
            ->groupBy($client);

        $racine->leftJoinSub($sous, $alias, fn ($jointure) => $jointure->on('users.id', '=', $alias.'.uid'));

        $this->jointures[$alias] = true;
    }

    protected function colonneClient(): ?string
    {
        foreach (['client_id', 'customer_user_id'] as $colonne) {
            if (Schema::hasColumn('bookings', $colonne)) {
                return $colonne;
            }
        }

        return null;
    }

    protected function colonneDeMontant(): ?string
    {
        foreach (['final_price', 'payment_amount_cents'] as $colonne) {
            if (Schema::hasColumn('bookings', $colonne)) {
                return $colonne;
            }
        }

        return null;
    }
}
```

- [ ] **Étape 4 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Marketing/UserSegmentDescriptorTest.php`
Attendu : 5 tests verts.

- [ ] **Étape 5 : retirer le test de caractérisation, devenu faux**

Il documentait un plantage qui n'existe plus.

```bash
git rm tests/Feature/Marketing/SegmentDerivedFieldsCharacterizationTest.php
```

- [ ] **Étape 6 : portails et commit**

```bash
./vendor/bin/pint app/Services/Marketing tests/Feature/Marketing
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Marketing/UserSegmentDescriptor.php tests/Feature/Marketing/UserSegmentDescriptorTest.php
git commit -m "fix(segments): les trois champs derives plantaient, la jointure etait posee au mauvais endroit"
```

---

### Tâche 7 : `SegmentEngine` devient un adaptateur

C'est la tâche où les 19 tests existants font foi.

**Fichiers :**
- Modifier : `app/Services/Marketing/SegmentEngine.php`

**Interfaces :**
- Consomme : `RuleTreeEvaluator`, `UserSegmentDescriptor`, `RuleTreeTooComplex`.
- Produit : `SegmentEngine::compute(MarketingSegment $segment): int` et
  `SegmentEngine::preview(array $rules, int $limit = 25): array` — **signatures inchangées**.

- [ ] **Étape 1 : noter le vert de départ**

Lancer : `php artisan test tests/Feature/Marketing/`
Attendu : tout vert. Noter le nombre de tests : c'est la référence.

- [ ] **Étape 2 : remplacer le corps de `buildQuery` et supprimer le code déplacé**

Remplacer `buildQuery`, `applyNode`, `applyLeaf`, `applyOperator`, `wrapForDomain` et
`applyBookingDerivedField` par :

```php
    /** @return Builder<User>|null */
    protected function buildQuery(array $rules): ?Builder
    {
        // DES REGLES VIDES NE SELECTIONNENT PERSONNE, et surtout pas tout le monde : un
        // segment vide qui prendrait toute la base lui enverrait la prochaine campagne.
        if (empty($rules)) {
            return null;
        }

        $entite = app(UserSegmentDescriptor::class);
        $requete = $entite->baseQuery();

        app(RuleTreeEvaluator::class)->apply($requete, $rules, $entite);

        return $requete;
    }
```

**Cette garde est la correction d'un défaut du plan**, relevée à l'exécution. Sans elle,
`RuleTreeEvaluator::apply([])` traite l'arbre vide comme « aucune contrainte » — ce qui est le bon
contrat pour un évaluateur générique, et le mauvais pour un segment. Le test existant
`SegmentEngineCoverageBatch7Test::test_preview_empty_rules_returns_zero` fige le contrat historique
et a détecté l'écart. C'est le rôle de l'adaptateur de trancher, pas celui de l'évaluateur.

Retirer les `use` devenus inutiles (`Config`, `Schema`, et `DB` s'il n'est plus employé
ailleurs dans le fichier) et ajouter :

```php
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Conditions\RuleTreeTooComplex;
```

- [ ] **Étape 3 : rattraper l'arbre refusé dans `compute`**

Envelopper l'appel à `buildQuery` :

```php
        try {
            $query = $this->buildQuery($segment->rules ?? []);
        } catch (RuleTreeTooComplex $e) {
            ActivityLogger::log('marketing.segment_rejected', $segment, ['raison' => $e->getMessage()]);

            return 0;
        }
```

Faire de même dans `preview()`, qui rend déjà cette forme exacte pour une requête absente :

```php
        try {
            $query = $this->buildQuery($rules);
        } catch (RuleTreeTooComplex) {
            return ['count' => 0, 'sample' => []];
        }
```

- [ ] **Étape 4 : lancer les 19 tests existants, SANS les modifier**

Lancer : `php artisan test tests/Feature/Marketing/`
Attendu : le même nombre de tests verts qu'à l'étape 1.

**Si un test existant demande à être retouché, ARRÊTER.** L'extraction a changé un comportement :
c'est le signal d'arrêt prévu par la spec, pas un test à ajuster.

- [ ] **Étape 5 : portails et commit**

```bash
./vendor/bin/pint app/Services/Marketing
./vendor/bin/phpstan analyse --no-progress
git add app/Services/Marketing/SegmentEngine.php
git commit -m "refactor(segments): SegmentEngine devient un adaptateur sur l'evaluateur partage"
```

---

### Tâche 8 : le garde-fou des descripteurs, et la vérification d'ensemble

**Fichiers :**
- Créer : `tests/Feature/Conditions/DescriptorFieldsResolveTest.php`

**Interfaces :**
- Consomme : `UserSegmentDescriptor`, `RuleTreeEvaluator`.
- Produit : rien. Ce test couvrira gratuitement les descripteurs du lot 2.

- [ ] **Étape 1 : écrire le test**

```php
<?php

namespace Tests\Feature\Conditions;

use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Marketing\UserSegmentDescriptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un champ declare vers une colonne absente ne cassait qu'a l'execution, chez un utilisateur. */
class DescriptorFieldsResolveTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string<EntityDescriptor>}> */
    public static function descripteurs(): array
    {
        return [
            'utilisateurs' => [UserSegmentDescriptor::class],
        ];
    }

    /** @param class-string<EntityDescriptor> $classe */
    #[\PHPUnit\Framework\Attributes\DataProvider('descripteurs')]
    public function test_chaque_champ_declare_produit_une_requete_qui_s_execute(string $classe): void
    {
        $echecs = [];

        foreach (array_keys(app($classe)->fields()) as $champ) {
            $entite = app($classe);
            $requete = $entite->baseQuery();

            try {
                app(RuleTreeEvaluator::class)->apply(
                    $requete,
                    ['field' => $champ, 'op' => 'is_not_null', 'value' => null],
                    $entite
                );
                $requete->count();
            } catch (\Throwable $e) {
                $echecs[] = $champ.' : '.substr($e->getMessage(), 0, 90);
            }
        }

        $this->assertSame([], $echecs, "Ces champs declares ne s'executent pas :\n".implode("\n", $echecs));
    }

    /** TEMOIN — le descripteur declare bien des champs. Sans lui, le test passerait au vert
     *  sur une liste vide. */
    public function test_temoin_le_descripteur_declare_des_champs(): void
    {
        $this->assertNotEmpty(app(UserSegmentDescriptor::class)->fields());
    }
}
```

- [ ] **Étape 2 : lancer, vérifier le vert**

Lancer : `php artisan test tests/Feature/Conditions/DescriptorFieldsResolveTest.php`
Attendu : 2 tests verts. Un échec ici nomme le champ fautif.

- [ ] **Étape 3 : la suite complète**

```bash
git status --short          # l'arbre doit etre propre AVANT de lancer
composer test:parallele
```

Attendu : aucun échec. Ne modifier **aucun** fichier pendant que la suite tourne.

- [ ] **Étape 4 : les deux portails**

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
```

- [ ] **Étape 5 : commit**

```bash
git add tests/Feature/Conditions/DescriptorFieldsResolveTest.php
git commit -m "test(conditions): un descripteur qui ment tombe au test, plus en production"
```

---

## Ce que ce lot ne fait pas

| Sujet | Pourquoi | Où |
|---|---|---|
| Un champ ou opérateur inconnu rend `1=0` en silence | Aucun écran, à ce lot, pour le dire | Lot 2 |
| Les 5 alertes `BusinessAlerts` écrites en dur | Hors périmètre de l'extraction | Lot 2 |
| Tout écran d'administration | Ce lot ne touche à aucune vue | Lot 2 |
| Une couverture MySQL par la suite | La suite tourne sur SQLite ; les deux moteurs ont été mesurés à la main | — |
