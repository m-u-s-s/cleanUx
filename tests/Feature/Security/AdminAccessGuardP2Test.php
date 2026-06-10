<?php

namespace Tests\Feature\Security;

use App\Livewire\Admin\GestionUtilisateurs;
use App\Livewire\Admin\Risk\RiskCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P2 — EnforcesAdminAccess: component-local defense-in-depth on admin Livewire components.
 * A non-admin reaching an admin component (any path) must get 403; an admin must pass.
 */
class AdminAccessGuardP2Test extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_forbidden_from_admin_component(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)->test(GestionUtilisateurs::class)->assertForbidden();
    }

    public function test_guest_is_forbidden_from_admin_component(): void
    {
        Livewire::test(RiskCenter::class)->assertForbidden();
    }

    public function test_admin_passes_the_guard(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(GestionUtilisateurs::class)->assertOk();
    }
}
