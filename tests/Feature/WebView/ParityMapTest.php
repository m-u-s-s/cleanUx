<?php

namespace Tests\Feature\WebView;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParityMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('parity.modules', [
            ['key' => 'booking', 'title' => 'Réserver', 'icon' => 'calendar-outline', 'path' => '/client/bookings/new', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
            ['key' => 'accounting', 'title' => 'Comptabilité', 'icon' => 'document-text-outline', 'path' => '/admin/accounting', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
            ['key' => 'help', 'title' => 'Aide', 'icon' => 'help-circle-outline', 'path' => '/help', 'web' => 'native', 'mobile' => 'webview', 'roles' => [], 'responsive_verified' => true],
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/parity-map')->assertUnauthorized();
    }

    public function test_client_sees_client_and_public_modules_but_not_admin(): void
    {
        // isClient() uses the legacy `role` column fallback in isClientPersonal():
        // ($this->attributes['role'] ?? null) === 'client'  → true
        // isPlatformAdmin() checks platform_role — not set here → false
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));

        $response = $this->getJson('/api/parity-map')->assertOk();

        $keys = collect($response->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('booking'));
        $this->assertTrue($keys->contains('help'));
        $this->assertFalse($keys->contains('accounting'));
    }

    public function test_each_module_exposes_mobile_delivery_mode(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));

        $booking = collect($this->getJson('/api/parity-map')->json('data'))
            ->firstWhere('key', 'booking');

        $this->assertSame('native', $booking['mobile']);
        $this->assertSame('/client/bookings/new', $booking['path']);
    }
}
