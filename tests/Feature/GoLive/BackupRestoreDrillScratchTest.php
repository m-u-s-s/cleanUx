<?php

namespace Tests\Feature\GoLive;

use Tests\TestCase;

/** Le restore-drill exige une connexion SCRATCH isolée (jamais la base primaire). */
class BackupRestoreDrillScratchTest extends TestCase
{
    public function test_scratch_connection_is_defined_and_isolated(): void
    {
        $connections = config('database.connections');

        $this->assertArrayHasKey('scratch', $connections, 'La connexion scratch doit être définie pour backup:restore-drill.');
        $this->assertNotEmpty($connections['scratch']['driver'] ?? null);
        $this->assertNotSame('scratch', config('database.default'), 'scratch ne doit jamais être la connexion primaire.');
    }

    public function test_restore_drill_dry_run_is_operable(): void
    {
        // Le garde de sécurité ne doit plus échouer sur "connexion non définie".
        $this->artisan('backup:restore-drill --dry-run')->assertExitCode(0);
    }
}
