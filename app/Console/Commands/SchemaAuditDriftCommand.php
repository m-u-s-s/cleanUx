<?php

namespace App\Console\Commands;

use App\Services\Schema\DriftFinding;
use App\Services\Schema\SchemaDriftAnalyzer;
use Illuminate\Console\Command;

class SchemaAuditDriftCommand extends Command
{
    protected $signature = 'schema:audit-drift {--json} {--rule=}';

    protected $description = 'Audit Eloquent models for schema drift (fillable/casts without columns, unsettable NOT NULL columns, missing tables).';

    public function handle(SchemaDriftAnalyzer $analyzer): int
    {
        $findings = $analyzer->analyze();

        if ($rule = $this->option('rule')) {
            $findings = $findings->where('rule', $rule)->values();
        }

        if ($this->option('json')) {
            $this->line($findings->map->toArray()->toJson(JSON_PRETTY_PRINT));

            return $findings->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        if ($findings->isEmpty()) {
            $this->info('No schema drift detected.');

            return self::SUCCESS;
        }

        $this->table(
            ['Model', 'Table', 'Column', 'Rule', 'Message'],
            $findings->map(fn (DriftFinding $f) => [
                class_basename($f->modelClass),
                $f->table,
                $f->column ?? '—',
                $f->rule,
                $f->message,
            ])->all(),
        );

        $this->error("{$findings->count()} schema drift finding(s).");

        return self::FAILURE;
    }
}
