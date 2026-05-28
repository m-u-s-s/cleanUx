<?php

namespace Tests\Unit\Schema;

use App\Services\Schema\DriftFinding;
use App\Services\Schema\Rules\CastColumnsExistRule;
use App\Services\Schema\Rules\FillableColumnsExistRule;
use Illuminate\Database\Eloquent\Model;
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
}
