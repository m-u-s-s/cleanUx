# Schema/Model Drift Guardrail — Design

**Date:** 2026-05-28
**Status:** Approved (pending spec review)

## Problem

Eloquent models can declare `$fillable` / `$casts` keys, or write columns, that do
not exist in the migrated database schema. This stays invisible until the code path
runs, then 500s in production.

This bit us concretely in the `completeMission()` repair (commit `7ce96f08`): the
`MissionReport` model had 11 `$fillable` columns absent from the `mission_reports`
table, `mission_events` was missing `actor_user_id`, and `missions` was missing
`quality_score` / `quality_status`. None of it was caught because the completion
path always died earlier (a `TypeError`) and was never executed end-to-end in tests.

The same class of drift very likely exists on other models whose code paths are
lightly or never tested.

## Objective

Both:

1. **Audit now** — scan all ~224 models and surface existing drift so we can fix or
   accept it.
2. **Block the future** — a CI gate that fails the build on any new drift.

## Scope of checks

Full coverage, three rules plus a missing-table check:

1. **Fillable → column existence** — every `$fillable` key must be a real column.
2. **Casts → column existence** — every `$casts` key must be a real column
   (accessor/virtual casts are handled via the exception config).
3. **Unsettable NOT NULL** — a column that is `NOT NULL`, has no default, is not
   auto-increment, is not `created_at` / `updated_at`, **and** is absent from
   `$fillable` is flagged: nothing populates it, so an insert will fail. This is the
   `mission_reports.path` class.
4. **Missing table** — a model whose table does not exist is itself a drift finding.

## Form

A central analyzer with two thin consumers (DRY, isolated, testable):

- A service that produces findings.
- An artisan command (ad-hoc audit, rich output).
- A PHPUnit test (the CI gate).
- A config file for legitimate exceptions.

## Architecture & components

### `App\Services\Schema\SchemaDriftAnalyzer`

The core. Public surface:

```php
public function analyze(): \Illuminate\Support\Collection; // Collection<DriftFinding>
```

Responsibilities:

- **Model discovery** — scan `app/Models/**/*.php`, resolve class names, keep only
  concrete (non-abstract) subclasses of `Illuminate\Database\Eloquent\Model`.
- For each model: read `$fillable` / `$casts` / table name via reflection on an
  instance (most models have no required constructor args).
- Resolve the table's columns and metadata via `Schema::getColumns($table)`
  (Laravel 11 — returns `name`, `nullable`, `default`, `auto_increment`, cross-DB:
  works on the sqlite test DB and MySQL prod).
- Run every registered `DriftRule` against the model, collect `DriftFinding`s.
- Drop findings matched by the exception config.
- **Robustness:** each model is analyzed inside a try/catch. A model that throws
  (e.g., not instantiable, table introspection error) yields an `analysis_error`
  finding instead of aborting the whole run.

### `App\Services\Schema\DriftFinding`

Immutable value object:

```php
public function __construct(
    public string $modelClass,
    public string $table,
    public ?string $column,   // null for table-level findings
    public string $rule,      // missing_fillable_column | missing_cast_column
                              // | unsettable_not_null | missing_table | analysis_error
    public string $message,
) {}

public function toArray(): array; // for --json output
```

### `App\Services\Schema\Rules\*`

One class per rule implementing a shared interface, so new rules (e.g. a future
foreign-key rule) can be added without touching the analyzer:

```php
interface DriftRule
{
    /** @return DriftFinding[] */
    public function check(Model $model, array $columns): array;
}
```

- `FillableColumnsExistRule`
- `CastColumnsExistRule`
- `UnsettableNotNullRule`

(`missing_table` is detected by the analyzer before rules run, since rules need
columns.)

### `App\Console\Commands\SchemaAuditDriftCommand` — `schema:audit-drift`

Thin consumer for ad-hoc audits.

- Options: `--json`, `--rule=` (filter to one rule).
- Output: table grouped by model — Model · table · column · rule · message.
- Exit code **1** if any findings, **0** otherwise.

### `tests/Feature/Schema/SchemaModelDriftTest`

The CI gate. Uses `RefreshDatabase` (the sqlite `:memory:` schema is fully migrated),
calls `SchemaDriftAnalyzer::analyze()`, asserts it is empty, and **prints the full
findings list on failure** so CI shows exactly what to fix. Runs inside the existing
`php artisan test` CI step — no workflow YAML change required.

### `config/schema_drift.php`

```php
return [
    // Legitimate exceptions, per model => columns and/or "rule:<name>" to ignore.
    // Every entry MUST carry a justifying comment (convention, not enforced).
    'ignore' => [
        // App\Models\Foo::class => ['legacy_col', 'rule:unsettable_not_null'],
    ],

    // Models excluded entirely (DB views, unmanaged tables).
    'ignore_models' => [
        // App\Models\SomeView::class,
    ],
];
```

The `UnsettableNotNull` rule is the noisiest (columns are sometimes populated by
model observers / `booted()` hooks that cannot be detected statically), so it relies
most on this allowlist.

## Data flow

```
discover model classes (app/Models)
  → filter to concrete Eloquent models, minus ignore_models
  → for each model (try/catch):
        table = $model->getTable()
        columns = Schema::getColumns(table)        // [] if table missing
        if columns empty → missing_table finding
        else for each rule: rule.check(model, columns)
  → strip findings matched by config 'ignore'
  → Collection<DriftFinding>
```

## Error handling

- Non-instantiable / throwing model → `analysis_error` finding, run continues.
- Table absent → `missing_table` finding (unless model is in `ignore_models`).
- A cast on a genuine virtual attribute → suppressed via `ignore`.

## Testing strategy (TDD)

Unit tests for the analyzer using fixture models + temporary tables:

- fillable key without column → 1 `missing_fillable_column` finding.
- cast key without column → 1 `missing_cast_column` finding.
- NOT NULL column, no default, not in fillable → 1 `unsettable_not_null` finding.
- clean model → 0 findings.
- exception in config → finding suppressed.
- model whose table is absent → `missing_table` finding.
- non-instantiable model → `analysis_error`, no crash.

The feature test `SchemaModelDriftTest` is the gate itself.

## Rollout

1. Ship analyzer + rules + command + test + config.
2. Run `php artisan schema:audit-drift`. Triage every finding:
   - real bug → defensive migration fix (as done for `completeMission`);
   - legitimate / false positive → `config/schema_drift.php` with a justifying comment.
3. Once the list is empty, `SchemaModelDriftTest` passes and the gate protects the
   future automatically.

## Out of scope (v1)

- Column **type** mismatches (cast type vs column type).
- Foreign-key column existence for relations (deferred; the rule interface leaves
  room to add it later).
- Validating raw `DB::table()->insert()` calls (only model-declared schema is checked).
