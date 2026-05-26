<?php

namespace Tests\Unit\Services;

use App\Services\Country\CountryConfigService;
use Tests\TestCase;

class CountryConfigServiceTest extends TestCase
{
    private CountryConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CountryConfigService();
    }

    public function test_get_returns_correct_config_for_belgium(): void
    {
        $config = $this->service->get('BE');

        $this->assertSame('Belgique', $config['name']);
        $this->assertSame('EUR', $config['currency']);
        $this->assertSame(0.21, $config['vat_rate']);
        $this->assertSame('+32', $config['phone_prefix']);
        $this->assertSame('Europe/Brussels', $config['timezone']);
    }

    public function test_get_is_case_insensitive(): void
    {
        $upper = $this->service->get('BE');
        $lower = $this->service->get('be');

        $this->assertSame($upper['name'], $lower['name']);
    }

    public function test_get_falls_back_to_belgium_for_unknown_country(): void
    {
        $config = $this->service->get('XX');

        $this->assertSame('Belgique', $config['name']);
    }

    public function test_all_returns_supported_countries(): void
    {
        $all = $this->service->all();

        $this->assertIsArray($all);
        $this->assertNotEmpty($all);
    }

    public function test_supported_returns_array_of_iso_codes(): void
    {
        $supported = $this->service->supported();

        $this->assertContains('BE', $supported);
        $this->assertContains('FR', $supported);
    }

    public function test_get_vat_rate_returns_correct_rate(): void
    {
        $this->assertSame(0.21, $this->service->getVatRate('BE'));
        $this->assertSame(0.20, $this->service->getVatRate('FR'));
    }

    public function test_get_timezone_returns_correct_tz_string(): void
    {
        $this->assertSame('Europe/Brussels', $this->service->getTimezone('BE'));
        $this->assertSame('Europe/Paris', $this->service->getTimezone('FR'));
    }

    public function test_unknown_country_fallback_uses_be_vat_rate(): void
    {
        $this->assertSame(0.21, $this->service->getVatRate('ZZ'));
    }
}
