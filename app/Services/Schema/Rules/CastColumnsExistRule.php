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
