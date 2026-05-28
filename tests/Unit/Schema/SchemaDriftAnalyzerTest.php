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
        $findings = (new SchemaDriftAnalyzer)->analyze([DriftCleanModel::class]);

        $this->assertTrue($findings->isEmpty(), $findings->map->message->implode("\n"));
    }

    public function test_flags_fillable_without_column(): void
    {
        $findings = (new SchemaDriftAnalyzer)->analyze([DriftFillableModel::class]);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftFinding::RULE_MISSING_FILLABLE, $findings->first()->rule);
        $this->assertSame('ghost', $findings->first()->column);
    }

    public function test_flags_unsettable_not_null(): void
    {
        $findings = (new SchemaDriftAnalyzer)->analyze([DriftNotNullModel::class]);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftFinding::RULE_UNSETTABLE_NOT_NULL, $findings->first()->rule);
        $this->assertSame('required_col', $findings->first()->column);
    }

    public function test_flags_missing_table(): void
    {
        $findings = (new SchemaDriftAnalyzer)->analyze([DriftMissingTableModel::class]);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftFinding::RULE_MISSING_TABLE, $findings->first()->rule);
    }

    public function test_records_analysis_error_without_crashing(): void
    {
        $findings = (new SchemaDriftAnalyzer)->analyze([DriftBoomModel::class]);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftFinding::RULE_ANALYSIS_ERROR, $findings->first()->rule);
    }

    public function test_ignore_config_suppresses_finding(): void
    {
        config(['schema_drift.ignore' => [DriftFillableModel::class => ['ghost']]]);

        $findings = (new SchemaDriftAnalyzer)->analyze([DriftFillableModel::class]);

        $this->assertTrue($findings->isEmpty());
    }

    public function test_ignore_models_config_skips_model(): void
    {
        config(['schema_drift.ignore_models' => [DriftMissingTableModel::class]]);

        $findings = (new SchemaDriftAnalyzer)->analyze([DriftMissingTableModel::class]);

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
