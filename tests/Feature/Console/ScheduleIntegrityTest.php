<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** D1 — guards against scheduled commands referencing a signature that does not exist. */
class ScheduleIntegrityTest extends TestCase
{
    public function test_all_scheduled_commands_are_registered(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $registered = array_keys(Artisan::all());

        $scheduledCommands = collect($schedule->events())
            ->map(fn ($event) => $event->command)
            ->filter() // drop closure/CallbackEvents which have no command string
            ->map(function (string $command): ?string {
                // e.g. '"php" "artisan" gdpr:execute-erasures --dry-run' → 'gdpr:execute-erasures'
                preg_match('/artisan[\'"]?\s+([\w:.-]+)/', $command, $m);

                return $m[1] ?? null;
            })
            ->filter()
            ->unique()
            ->values();

        $this->assertNotEmpty($scheduledCommands, 'Expected at least one scheduled artisan command');

        // Toutes les commandes fantomes d'un coup : une refonte du noyau en laisse souvent
        // plusieurs derriere elle, et chacune est une tache qui ne tournera jamais.
        $fantomes = array_values(array_diff(
            is_array($scheduledCommands) ? $scheduledCommands : $scheduledCommands->all(),
            is_array($registered) ? $registered : $registered->all(),
        ));

        $this->assertSame([], $fantomes, 'Ces commandes planifiees n existent pas : la tache ne tournera jamais.');
    }

    public function test_gdpr_erasure_command_is_scheduled_and_exists(): void
    {
        $this->assertArrayHasKey('gdpr:execute-erasures', Artisan::all());
    }

    /** B5 — sans borne, un verrou jamais relache tait le moteur pendant 1440 min (le defaut Laravel). */
    public function test_automation_executer_lock_expires_in_ten_minutes(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'automation:executer'));

        $this->assertNotNull($event, 'La tache automation:executer doit etre planifiee.');
        $this->assertSame(10, $event->expiresAt);
    }
}
