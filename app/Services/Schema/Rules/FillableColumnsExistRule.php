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
