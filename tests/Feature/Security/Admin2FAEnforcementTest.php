<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Audit HIGH — 2FA admin obligatoire. */
class Admin2FAEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(?string $confirmedAt): User
    {
        return User::factory()->admin()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
            'two_factor_confirmed_at' => $confirmedAt,
        ]);
    }

    public function test_admin_without_confirmed_2fa_is_redirected_when_enforced(): void
    {
        config(['auth.enforce_2fa_for_admins' => true]);

        $this->actingAs($this->admin(null))
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('profile.show'));
    }

    public function test_admin_with_confirmed_2fa_reaches_admin_area(): void
    {
        config(['auth.enforce_2fa_for_admins' => true]);

        $this->actingAs($this->admin(now()->toDateTimeString()))
            ->get(route('admin.dashboard'))
            ->assertSuccessful();
    }

    public function test_disabled_flag_lets_admin_through_without_2fa(): void
    {
        config(['auth.enforce_2fa_for_admins' => false]);

        $this->actingAs($this->admin(null))
            ->get(route('admin.dashboard'))
            ->assertSuccessful();
    }

    /** Anti-loop guard: the page the un-enrolled admin is redirected TO must render, not bounce back into the enforcement middleware. */
    public function test_redirect_target_does_not_loop(): void
    {
        config(['auth.enforce_2fa_for_admins' => true]);

        $admin = $this->admin(null);

        // Blocked admin route bounces to the 2FA-setup page…
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('profile.show'));

        // …and that page is reachable (renders, no second redirect).
        $this->actingAs($admin)
            ->get(route('profile.show'))
            ->assertSuccessful();
    }
}
