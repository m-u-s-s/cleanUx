<?php

namespace Tests\Feature\Schema;

use App\Services\Schema\DriftFinding;
use App\Services\Schema\SchemaDriftAnalyzer;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SchemaAuditDriftCommandTest extends TestCase
{
    public function test_exits_zero_and_reports_clean_when_no_findings(): void
    {
        $this->swapAnalyzer(collect());

        $this->artisan('schema:audit-drift')
            ->expectsOutputToContain('No schema drift detected.')
            ->assertExitCode(0);
    }

    public function test_exits_one_and_lists_findings(): void
    {
        $this->swapAnalyzer(collect([
            new DriftFinding('App\\Models\\Foo', 'foos', 'ghost', DriftFinding::RULE_MISSING_FILLABLE, 'msg'),
        ]));

        $this->artisan('schema:audit-drift')
            ->expectsOutputToContain('ghost')
            ->assertExitCode(1);
    }

    private function swapAnalyzer(Collection $findings): void
    {
        $this->app->instance(SchemaDriftAnalyzer::class, new class($findings) extends SchemaDriftAnalyzer
        {
            public function __construct(private Collection $stub)
            {
                parent::__construct();
            }

            public function analyze(?array $models = null): Collection
            {
                return $this->stub;
            }
        });
    }
}
