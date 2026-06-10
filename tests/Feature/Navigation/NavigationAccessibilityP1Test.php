<?php

namespace Tests\Feature\Navigation;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P1 — navigation accessibility. Locks in the nav links for pages that were reachable only by
 * typing the URL (no menu/button anywhere). Each previously-orphaned page must (a) be a real
 * registered route and (b) be referenced in the relevant nav so users can actually reach it.
 */
class NavigationAccessibilityP1Test extends TestCase
{
    private function navContains(string $bladeRelPath, string $needle): void
    {
        $contents = file_get_contents(resource_path($bladeRelPath));
        $this->assertNotFalse($contents, "missing nav file: $bladeRelPath");
        $this->assertStringContainsString($needle, $contents, "$needle must be linked in $bladeRelPath");
    }

    public function test_client_company_nav_links_to_contracts(): void
    {
        $this->assertTrue(Route::has('client-company.contracts'));
        $this->navContains('views/layouts/client-company.blade.php', "'client-company.contracts'");
    }

    public function test_admin_nav_links_to_feature_flags_manager(): void
    {
        $this->assertTrue(Route::has('admin.feature-flags.manager'));
        $this->navContains('views/navigation-menu.blade.php', "'admin.feature-flags.manager'");
    }

    /**
     * Guard: the previously-validated in-app nav coverage must not regress — these core pages
     * stay referenced in the primary nav menu.
     */
    public function test_core_admin_pages_remain_in_nav(): void
    {
        $nav = file_get_contents(resource_path('views/navigation-menu.blade.php'));
        foreach (['admin.utilisateurs.manage', 'admin.modules', 'admin.orchestration', 'admin.automation', 'admin.b2b.operations'] as $name) {
            $this->assertStringContainsString("'$name'", $nav, "$name must remain in the admin nav");
        }
    }
}
