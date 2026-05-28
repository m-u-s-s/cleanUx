# Schema/Model Drift Guardrail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Detect Eloquent models whose `$fillable`/`$casts` reference non-existent columns (plus unsettable NOT NULL columns and missing tables), as a runnable audit and a CI gate.

**Architecture:** A central `SchemaDriftAnalyzer` discovers models, introspects each table via `Schema::getColumns()`, and runs a set of `DriftRule` checks producing `DriftFinding` value objects. Two thin consumers reuse it: an artisan command (`schema:audit-drift`) for ad-hoc audits and a PHPUnit feature test as the CI gate. A `config/schema_drift.php` allowlist suppresses legitimate exceptions.

**Tech Stack:** Laravel 11, PHPUnit, sqlite `:memory:` (test DB), Symfony Finder (model discovery).

Spec: `docs/superpowers/specs/2026-05-28-schema-drift-guardrail-design.md`

## File structure

- Create `app/Services/Schema/DriftFinding.php` — immutable finding value object + rule-name constants.
- Create `app/Services/Schema/Rules/DriftRule.php` — rule interface.
- Create `app/Services/Schema/Rules/FillableColumnsExistRule.php` — fillable → column check.
- Create `app/Services/Schema/Rules/CastColumnsExistRule.php` — casts → column check.
- Create `app/Services/Schema/Rules/UnsettableNotNullRule.php` — NOT NULL/no-default/not-fillable check.
- Create `app/Services/Schema/SchemaDriftAnalyzer.php` — discovery + orchestration + ignore filtering.
- Create `config/schema_drift.php` — exception allowlist.
- Create `app/Console/Commands/SchemaAuditDriftCommand.php` — `schema:audit-drift`.
- Create `tests/Unit/Schema/RulesTest.php` — pure unit tests for the 3 rules.
- Create `tests/Unit/Schema/SchemaDriftAnalyzerTest.php` — analyzer tests with fixture models/tables.
- Create `tests/Feature/Schema/SchemaAuditDriftCommandTest.php` — command output/exit-code tests (faked analyzer).
- Create `tests/Feature/Schema/SchemaModelDriftTest.php` — the CI gate (scans real models against migrated schema).

---

### Task 1: DriftFinding value object

**Files:**
- Create: `app/Services/Schema/DriftFinding.php`
- Test: `tests/Unit/Schema/RulesTest.php` (created here, extended in Tasks 2-4)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Schema/RulesTest.php`:

```php
<?php

namespace Tests\Unit\Schema;

use App\Services\Schema\DriftFinding;
use Tests\TestCase;

class RulesTest extends TestCase
{
    public function test_drift_finding_exposes_fields_and_to_array(): void
    {
        $finding = new DriftFinding(
            modelClass: 'App\\Models\\Foo',
            table: 'foos',
            column: 'ghost',
            rule: DriftFinding::RULE_MISSING_FILLABLE,
            message: 'boom',
        );

        $this->assertSame('App\\Models\\Foo', $finding->modelClass);
        $this->assertSame('ghost', $finding->column);
        $this->assertSame('missing_fillable_column', $finding->rule);
        $this->assertSame([
            'model' => 'App\\Models\\Foo',
            'table' => 'foos',
            'column' => 'ghost',
            'rule' => 'missing_fillable_column',
            'message' => 'boom',
        ], $finding->toArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/RulesTest.php`
Expected: FAIL — `Class "App\Services\Schema\DriftFinding" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Schema/DriftFinding.php`:

```php
<?php

namespace App\Services\Schema;

class DriftFinding
{
    public const RULE_MISSING_FILLABLE = 'missing_fillable_column';
    public const RULE_MISSING_CAST = 'missing_cast_column';
    public const RULE_UNSETTABLE_NOT_NULL = 'unsettable_not_null';
    public const RULE_MISSING_TABLE = 'missing_table';
    public const RULE_ANALYSIS_ERROR = 'analysis_error';

    public function __construct(
        public readonly string $modelClass,
        public readonly string $table,
        public readonly ?string $column,
        public readonly string $rule,
        public readonly string $message,
    ) {}

    public function toArray(): array
    {
        return [
            'model' => $this->modelClass,
            'table' => $this->table,
            'column' => $this->column,
            'rule' => $this->rule,
            'message' => $this->message,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/RulesTest.php`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Schema/DriftFinding.php tests/Unit/Schema/RulesTest.php
git commit -m "feat: add DriftFinding value object for schema drift guardrail"
```

---

### Task 2: DriftRule interface + FillableColumnsExistRule

**Files:**
- Create: `app/Services/Schema/Rules/DriftRule.php`
- Create: `app/Services/Schema/Rules/FillableColumnsExistRule.php`
- Test: `tests/Unit/Schema/RulesTest.php` (extend)

- [ ] **Step 1: Write the failing test**

Add these methods to `tests/Unit/Schema/RulesTest.php` (and add the imports
`use App\Services\Schema\Rules\FillableColumnsExistRule;` and
`use Illuminate\Database\Eloquent\Model;` at the top):

```php
    public function test_fillable_rule_flags_attribute_without_column(): void
    {
        $model = new class extends Model {
            protected $table = 'widgets';
            protected $fillable = ['name', 'ghost', 'meta->locale'];
        };

        $columns = [
            'name' => ['name' => 'name'],
            'meta' => ['name' => 'meta'],
        ];

        $findings = (new FillableColumnsExistRule())->check($model, $columns);

        $this->assertCount(1, $findings);
        $this->assertSame('ghost', $findings[0]->column);
        $this->assertSame(DriftFinding::RULE_MISSING_FILLABLE, $findings[0]->rule);
    }

    public function test_fillable_rule_passes_clean_model(): void
    {
        $model = new class extends Model {
            protected $table = 'widgets';
            protected $fillable = ['name'];
        };

        $findings = (new FillableColumnsExistRule())->check($model, ['name' => ['name' => 'name']]);

        $this->assertSame([], $findings);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/RulesTest.php`
Expected: FAIL — `Class "App\Services\Schema\Rules\FillableColumnsExistRule" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Schema/Rules/DriftRule.php`:

```php
<?php

namespace App\Services\Schema\Rules;

use App\Services\Schema\DriftFinding;
use Illuminate\Database\Eloquent\Model;

interface DriftRule
{
    /**
     * @param  array<string, array<string, mixed>>  $columns  keyed by column name
     * @return DriftFinding[]
     */
    public function check(Model $model, array $columns): array;
}
```

Create `app/Services/Schema/Rules/FillableColumnsExistRule.php`:

```php
<?php

namespace App\Services\Schema\Rules;

use App\Services\Schema\DriftFinding;
use Illuminate\Database\Eloquent\Model;

class FillableColumnsExistRule implements DriftRule
{
    public function check(Model $model, array $columns): array
    {
        $findings = [];

        foreach ($model->getFillable() as $attribute) {
            // Support JSON-path mass assignment like 'meta->locale' — check base column.
            $base = explode('->', $attribute)[0];

            if (! array_key_exists($base, $columns)) {
                $findings[] = new DriftFinding(
                    modelClass: $model::class,
                    table: $model->getTable(),
                    column: $attribute,
                    rule: DriftFinding::RULE_MISSING_FILLABLE,
                    message: "Fillable attribute '{$attribute}' has no matching column in table '{$model->getTable()}'.",
                );
            }
        }

        return $findings;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/RulesTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Schema/Rules/DriftRule.php app/Services/Schema/Rules/FillableColumnsExistRule.php tests/Unit/Schema/RulesTest.php
git commit -m "feat: add DriftRule interface + fillable column existence rule"
```

---

### Task 3: CastColumnsExistRule

**Files:**
- Create: `app/Services/Schema/Rules/CastColumnsExistRule.php`
- Test: `tests/Unit/Schema/RulesTest.php` (extend)

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Schema/RulesTest.php` (add import `use App\Services\Schema\Rules\CastColumnsExistRule;`):

```php
    public function test_cast_rule_flags_cast_without_column(): void
    {
        $model = new class extends Model {
            protected $table = 'widgets';
            protected $casts = ['meta' => 'array', 'ghost' => 'array'];
        };

        $columns = [
            'id' => ['name' => 'id'],
            'meta' => ['name' => 'meta'],
        ];

        $findings = (new CastColumnsExistRule())->check($model, $columns);

        $this->assertCount(1, $findings);
        $this->assertSame('ghost', $findings[0]->column);
        $this->assertSame(DriftFinding::RULE_MISSING_CAST, $findings[0]->rule);
    }
```

Note: `getCasts()` also returns the auto primary-key cast (`id => int`); the `id`
column above is present so it does not produce a finding.

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/RulesTest.php`
Expected: FAIL — `Class "App\Services\Schema\Rules\CastColumnsExistRule" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Schema/Rules/CastColumnsExistRule.php`:

```php
<?php

namespace App\Services\Schema\Rules;

use App\Services\Schema\DriftFinding;
use Illuminate\Database\Eloquent\Model;

class CastColumnsExistRule implements DriftRule
{
    public function check(Model $model, array $columns): array
    {
        $findings = [];

        foreach (array_keys($model->getCasts()) as $attribute) {
            if (! array_key_exists($attribute, $columns)) {
                $findings[] = new DriftFinding(
                    modelClass: $model::class,
                    table: $model->getTable(),
                    column: $attribute,
                    rule: DriftFinding::RULE_MISSING_CAST,
                    message: "Cast attribute '{$attribute}' has no matching column in table '{$model->getTable()}'.",
                );
            }
        }

        return $findings;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/RulesTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Schema/Rules/CastColumnsExistRule.php tests/Unit/Schema/RulesTest.php
git commit -m "feat: add cast column existence rule"
```

---

### Task 4: UnsettableNotNullRule

**Files:**
- Create: `app/Services/Schema/Rules/UnsettableNotNullRule.php`
- Test: `tests/Unit/Schema/RulesTest.php` (extend)

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Schema/RulesTest.php` (add import `use App\Services\Schema\Rules\UnsettableNotNullRule;`):

```php
    public function test_unsettable_not_null_rule_flags_only_risky_column(): void
    {
        $model = new class extends Model {
            protected $table = 'widgets';
            protected $fillable = ['name'];
        };

        $columns = [
            'id' => ['name' => 'id', 'nullable' => false, 'default' => null, 'auto_increment' => true],
            'name' => ['name' => 'name', 'nullable' => true, 'default' => null, 'auto_increment' => false],
            'status' => ['name' => 'status', 'nullable' => false, 'default' => 'new', 'auto_increment' => false],
            'required_col' => ['name' => 'required_col', 'nullable' => false, 'default' => null, 'auto_increment' => false],
            'created_at' => ['name' => 'created_at', 'nullable' => false, 'default' => null, 'auto_increment' => false],
        ];

        $findings = (new UnsettableNotNullRule())->check($model, $columns);

        $this->assertCount(1, $findings);
        $this->assertSame('required_col', $findings[0]->column);
        $this->assertSame(DriftFinding::RULE_UNSETTABLE_NOT_NULL, $findings[0]->rule);
    }
```

This asserts the exclusions: `id` (auto-increment), `name` (nullable), `status`
(has default), `created_at` (timestamp) are all skipped; only `required_col` flags.

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/RulesTest.php`
Expected: FAIL — `Class "App\Services\Schema\Rules\UnsettableNotNullRule" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Schema/Rules/UnsettableNotNullRule.php`:

```php
<?php

namespace App\Services\Schema\Rules;

use App\Services\Schema\DriftFinding;
use Illuminate\Database\Eloquent\Model;

class UnsettableNotNullRule implements DriftRule
{
    public function check(Model $model, array $columns): array
    {
        $findings = [];
        $fillable = $model->getFillable();
        $timestamps = array_filter([$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()]);

        foreach ($columns as $name => $meta) {
            $isNotNull = ($meta['nullable'] ?? true) === false;
            $hasDefault = ($meta['default'] ?? null) !== null;
            $isAuto = (bool) ($meta['auto_increment'] ?? false);

            if (! $isNotNull || $hasDefault || $isAuto) {
                continue;
            }
            if (in_array($name, $timestamps, true) || in_array($name, $fillable, true)) {
                continue;
            }

            $findings[] = new DriftFinding(
                modelClass: $model::class,
                table: $model->getTable(),
                column: $name,
                rule: DriftFinding::RULE_UNSETTABLE_NOT_NULL,
                message: "Column '{$name}' is NOT NULL with no default and is not fillable; inserts will fail unless it is set elsewhere (observer/hook). Allowlist it in config/schema_drift.php if intentional.",
            );
        }

        return $findings;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/RulesTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Schema/Rules/UnsettableNotNullRule.php tests/Unit/Schema/RulesTest.php
git commit -m "feat: add unsettable NOT NULL column rule"
```

---

### Task 5: SchemaDriftAnalyzer

**Files:**
- Create: `app/Services/Schema/SchemaDriftAnalyzer.php`
- Test: `tests/Unit/Schema/SchemaDriftAnalyzerTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Schema/SchemaDriftAnalyzerTest.php`. It defines named fixture
models at the bottom (the analyzer instantiates models by class-string, so
anonymous classes cannot be used here) and builds their tables in `setUp`:

```php
<?php

namespace Tests\Unit\Schema;

use App\Services\Schema\DriftFinding;
use App\Services\Schema\SchemaDriftAnalyzer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaDriftAnalyzerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('drift_clean', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->json('meta')->nullable();
        });
        Schema::create('drift_fillable', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
        });
        Schema::create('drift_notnull', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('required_col'); // NOT NULL, no default, not fillable
        });
    }

    protected function tearDown(): void
    {
        foreach (['drift_clean', 'drift_fillable', 'drift_notnull'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_clean_model_yields_no_findings(): void
    {
        $findings = (new SchemaDriftAnalyzer())->analyze([DriftCleanModel::class]);

        $this->assertTrue($findings->isEmpty(), $findings->map->message->implode("\n"));
    }

    public function test_flags_fillable_without_column(): void
    {
        $findings = (new SchemaDriftAnalyzer())->analyze([DriftFillableModel::class]);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftFinding::RULE_MISSING_FILLABLE, $findings->first()->rule);
        $this->assertSame('ghost', $findings->first()->column);
    }

    public function test_flags_unsettable_not_null(): void
    {
        $findings = (new SchemaDriftAnalyzer())->analyze([DriftNotNullModel::class]);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftFinding::RULE_UNSETTABLE_NOT_NULL, $findings->first()->rule);
        $this->assertSame('required_col', $findings->first()->column);
    }

    public function test_flags_missing_table(): void
    {
        $findings = (new SchemaDriftAnalyzer())->analyze([DriftMissingTableModel::class]);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftFinding::RULE_MISSING_TABLE, $findings->first()->rule);
    }

    public function test_records_analysis_error_without_crashing(): void
    {
        $findings = (new SchemaDriftAnalyzer())->analyze([DriftBoomModel::class]);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftFinding::RULE_ANALYSIS_ERROR, $findings->first()->rule);
    }

    public function test_ignore_config_suppresses_finding(): void
    {
        config(['schema_drift.ignore' => [DriftFillableModel::class => ['ghost']]]);

        $findings = (new SchemaDriftAnalyzer())->analyze([DriftFillableModel::class]);

        $this->assertTrue($findings->isEmpty());
    }

    public function test_ignore_models_config_skips_model(): void
    {
        config(['schema_drift.ignore_models' => [DriftMissingTableModel::class]]);

        $findings = (new SchemaDriftAnalyzer())->analyze([DriftMissingTableModel::class]);

        $this->assertTrue($findings->isEmpty());
    }
}

class DriftCleanModel extends Model
{
    protected $table = 'drift_clean';
    public $timestamps = false;
    protected $fillable = ['name'];
    protected $casts = ['meta' => 'array'];
}

class DriftFillableModel extends Model
{
    protected $table = 'drift_fillable';
    public $timestamps = false;
    protected $fillable = ['name', 'ghost'];
}

class DriftNotNullModel extends Model
{
    protected $table = 'drift_notnull';
    public $timestamps = false;
    protected $fillable = ['name'];
}

class DriftMissingTableModel extends Model
{
    protected $table = 'drift_missing_table_xyz';
    public $timestamps = false;
}

class DriftBoomModel extends Model
{
    protected $table = 'drift_boom';

    public function __construct(array $attributes = [])
    {
        throw new \RuntimeException('boom');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/SchemaDriftAnalyzerTest.php`
Expected: FAIL — `Class "App\Services\Schema\SchemaDriftAnalyzer" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Schema/SchemaDriftAnalyzer.php`:

```php
<?php

namespace App\Services\Schema;

use App\Services\Schema\Rules\CastColumnsExistRule;
use App\Services\Schema\Rules\DriftRule;
use App\Services\Schema\Rules\FillableColumnsExistRule;
use App\Services\Schema\Rules\UnsettableNotNullRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class SchemaDriftAnalyzer
{
    /** @var DriftRule[] */
    protected array $rules;

    /** @param DriftRule[]|null $rules */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? [
            new FillableColumnsExistRule(),
            new CastColumnsExistRule(),
            new UnsettableNotNullRule(),
        ];
    }

    /**
     * @param  class-string[]|null  $models  Explicit list (tests); null = discover all.
     * @return Collection<int, DriftFinding>
     */
    public function analyze(?array $models = null): Collection
    {
        $config = config('schema_drift', ['ignore' => [], 'ignore_models' => []]);
        $ignoreModels = $config['ignore_models'] ?? [];
        $ignore = $config['ignore'] ?? [];

        $findings = collect();

        foreach ($models ?? $this->discoverModels() as $class) {
            if (in_array($class, $ignoreModels, true)) {
                continue;
            }

            try {
                $model = new $class();

                if (! $model instanceof Model) {
                    continue;
                }

                $table = $model->getTable();
                $columns = $this->columnsFor($table);

                if ($columns === []) {
                    $findings->push(new DriftFinding(
                        modelClass: $class,
                        table: $table,
                        column: null,
                        rule: DriftFinding::RULE_MISSING_TABLE,
                        message: "Model table '{$table}' does not exist in the schema.",
                    ));

                    continue;
                }

                foreach ($this->rules as $rule) {
                    foreach ($rule->check($model, $columns) as $finding) {
                        $findings->push($finding);
                    }
                }
            } catch (Throwable $e) {
                $findings->push(new DriftFinding(
                    modelClass: $class,
                    table: '',
                    column: null,
                    rule: DriftFinding::RULE_ANALYSIS_ERROR,
                    message: "Could not analyze model: {$e->getMessage()}",
                ));
            }
        }

        return $findings
            ->reject(fn (DriftFinding $f) => $this->isIgnored($f, $ignore))
            ->values();
    }

    /** @return array<string, array<string, mixed>> */
    protected function columnsFor(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = [];
        foreach (Schema::getColumns($table) as $col) {
            $columns[$col['name']] = $col;
        }

        return $columns;
    }

    /** @return class-string[] */
    protected function discoverModels(): array
    {
        $classes = [];

        foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
            $class = $this->classFromFile($file);

            if ($class === null || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $ctor = $reflection->getConstructor();
            if ($ctor && $ctor->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    protected function classFromFile(SplFileInfo $file): ?string
    {
        $path = str_replace(['\\', '/'], '\\', $file->getRealPath());
        $appPath = str_replace(['\\', '/'], '\\', app_path());

        $relative = substr($path, strlen($appPath) + 1, -strlen('.php'));
        $class = 'App\\' . $relative;

        return class_exists($class) ? $class : null;
    }

    protected function isIgnored(DriftFinding $finding, array $ignore): bool
    {
        foreach ($ignore[$finding->modelClass] ?? [] as $entry) {
            if ($entry === $finding->column || $entry === 'rule:' . $finding->rule) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --no-coverage tests/Unit/Schema/SchemaDriftAnalyzerTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Schema/SchemaDriftAnalyzer.php tests/Unit/Schema/SchemaDriftAnalyzerTest.php
git commit -m "feat: add SchemaDriftAnalyzer (discovery, rules orchestration, ignore filtering)"
```

---

### Task 6: Exception config

**Files:**
- Create: `config/schema_drift.php`

- [ ] **Step 1: Create the config file**

Create `config/schema_drift.php`:

```php
<?php

return [

    /*
    | Legitimate exceptions, keyed by model class. Each value is a list of either
    | a column name (suppresses any finding for that column) or 'rule:<rule_name>'
    | (suppresses all findings of that rule for the model). ALWAYS add a comment
    | explaining why an entry is here — the allowlist must not become a dumping ground.
    */
    'ignore' => [
        // App\Models\Example::class => ['legacy_col', 'rule:unsettable_not_null'],
    ],

    /*
    | Models excluded from the audit entirely (database views, unmanaged tables).
    */
    'ignore_models' => [
        // App\Models\SomeView::class,
    ],

];
```

- [ ] **Step 2: Verify config loads**

Run: `php artisan config:clear && php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); var_dump(config('schema_drift'));"`
Expected: dumps an array with `ignore` and `ignore_models` keys.

- [ ] **Step 3: Commit**

```bash
git add config/schema_drift.php
git commit -m "feat: add schema_drift exception allowlist config"
```

---

### Task 7: schema:audit-drift command

**Files:**
- Create: `app/Console/Commands/SchemaAuditDriftCommand.php`
- Test: `tests/Feature/Schema/SchemaAuditDriftCommandTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Schema/SchemaAuditDriftCommandTest.php`. It swaps the
analyzer for a stub so the command is tested independently of real model state:

```php
<?php

namespace Tests\Feature\Schema;

use App\Services\Schema\DriftFinding;
use App\Services\Schema\SchemaDriftAnalyzer;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SchemaAuditDriftCommandTest extends TestCase
{
    public function test_exits_zero_and_reports_clean_when_no_findings(): void
    {
        $this->swapAnalyzer(collect());

        $this->artisan('schema:audit-drift')
            ->expectsOutputToContain('No schema drift detected.')
            ->assertExitCode(0);
    }

    public function test_exits_one_and_lists_findings(): void
    {
        $this->swapAnalyzer(collect([
            new DriftFinding('App\\Models\\Foo', 'foos', 'ghost', DriftFinding::RULE_MISSING_FILLABLE, 'msg'),
        ]));

        $this->artisan('schema:audit-drift')
            ->expectsOutputToContain('ghost')
            ->assertExitCode(1);
    }

    private function swapAnalyzer(Collection $findings): void
    {
        $this->app->instance(SchemaDriftAnalyzer::class, new class($findings) extends SchemaDriftAnalyzer {
            public function __construct(private Collection $stub)
            {
                parent::__construct();
            }

            public function analyze(?array $models = null): Collection
            {
                return $this->stub;
            }
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --no-coverage tests/Feature/Schema/SchemaAuditDriftCommandTest.php`
Expected: FAIL — command `schema:audit-drift` is not defined.

- [ ] **Step 3: Write minimal implementation**

Create `app/Console/Commands/SchemaAuditDriftCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Schema\DriftFinding;
use App\Services\Schema\SchemaDriftAnalyzer;
use Illuminate\Console\Command;

class SchemaAuditDriftCommand extends Command
{
    protected $signature = 'schema:audit-drift {--json} {--rule=}';

    protected $description = 'Audit Eloquent models for schema drift (fillable/casts without columns, unsettable NOT NULL columns, missing tables).';

    public function handle(SchemaDriftAnalyzer $analyzer): int
    {
        $findings = $analyzer->analyze();

        if ($rule = $this->option('rule')) {
            $findings = $findings->where('rule', $rule)->values();
        }

        if ($this->option('json')) {
            $this->line($findings->map->toArray()->toJson(JSON_PRETTY_PRINT));

            return $findings->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        if ($findings->isEmpty()) {
            $this->info('No schema drift detected.');

            return self::SUCCESS;
        }

        $this->table(
            ['Model', 'Table', 'Column', 'Rule', 'Message'],
            $findings->map(fn (DriftFinding $f) => [
                class_basename($f->modelClass),
                $f->table,
                $f->column ?? '—',
                $f->rule,
                $f->message,
            ])->all(),
        );

        $this->error("{$findings->count()} schema drift finding(s).");

        return self::FAILURE;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --no-coverage tests/Feature/Schema/SchemaAuditDriftCommandTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SchemaAuditDriftCommand.php tests/Feature/Schema/SchemaAuditDriftCommandTest.php
git commit -m "feat: add schema:audit-drift artisan command"
```

---

### Task 8: CI gate + triage existing drift

**Files:**
- Create: `tests/Feature/Schema/SchemaModelDriftTest.php`
- Modify (as triage requires): new defensive migrations under `database/migrations/` and/or entries in `config/schema_drift.php`.

- [ ] **Step 1: Write the gate test**

Create `tests/Feature/Schema/SchemaModelDriftTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Services\Schema\SchemaDriftAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaModelDriftTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_model_has_schema_drift(): void
    {
        $findings = app(SchemaDriftAnalyzer::class)->analyze();

        $report = $findings
            ->map(fn ($f) => "[{$f->rule}] {$f->modelClass}::{$f->column} — {$f->message}")
            ->implode("\n");

        $this->assertTrue(
            $findings->isEmpty(),
            "Schema drift detected:\n{$report}",
        );
    }
}
```

- [ ] **Step 2: Run the gate to surface existing drift**

Run: `php vendor/bin/phpunit --no-coverage tests/Feature/Schema/SchemaModelDriftTest.php`
Expected: likely FAIL initially, printing a list of real findings (this is the
point of the audit). If it already PASSES, skip to Step 5.

- [ ] **Step 3: Get the full report for triage**

Run: `php artisan migrate:fresh && php artisan schema:audit-drift`
Expected: the same findings as a readable table.

- [ ] **Step 4: Triage every finding (iterate until the gate is green)**

For EACH finding apply this decision rule, then re-run Step 2:

- `missing_fillable_column` / `missing_cast_column` / `missing_table`: almost always
  a real bug. Add a defensive migration that adds the column/table, mirroring the
  pattern in `database/migrations/2026_05_28_100003_align_mission_reports_with_quality_schema.php`
  (guard each add with `if (! Schema::hasColumn(...))`). Example skeleton:

  ```php
  <?php

  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;

  return new class extends Migration {
      public function up(): void
      {
          if (! Schema::hasTable('TABLE')) {
              return;
          }
          Schema::table('TABLE', function (Blueprint $table) {
              if (! Schema::hasColumn('TABLE', 'COLUMN')) {
                  $table->string('COLUMN')->nullable(); // choose type to match the model
              }
          });
      }

      public function down(): void
      {
          if (Schema::hasColumn('TABLE', 'COLUMN')) {
              Schema::table('TABLE', fn (Blueprint $table) => $table->dropColumn('COLUMN'));
          }
      }
  };
  ```

  If the attribute is genuinely virtual (an accessor, not stored), instead add it to
  `config/schema_drift.php` `ignore` with a comment explaining why.

- `unsettable_not_null`: determine whether something populates the column.
  - Set by an observer / `booted()` / DB default that should exist → add the default
    in a migration, or allowlist with `'rule:unsettable_not_null'` (or the column
    name) in `config/schema_drift.php` and a comment naming what sets it.
  - Nothing sets it → make it nullable or give it a default via migration.

- `analysis_error`: the model could not be instantiated. Inspect the message; if the
  model is legitimately special (abstract-like, needs args), add it to `ignore_models`
  with a comment. Otherwise fix the underlying model bug.

- [ ] **Step 5: Confirm the gate passes and run the full suite**

Run: `php vendor/bin/phpunit --no-coverage tests/Feature/Schema/SchemaModelDriftTest.php`
Expected: PASS.

Run: `php vendor/bin/phpunit --no-coverage`
Expected: full suite green (no regressions from any triage migrations).

- [ ] **Step 6: Run PHPStan and Pint on the new code**

Run: `vendor/bin/phpstan analyse --memory-limit=512M --no-progress app/Services/Schema app/Console/Commands/SchemaAuditDriftCommand.php`
Expected: no errors.

Run: `vendor/bin/pint app/Services/Schema app/Console/Commands/SchemaAuditDriftCommand.php config/schema_drift.php`
Expected: files formatted (or already clean).

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/Schema/SchemaModelDriftTest.php config/schema_drift.php database/migrations
git commit -m "feat: add schema drift CI gate + fix/allowlist existing drift"
```

---

## CI integration note

No workflow change is required: `SchemaModelDriftTest` runs inside the existing
`php artisan test` step in `.github/workflows/ci.yml`, so the gate is active once the
test is committed. Optionally, an explicit step `php artisan schema:audit-drift` can be
added after the test step for a readable report in CI logs, but it is redundant with
the gate.

## Self-review

- **Spec coverage:** objective (audit + gate) → Tasks 7 (command/audit) + 8 (gate);
  fillable rule → Task 2; casts rule → Task 3; unsettable NOT NULL → Task 4; missing
  table → Task 5; analyzer/discovery/robustness → Task 5; exception config → Task 6;
  command → Task 7; gate + rollout/triage → Task 8; out-of-scope items (type
  mismatch, FK, raw inserts) intentionally absent. All covered.
- **Placeholders:** none — every code step contains complete code. Task 8 Step 4 is
  iterative triage by design (the specific findings are unknown until the audit runs);
  it provides a concrete decision rule and a complete migration skeleton.
- **Type consistency:** `DriftFinding(modelClass, table, column, rule, message)` and
  its rule constants are used identically across all tasks; `DriftRule::check(Model,
  array): array` and `SchemaDriftAnalyzer::analyze(?array): Collection` signatures
  match every call site (command, gate, tests, stub).
