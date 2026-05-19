<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use App\Services\I18n\LocaleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleResolverTest extends TestCase
{
    use RefreshDatabase;

    protected LocaleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(LocaleResolver::class);
    }

    public function test_supported_codes_includes_new_european_locales(): void
    {
        $codes = $this->resolver->supportedCodes();

        $this->assertContains('fr', $codes);
        $this->assertContains('nl', $codes);
        $this->assertContains('en', $codes);
        $this->assertContains('es', $codes);
        $this->assertContains('it', $codes);
        $this->assertContains('de', $codes);
    }

    public function test_disabled_locales_excluded_from_supported(): void
    {
        config(['i18n.locales.de.enabled' => false]);
        $codes = $this->resolver->supportedCodes();
        $this->assertNotContains('de', $codes);
    }

    public function test_normalize_handles_regional_variants(): void
    {
        $this->assertSame('fr', $this->resolver->normalize('fr_BE'));
        $this->assertSame('fr', $this->resolver->normalize('fr-FR'));
        $this->assertSame('nl', $this->resolver->normalize('nl_NL'));
        $this->assertSame('en', $this->resolver->normalize('en-US'));
        $this->assertSame('it', $this->resolver->normalize('it_IT'));
        $this->assertNull($this->resolver->normalize('zz'));
        $this->assertNull($this->resolver->normalize(null));
    }

    public function test_resolve_for_user_returns_default_when_no_user_locale(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locale' => ''])->save();
        $this->assertSame($this->resolver->default(), $this->resolver->resolveForUser($user->fresh()));
    }

    public function test_resolve_for_user_normalizes_user_locale(): void
    {
        $user = User::factory()->create(['locale' => 'es_ES']);
        $this->assertSame('es', $this->resolver->resolveForUser($user));
    }

    public function test_parse_accept_language_picks_highest_quality_supported(): void
    {
        $picked = $this->resolver->parseAcceptLanguage('zh-CN;q=1.0,it-IT;q=0.9,fr;q=0.5');
        $this->assertSame('it', $picked);
    }

    public function test_available_for_switcher_returns_sorted_locales(): void
    {
        $items = $this->resolver->availableForSwitcher();
        $this->assertNotEmpty($items);
        $priorities = array_column($items, 'priority');
        $sorted = $priorities;
        sort($sorted);
        $this->assertSame($sorted, $priorities);
    }

    public function test_user_persisted_format_returns_bcp47_like(): void
    {
        $this->assertSame('fr_BE', $this->resolver->userPersistedFormat('fr'));
        $this->assertSame('es_ES', $this->resolver->userPersistedFormat('es'));
        $this->assertSame('de_DE', $this->resolver->userPersistedFormat('de'));
    }
}
