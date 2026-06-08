<?php

namespace Tests\Feature\Console;

use App\Console\Commands\CleanupReport;
use Illuminate\Console\Command;
use Tests\TestCase;

/**
 * Coverage for {@see CleanupReport}.
 *
 * The 'app:cleanup-report' command scans the application's Livewire components,
 * Blade views and registered routes, then prints a synthetic cleanup report. It
 * has no external dependencies or DB writes, so a single end-to-end run drives
 * handle() plus every private discovery/report helper (component discovery,
 * route-linked detection, view enumeration, alias parsing and the three report
 * sections) against the real application tree.
 */
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
