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
