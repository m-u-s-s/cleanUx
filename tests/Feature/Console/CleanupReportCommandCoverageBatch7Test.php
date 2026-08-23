<?php

namespace Tests\Feature\Console;

use App\Console\Commands\CleanupReport;
use Illuminate\Console\Command;
use Tests\TestCase;

/** Coverage for {@see CleanupReport}. */
class CleanupReportCommandCoverageBatch7Test extends TestCase
{
    public function test_cleanup_report_runs_and_prints_every_section(): void
    {
        $this->artisan('app:cleanup-report')
            ->expectsOutputToContain('=== Livewire ===')
            ->expectsOutputToContain('Total composants :')
            ->expectsOutputToContain('Utilisés dans les routes :')
            ->expectsOutputToContain('Possiblement orphelins :')
            ->expectsOutputToContain('=== Vues ===')
            ->expectsOutputToContain('Total vues Blade :')
            ->expectsOutputToContain('Vues Livewire :')
            ->expectsOutputToContain('=== Routes ===')
            ->expectsOutputToContain('Total routes :')
            ->expectsOutputToContain('Routes GET :')
            ->expectsOutputToContain('Routes POST :')
            ->expectsOutputToContain('Rapport terminé.')
            ->assertExitCode(Command::SUCCESS);
    }
}
