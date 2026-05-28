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
