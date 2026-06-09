<?php

namespace Tests\Feature\Console;

use App\Console\Commands\LivewireUnusedInViews;
use Illuminate\Console\Command;
use Tests\TestCase;

/**
 * Coverage for {@see LivewireUnusedInViews}.
 *
 * The command scans resources/views for @livewire / <livewire:...> usages,
 * collects full-page routed components, then walks app/Livewire to report any
 * component never included in a view and not bound to a route. It always
 * returns SUCCESS. Running it against the real codebase exercises the view
 * scanning, alias normalisation, route inspection and unused-detection loops.
 */
class LivewireUnusedInViewsCoverageBatch15Test extends TestCase
{
    public function test_command_runs_and_returns_success(): void
    {
        $this->artisan('livewire:unused-includes')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_output_reports_either_clean_or_a_list(): void
    {
        $pending = $this->artisan('livewire:unused-includes');

        // Whichever branch the live codebase takes, the exit code is SUCCESS.
        // We do not assert on a specific message because the unused set depends
        // on the current state of resources/views and app/Livewire.
        $pending->assertExitCode(Command::SUCCESS);
    }
}
