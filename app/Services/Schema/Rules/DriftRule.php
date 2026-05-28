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
