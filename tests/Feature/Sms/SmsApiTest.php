<?php

namespace Tests\Feature\Sms;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\Sms\Providers\SmsMockProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(SmsProviderInterface::class, SmsMockProvider::class);
        Config::set('sms.enabled', true);
        Config::set('sms.otp.cooldown_seconds', 0);
    }

    public function test_phone_verify_request_requires_authentication(): void
    {
        $this->postJson('/api/client/phone/verify-request', ['phone' => '+32412345678'])
            ->assertStatus(401);
    }

    public function test_phone_verify_request_validates_phone_field(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/phone/verify-request', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_phone_verify_request_rejects_invalid_e164(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/phone/verify-request', ['phone' => '0412345678'])
            ->assertStatus(422);
    }

    public function test_phone_verify_request_creates_otp_code(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/client/phone/verify-request', [
            'phone' => '+32412345678',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['code_id', 'expires_at', 'phone']);

        $this->assertSame(1, PhoneVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('phone', '+32412345678')
            ->count());
    }

    public function test_phone_verify_confirm_with_correct_code_marks_verified(): void
    {
        $user = User::factory()->client()->create(['phone' => null]);
        Sanctum::actingAs($user);

        PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => '+32499887766',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'purpose' => 'phone_verification',
        ]);

        $response = $this->postJson('/api/client/phone/verify-confirm', [
            'code' => '123456',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'phone' => '+32499887766',
        ]);

        $user->refresh();
        $this->assertSame('+32499887766', $user->phone);
    }

    public function test_phone_verify_confirm_returns_422_for_wrong_code(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => '+32499887766',
            'code_hash' => Hash::make('999999'),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'purpose' => 'phone_verification',
        ]);

        $this->postJson('/api/client/phone/verify-confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_phone_verify_confirm_does_not_leak_other_users_code(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();
        Sanctum::actingAs($bob);

        PhoneVerificationCode::create([
            'user_id' => $alice->id,
            'phone' => '+32499887766',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'purpose' => 'phone_verification',
        ]);

        $this->postJson('/api/client/phone/verify-confirm', ['code' => '123456'])
            ->assertStatus(422);

        $bob->refresh();
        $this->assertNotSame('+32499887766', $bob->phone);
    }
}
