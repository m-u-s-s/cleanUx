<?php

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Adds coverage for the REAL-drill branches of BackupRestoreDrill that the existing BackupRestoreDrillTest leaves untouched: scratch-connection resolution, backup location guards, SQL-dump restore, integrity checks (pass + fail), and the RTO/RPO report. */
class BackupRestoreDrillCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        DB::purge('scratch_drill');

        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function registerScratchConnection(): void
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'scratch_').'.sqlite';
        touch($dbFile);
        $this->tempFiles[] = $dbFile;

        config(['database.connections.scratch_drill' => [
            'driver' => 'sqlite',
            'database' => $dbFile,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        DB::purge('scratch_drill');
    }

    private function writeDump(string $sql): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dump_').'.sql';
        file_put_contents($path, $sql);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_real_drill_restores_dump_and_passes_all_integrity_checks(): void
    {
        $this->registerScratchConnection();

        $dump = $this->writeDump(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT);
            INSERT INTO users (id, name) VALUES (1, 'Alice');
            CREATE TABLE bookings (id INTEGER PRIMARY KEY);
            INSERT INTO bookings (id) VALUES (1);
            CREATE TABLE provider_wallet_transactions (id INTEGER PRIMARY KEY, provider_user_id INTEGER, direction TEXT, amount REAL);
            INSERT INTO provider_wallet_transactions (id, provider_user_id, direction, amount) VALUES (1, 1, 'credit', 80.0);
            INSERT INTO provider_wallet_transactions (id, provider_user_id, direction, amount) VALUES (2, 1, 'debit', 30.0);
            SQL);

        $this->artisan('backup:restore-drill', [
            '--connection' => 'scratch_drill',
            '--backup' => $dump,
        ])
            ->expectsOutputToContain('safe to proceed')
            ->expectsOutputToContain('Restore completed')
            ->assertExitCode(0);
    }

    public function test_real_drill_fails_when_a_spine_table_is_empty(): void
    {
        $this->registerScratchConnection();

        // users present, bookings present, but wallet table empty => FAIL check.
        $dump = $this->writeDump(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY);
            INSERT INTO users (id) VALUES (1);
            CREATE TABLE bookings (id INTEGER PRIMARY KEY);
            INSERT INTO bookings (id) VALUES (1);
            CREATE TABLE provider_wallet_transactions (id INTEGER PRIMARY KEY, provider_user_id INTEGER, direction TEXT, amount REAL);
            SQL);

        $this->artisan('backup:restore-drill', [
            '--connection' => 'scratch_drill',
            '--backup' => $dump,
        ])
            ->expectsOutputToContain('Overall drill result')
            ->assertExitCode(1);
    }

    public function test_real_drill_fails_when_dump_contains_invalid_sql(): void
    {
        $this->registerScratchConnection();

        $dump = $this->writeDump('THIS IS NOT VALID SQL AT ALL;');

        $this->artisan('backup:restore-drill', [
            '--connection' => 'scratch_drill',
            '--backup' => $dump,
        ])
            ->expectsOutputToContain('Restore FAILED')
            ->assertExitCode(1);
    }

    public function test_fails_when_scratch_connection_is_not_defined(): void
    {
        $this->artisan('backup:restore-drill', [
            '--connection' => 'totally_undefined_connection',
        ])
            ->expectsOutputToContain('is not defined')
            ->assertExitCode(1);
    }

    public function test_fails_when_backup_path_does_not_exist(): void
    {
        $this->registerScratchConnection();

        $missing = sys_get_temp_dir().DIRECTORY_SEPARATOR.'no_such_backup_'.uniqid().'.sql';

        $this->artisan('backup:restore-drill', [
            '--connection' => 'scratch_drill',
            '--backup' => $missing,
        ])
            ->expectsOutputToContain('Backup not found')
            ->assertExitCode(1);
    }

    public function test_fails_when_no_backup_can_be_located_automatically(): void
    {
        $this->registerScratchConnection();

        // No --backup and no configured restore path / backups disk => null.
        config(['backup.restore.path' => null]);

        $this->artisan('backup:restore-drill', [
            '--connection' => 'scratch_drill',
        ])
            ->expectsOutputToContain('Backup not found')
            ->assertExitCode(1);
    }
}
