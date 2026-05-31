<?php

namespace Tests\Feature\Parity;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ParityScaffoldRegistryTest extends TestCase
{
    public function test_scaffold_emits_candidate_modules_excluding_existing(): void
    {
        // Hermetic: with an empty registry every navigable area is a candidate, so the
        // command emits webview entries regardless of how complete the live registry is.
        // (Once config/parity.php is fully populated the real registry emits zero — this
        // fixture keeps the test about the command's behavior, not the registry's state.)
        Config::set('parity.modules', []);

        $this->artisan('parity:scaffold-registry')
            ->assertExitCode(0)
            ->expectsOutputToContain("'mobile' => 'webview'");
    }

    public function test_scaffold_excludes_already_registered_paths(): void
    {
        // A path already in the registry must be deduped out of the candidates.
        // /dashboard/client/finance is the native invoices path; register only it, then
        // assert the scaffold does NOT re-emit it. Exact JSON value line match (from
        // JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) avoids false passes on prefix-substring
        // paths like /dashboard/client/finance-x.
        Config::set('parity.modules', [
            ['key' => 'invoices', 'title' => 'Factures', 'icon' => 'receipt-outline', 'path' => '/dashboard/client/finance', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
        ]);

        $this->artisan('parity:scaffold-registry --json')->assertExitCode(0);

        $rawOutput = Artisan::output();
        $this->assertStringNotContainsString('"path": "/dashboard/client/finance"', $rawOutput);
    }
}
