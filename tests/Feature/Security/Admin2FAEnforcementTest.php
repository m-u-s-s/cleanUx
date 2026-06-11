<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit HIGH — 2FA admin obligatoire. Quand enforce_2fa_for_admins est actif
 * (défaut sécurisé en prod), un admin sans 2FA confirmée est redirigé vers son
 * profil pour l'activer avant d'accéder à l'espace admin.
 */
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
}
