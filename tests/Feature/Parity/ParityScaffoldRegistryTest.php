<?php

namespace Tests\Feature\Parity;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ParityScaffoldRegistryTest extends TestCase
{
    public function test_scaffold_emits_candidate_modules_excluding_existing(): void
    {
        $this->artisan('parity:scaffold-registry')
            ->assertExitCode(0)
            ->expectsOutputToContain("'mobile' => 'webview'");
    }

    public function test_scaffold_excludes_already_registered_paths(): void
    {
        // The native invoices path /dashboard/client/finance is already in config/parity.php,
        // so the scaffold must NOT emit a candidate for it.
        // We match the exact JSON value line emitted by JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES
        // to avoid false passes on prefix-substring paths like /dashboard/client/finance-x.
        $output = $this->artisan('parity:scaffold-registry --json')
            ->assertExitCode(0);

        $rawOutput = Artisan::output();
        $this->assertStringNotContainsString('"path": "/dashboard/client/finance"', $rawOutput);
    }
}
