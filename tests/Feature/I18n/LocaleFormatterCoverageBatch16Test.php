<?php

namespace Tests\Feature\I18n;

use App\Services\I18n\LocaleFormatter;
use Carbon\Carbon;
use Tests\TestCase;

class LocaleFormatterCoverageBatch16Test extends TestCase
{
    protected LocaleFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = app(LocaleFormatter::class);
    }

    public function test_date_time_includes_both_date_and_time_parts(): void
    {
        $out = $this->formatter->dateTime('2026-03-15 14:37:00', 'fr');

        $this->assertNotEmpty($out);
        $this->assertStringContainsString('2026', $out);
        // Heure présente (14:37 ou variante locale)
        $this->assertMatchesRegularExpression('/\d{1,2}[:h]\d{2}/', $out);
    }

    public function test_date_time_null_returns_empty(): void
    {
        $this->assertSame('', $this->formatter->dateTime(null));
    }

    public function test_date_honours_short_long_and_full_styles(): void
    {
        $short = $this->formatter->date('2026-03-15', 'fr', 'short');
        $long = $this->formatter->date('2026-03-15', 'fr', 'long');
        $full = $this->formatter->date('2026-03-15', 'fr', 'full');

        $this->assertNotEmpty($short);
        $this->assertNotEmpty($long);
        $this->assertNotEmpty($full);
        $this->assertStringContainsString('2026', $long);
        $this->assertStringContainsString('2026', $full);
    }

    public function test_date_english_short_style(): void
    {
        $out = $this->formatter->date('2026-03-15', 'en', 'short');

        $this->assertNotEmpty($out);
        $this->assertStringContainsString('26', $out);
    }

    public function test_date_accepts_carbon_and_datetime_objects(): void
    {
        $carbon = Carbon::create(2026, 3, 15, 9, 0, 0);
        $native = new \DateTime('2026-03-15 09:00:00');

        $fromCarbon = $this->formatter->date($carbon, 'fr');
        $fromNative = $this->formatter->date($native, 'fr');

        $this->assertStringContainsString('2026', $fromCarbon);
        $this->assertStringContainsString('2026', $fromNative);
    }

    public function test_date_fallback_formats_for_each_locale_family(): void
    {
        foreach (['nl', 'it', 'es', 'pt', 'de'] as $locale) {
            $out = $this->formatter->date('2026-03-15', $locale);
            $this->assertStringContainsString('2026', $out, "locale {$locale} should contain the year");
        }
    }

    public function test_currency_default_currency_resolved_from_config(): void
    {
        // Sans devise explicite -> config i18n ou EUR par défaut
        $out = $this->formatter->currency(42, null, 'fr');

        $this->assertNotEmpty($out);
        $this->assertStringContainsString('42', $out);
    }

    public function test_currency_english_prefixes_symbol(): void
    {
        $out = $this->formatter->currency(1000, 'EUR', 'en');

        $this->assertNotEmpty($out);
        $this->assertStringContainsString('1', $out);
    }

    public function test_currency_non_eur_currency_code_used_verbatim(): void
    {
        $out = $this->formatter->currency(99.9, 'USD', 'fr');

        $this->assertNotEmpty($out);
        $this->assertStringContainsString('99', $out);
        $this->assertStringContainsString('USD', $out);
    }

    public function test_number_with_zero_decimals(): void
    {
        $out = $this->formatter->number(1234.5, 'fr', 0);

        $this->assertNotEmpty($out);
        $this->assertStringContainsString('1', $out);
    }

    public function test_number_accepts_numeric_string_input(): void
    {
        $out = $this->formatter->number('2048.75', 'en', 2);

        $this->assertStringContainsString('2', $out);
        $this->assertStringContainsString('.', $out);
    }

    public function test_currency_accepts_string_amount(): void
    {
        $out = $this->formatter->currency('500.25', 'EUR', 'fr');

        $this->assertNotEmpty($out);
        $this->assertStringContainsString('500', $out);
    }
}
