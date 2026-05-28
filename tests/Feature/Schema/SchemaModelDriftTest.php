<?php

namespace Tests\Feature\Schema;

use App\Services\Schema\SchemaDriftAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaModelDriftTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_model_has_schema_drift(): void
    {
        $findings = app(SchemaDriftAnalyzer::class)->analyze();

        $report = $findings
            ->map(fn ($f) => "[{$f->rule}] {$f->modelClass}::{$f->column} — {$f->message}")
            ->implode("\n");

        $this->assertTrue(
            $findings->isEmpty(),
            "Schema drift detected:\n{$report}",
        );
    }
}
