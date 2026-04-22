<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_switch_locale_and_persist_it(): void
    {
        $user = User::factory()->client()->create(['locale' => 'fr_BE']);

        $this->actingAs($user)
            ->from(route('home'))
            ->post(route('locale.switch'), ['locale' => 'nl'])
            ->assertRedirect(route('home'));

        $this->assertSame('nl', session('locale'));
        $this->assertSame('nl_BE', $user->fresh()->locale);
    }

    public function test_notifications_center_uses_selected_session_locale(): void
    {
        $user = User::factory()->client()->create(['locale' => 'fr_BE']);

        $this->actingAs($user)
            ->withSession(['locale' => 'nl'])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Meldingscentrum');
    }
}
