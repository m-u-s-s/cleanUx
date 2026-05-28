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
