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
            new FillableColumnsExistRule,
            new CastColumnsExistRule,
            new UnsettableNotNullRule,
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
                $model = new $class;

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
        $class = 'App\\'.$relative;

        return class_exists($class) ? $class : null;
    }

    protected function isIgnored(DriftFinding $finding, array $ignore): bool
    {
        foreach ($ignore[$finding->modelClass] ?? [] as $entry) {
            if ($entry === $finding->column || $entry === 'rule:'.$finding->rule) {
                return true;
            }
        }

        return false;
    }
}
