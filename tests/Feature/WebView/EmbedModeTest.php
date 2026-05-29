<?php

namespace Tests\Feature\WebView;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class EmbedModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway route that renders the real app layout with a known slot,
        // so we can assert the chrome guard without depending on a feature page.
        Route::middleware('web')->get('/__embed_probe', function () {
            return view('layouts.app', [
                'slot' => new HtmlString('<div data-probe="content">PROBE_OK</div>'),
            ]);
        });
    }

    public function test_embed_param_hides_primary_nav_chrome(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/__embed_probe?embed=1')
            ->assertOk()
            ->assertSee('PROBE_OK', false)
            ->assertDontSee('data-chrome="primary-nav"', false);
    }

    public function test_x_embedded_header_hides_chrome(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/__embed_probe', ['X-Embedded' => '1'])
            ->assertOk()
            ->assertSee('PROBE_OK', false)
            ->assertDontSee('data-chrome="primary-nav"', false);
    }

    public function test_normal_request_renders_navigation_chrome(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/__embed_probe')
            ->assertOk()
            ->assertSee('PROBE_OK', false)
            ->assertSee('data-chrome="primary-nav"', false);
    }
}
