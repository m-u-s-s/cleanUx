<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Safety\Data\MaskedCallSessionData;
use App\Services\Safety\Providers\MockMaskedCallProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 7.2 — MockMaskedCallProvider and MaskedCallSessionData value object.
 */
class MockMaskedCallProviderTest extends TestCase
{
    use RefreshDatabase;
    private MockMaskedCallProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new MockMaskedCallProvider();
    }

    public function test_provider_name_is_mock(): void
    {
        $this->assertSame('mock', $this->provider->name());
    }

    public function test_create_session_returns_session_data_instance(): void
    {
        $result = $this->invokeCreateSession();
        $this->assertInstanceOf(MaskedCallSessionData::class, $result);
    }

    public function test_proxy_phone_number_starts_with_plus32(): void
    {
        $result = $this->invokeCreateSession();
        $this->assertStringStartsWith('+32', $result->proxyPhoneNumber);
    }

    public function test_external_ref_starts_with_mock_prefix(): void
    {
        $result = $this->invokeCreateSession();
        $this->assertStringStartsWith('mock_', $result->externalRef);
    }

    public function test_expires_at_is_in_the_future(): void
    {
        $result = $this->invokeCreateSession();
        $this->assertNotNull($result->expiresAt);
        $this->assertTrue(now()->lt(\Carbon\Carbon::parse($result->expiresAt)));
    }

    public function test_metadata_contains_provider_key(): void
    {
        $result = $this->invokeCreateSession();
        $this->assertSame('mock', $result->metadata['provider']);
    }

    public function test_close_session_does_not_throw(): void
    {
        $this->expectNotToPerformAssertions();
        $this->provider->closeSession('mock_any_ref');
    }

    public function test_session_data_value_object_stores_fields(): void
    {
        $data = new MaskedCallSessionData(
            proxyPhoneNumber: '+32471234567',
            externalRef: 'mock_abc',
            expiresAt: now()->addHours(24)->toIso8601String(),
            metadata: ['provider' => 'mock'],
        );

        $this->assertSame('+32471234567', $data->proxyPhoneNumber);
        $this->assertSame('mock_abc', $data->externalRef);
        $this->assertSame('mock', $data->metadata['provider']);
    }

    public function test_session_data_nullable_fields_default_to_null(): void
    {
        $data = new MaskedCallSessionData(
            proxyPhoneNumber: '+32471234567',
            externalRef: 'mock_abc',
        );

        $this->assertNull($data->expiresAt);
        $this->assertSame([], $data->metadata);
    }

    private function invokeCreateSession(): MaskedCallSessionData
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        return $this->provider->createMaskedSession($caller, $receiver);
    }
}
