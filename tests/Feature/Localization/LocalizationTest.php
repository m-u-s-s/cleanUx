<?php

namespace Tests\Feature\Localization;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Services\Localization\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────
    // SetLocale middleware - résolution de la langue
    // ──────────────────────────────────────────────────────

    public function test_query_param_lang_takes_priority(): void
    {
        $this->get('/?lang=nl');
        $this->assertSame('nl', App::getLocale());
    }

    public function test_invalid_query_lang_is_ignored(): void
    {
        $this->get('/?lang=xyz');
        $this->assertContains(App::getLocale(), SetLocale::SUPPORTED);
    }

    public function test_authenticated_user_locale_is_used(): void
    {
        $user = User::factory()->create(['locale' => 'nl']);
        $this->actingAs($user)->get('/');

        $this->assertSame('nl', App::getLocale());
    }

    public function test_accept_language_header_is_detected(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'nl-BE,nl;q=0.9,fr;q=0.8',
        ])->get('/');

        $this->assertSame('nl', App::getLocale());
    }

    public function test_accept_language_quality_is_respected(): void
    {
        // Avec quality, fr est préféré à nl
        $response = $this->withHeaders([
            'Accept-Language' => 'nl;q=0.5,fr;q=0.9',
        ])->get('/');

        $this->assertSame('fr', App::getLocale());
    }

    public function test_default_falls_back_to_french(): void
    {
        $this->get('/');
        $this->assertSame('fr', App::getLocale());
    }

    // ──────────────────────────────────────────────────────
    // LocaleController - changement de langue
    // ──────────────────────────────────────────────────────

    public function test_authenticated_user_can_change_language(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $response = $this->actingAs($user)
            ->from('/')
            ->post('/locale', ['locale' => 'en']);

        $response->assertRedirect('/');
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $response = $this->actingAs($user)
            ->from('/')
            ->post('/locale', ['locale' => 'klingon']);

        $response->assertRedirect('/');
        $this->assertSame('fr', $user->fresh()->locale);
    }

    public function test_anonymous_visitor_can_change_language_via_session(): void
    {
        $response = $this->from('/')
            ->post('/locale', ['locale' => 'nl']);

        $response->assertRedirect('/');
        $this->assertSame('nl', session('locale'));
    }

    // ──────────────────────────────────────────────────────
    // Money formatting
    // ──────────────────────────────────────────────────────

    public function test_money_formats_eur_with_french_locale(): void
    {
        App::setLocale('fr');
        $formatted = app(Money::class)->format(1234.56, 'EUR');

        // Doit contenir 1 234,56 et le symbole €
        $this->assertStringContainsString('€', $formatted);
        $this->assertMatchesRegularExpression('/1[\s\xc2\xa0]?234[,.]56/', $formatted);
    }

    public function test_money_formats_usd_with_english_locale(): void
    {
        App::setLocale('en');
        $formatted = app(Money::class)->format(1234.56, 'USD');

        $this->assertStringContainsString('$', $formatted);
        $this->assertStringContainsString('1,234.56', $formatted);
    }

    public function test_money_falls_back_to_eur_for_unknown_currency(): void
    {
        $formatted = app(Money::class)->format(100, 'XYZ');
        // Doit pas crasher, doit retourner du formatage
        $this->assertNotEmpty($formatted);
    }

    public function test_money_supported_list_includes_eur_usd_gbp(): void
    {
        $list = app(Money::class)->supportedList();
        $codes = array_column($list, 'code');

        $this->assertContains('EUR', $codes);
        $this->assertContains('USD', $codes);
        $this->assertContains('GBP', $codes);
    }

    public function test_money_symbol_returns_correct_symbol(): void
    {
        $svc = app(Money::class);
        $this->assertSame('€', $svc->symbol('EUR'));
        $this->assertSame('$', $svc->symbol('USD'));
        $this->assertSame('£', $svc->symbol('GBP'));
    }

    // ──────────────────────────────────────────────────────
    // Money conversion
    // ──────────────────────────────────────────────────────

    public function test_conversion_same_currency_returns_amount_unchanged(): void
    {
        $this->assertSame(100.0, app(Money::class)->convert(100, 'EUR', 'EUR'));
    }

    public function test_conversion_uses_seeded_rate(): void
    {
        // La migration seed un taux EUR→USD = 1.087
        $converted = app(Money::class)->convert(100, 'EUR', 'USD');

        $this->assertEqualsWithDelta(108.7, $converted, 0.01);
    }

    public function test_conversion_inverse_rate_works(): void
    {
        // EUR→USD = 1.087, donc USD→EUR ≈ 0.92
        $converted = app(Money::class)->convert(100, 'USD', 'EUR');

        $this->assertEqualsWithDelta(91.99, $converted, 0.5);
    }

    public function test_conversion_fallback_when_no_rate_returns_amount(): void
    {
        // Pas de taux EUR→XYZ
        $converted = app(Money::class)->convert(100, 'EUR', 'XYZ');
        $this->assertSame(100.0, $converted);
    }

    // ──────────────────────────────────────────────────────
    // Translations loaded
    // ──────────────────────────────────────────────────────

    public function test_translations_are_loaded_for_all_locales(): void
    {
        foreach (['fr', 'nl', 'en'] as $locale) {
            App::setLocale($locale);
            $translation = trans('app.account');
            $this->assertNotSame('app.account', $translation, "Translation for 'app.account' not loaded in {$locale}");
        }
    }

    public function test_status_translations_in_french(): void
    {
        App::setLocale('fr');
        $this->assertSame('Confirmé', trans('app.status.confirmed'));
        $this->assertSame('Terminé', trans('app.status.completed'));
    }

    public function test_status_translations_in_dutch(): void
    {
        App::setLocale('nl');
        $this->assertSame('Bevestigd', trans('app.status.confirmed'));
        $this->assertSame('Voltooid', trans('app.status.completed'));
    }
}
