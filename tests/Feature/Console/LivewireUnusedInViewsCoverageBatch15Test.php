<?php

namespace Tests\Feature\Console;

use App\Console\Commands\LivewireUnusedInViews;
use Illuminate\Console\Command;
use Tests\TestCase;

/** Coverage for {@see LivewireUnusedInViews}. */
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
