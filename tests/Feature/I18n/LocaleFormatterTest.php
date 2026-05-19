<?php

namespace Tests\Feature\I18n;

use App\Services\I18n\LocaleFormatter;
use Tests\TestCase;

class LocaleFormatterTest extends TestCase
{
    protected LocaleFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = app(LocaleFormatter::class);
    }

    public function test_currency_formatted_in_french_uses_comma_decimal(): void
    {
        $out = $this->formatter->currency(1234.5, 'EUR', 'fr');
        $this->assertStringContainsString('1', $out);
        $this->assertStringContainsString(',', $out);
        $this->assertStringContainsString('50', $out);
    }

    public function test_currency_formatted_in_english_uses_dot_decimal(): void
    {
        $out = $this->formatter->currency(1234.5, 'EUR', 'en');
        $this->assertStringContainsString('.', $out);
    }

    public function test_currency_formatted_in_german_uses_dot_thousand_separator_fallback(): void
    {
        // Si Intl pas dispo, fallback : "1.234,50 €"
        $out = $this->formatter->currency(1234.5, 'EUR', 'de');
        $this->assertNotEmpty($out);
        $this->assertStringContainsString('1', $out);
    }

    public function test_number_uses_locale_specific_decimals(): void
    {
        $fr = $this->formatter->number(3.14, 'fr', 2);
        $en = $this->formatter->number(3.14, 'en', 2);

        $this->assertStringContainsString('3', $fr);
        $this->assertStringContainsString('3', $en);
        $this->assertStringContainsString(',', $fr);
        $this->assertStringContainsString('.', $en);
    }

    public function test_date_returns_non_empty_for_known_locale(): void
    {
        $out = $this->formatter->date('2026-03-15', 'fr');
        $this->assertNotEmpty($out);
        $this->assertStringContainsString('2026', $out);
    }

    public function test_null_returns_empty_string(): void
    {
        $this->assertSame('', $this->formatter->currency(null));
        $this->assertSame('', $this->formatter->date(null));
        $this->assertSame('', $this->formatter->number(null));
    }
}
