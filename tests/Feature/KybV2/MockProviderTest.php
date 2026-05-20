<?php

namespace Tests\Feature\KybV2;

use App\Services\KybV2\Providers\MockBusinessVerificationProvider;
use App\Services\KybV2\Providers\MockSanctionsScreeningProvider;
use Tests\TestCase;

class MockProviderTest extends TestCase
{
    public function test_mock_verifies_known_siret_with_payload(): void
    {
        $r = (new MockBusinessVerificationProvider())->verifyIdentifier('siret', '12345678900012', 'FR');
        $this->assertTrue($r->success);
        $this->assertSame('Acme Cleaning SARL', $r->payload['legal_name']);
        $this->assertSame('SARL', $r->payload['legal_form']);
    }

    public function test_mock_forced_failure_with_FAIL_prefix(): void
    {
        $r = (new MockBusinessVerificationProvider())->verifyIdentifier('siret', 'FAIL-123', 'FR');
        $this->assertFalse($r->success);
        $this->assertSame('mock_forced_failure', $r->error);
    }

    public function test_mock_returns_generic_success_for_digit_only_identifier(): void
    {
        $r = (new MockBusinessVerificationProvider())->verifyIdentifier('siren', '987654321', 'FR');
        $this->assertTrue($r->success);
        $this->assertStringStartsWith('Generic Business', $r->payload['legal_name']);
    }

    public function test_mock_vat_validates_format_strict(): void
    {
        $provider = new MockBusinessVerificationProvider();
        $this->assertTrue($provider->verifyVatId('FR12345678900')->success);
        $this->assertFalse($provider->verifyVatId('invalid')->success);
        $this->assertFalse($provider->verifyVatId('FAIL-VAT')->success);
    }

    public function test_mock_sanctions_screening_detects_sanctioned_tokens(): void
    {
        $screener = new MockSanctionsScreeningProvider();
        $clean = $screener->screen('Acme Cleaning SARL', 'eu');
        $this->assertFalse($clean->hasMatch);

        $match = $screener->screen('Sanctioned Holdings Ltd', 'eu');
        $this->assertTrue($match->hasMatch);
        $this->assertSame(1, $match->matchCount);
    }

    public function test_mock_sanctions_clear_for_normal_name(): void
    {
        $screener = new MockSanctionsScreeningProvider();
        $r = $screener->screen('Acme Belgian Cleaning BVBA', 'us_ofac');
        $this->assertFalse($r->hasMatch);
        $this->assertSame(0, $r->matchCount);
    }
}
