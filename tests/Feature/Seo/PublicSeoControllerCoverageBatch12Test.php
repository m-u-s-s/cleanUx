<?php

namespace Tests\Feature\Seo;

use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSeoControllerCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_static_trade_and_provider_urls(): void
    {
        $trade = Trade::factory()->create([
            'slug' => 'cleaning-trade',
            'is_active' => true,
        ]);

        Trade::factory()->create(['is_active' => false]);

        $verifiedUser = User::factory()->employe()->create();
        $verifiedProvider = ProviderProfile::factory()->create([
            'user_id' => $verifiedUser->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $approvedUser = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $approvedUser->id,
            'status' => 'active',
            'verification_status' => 'approved',
        ]);

        // Should not appear: not verified/approved.
        $pendingUser = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $pendingUser->id,
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $response = $this->get(route('seo.sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $response->assertHeader('X-Robots-Tag', 'noindex');

        $body = $response->getContent();

        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString('/providers</loc>', $body);
        $this->assertStringContainsString('/aide</loc>', $body);
        $this->assertStringContainsString('/providers/cleaning-trade</loc>', $body);
        $this->assertStringContainsString('/providers/'.$verifiedProvider->user_id.'</loc>', $body);
        $this->assertStringContainsString('<priority>1.0</priority>', $body);
        $this->assertStringContainsString('</urlset>', $body);
    }

    public function test_robots_txt_returns_expected_directives(): void
    {
        $response = $this->get(route('seo.robots'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $body = $response->getContent();

        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Disallow: /admin/', $body);
        $this->assertStringContainsString('Disallow: /api/', $body);
        $this->assertStringContainsString('Sitemap: ', $body);
        $this->assertStringContainsString('/sitemap.xml', $body);
    }
}
