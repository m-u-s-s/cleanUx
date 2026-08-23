<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\VerifyPhone;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/** OTP téléphone câblé à l'auth web : envoi du code, vérification (pose phone_verified_at), et garde optionnelle (gated) qui force la vérification. */
class PhoneOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedActiveCode(User $user, string $code, string $phone = '+32470000000'): void
    {
        PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'purpose' => 'phone_verification',
        ]);
    }

    public function test_user_has_verified_phone_helper(): void
    {
        $user = User::factory()->create(['phone_verified_at' => null]);
        $this->assertFalse($user->hasVerifiedPhone());

        $user->forceFill(['phone_verified_at' => now()])->save();
        $this->assertTrue($user->fresh()->hasVerifiedPhone());
    }

    public function test_send_code_dispatches_otp(): void
    {
        $user = User::factory()->client()->create();
        $this->actingAs($user);

        Livewire::test(VerifyPhone::class)
            ->set('phone', '+32470000000')
            ->call('sendCode')
            ->assertSet('codeSent', true)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('phone_verification_codes', ['user_id' => $user->id]);
    }

    public function test_verify_code_marks_phone_verified_and_redirects(): void
    {
        $user = User::factory()->client()->create(['phone_verified_at' => null]);
        $this->actingAs($user);
        $this->seedActiveCode($user, '123456');

        Livewire::test(VerifyPhone::class)
            ->set('codeSent', true)
            ->set('code', '123456')
            ->call('verifyCode')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_wrong_code_keeps_phone_unverified(): void
    {
        $user = User::factory()->client()->create(['phone_verified_at' => null]);
        $this->actingAs($user);
        $this->seedActiveCode($user, '123456');

        Livewire::test(VerifyPhone::class)
            ->set('codeSent', true)
            ->set('code', '000000')
            ->call('verifyCode')
            ->assertHasErrors('code');

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_guard_redirects_unverified_when_enabled(): void
    {
        config(['auth.require_phone_verification' => true]);
        Route::middleware(['web', 'auth', 'phone.verified'])->get('/_phone_guard', fn () => 'ok');

        $user = User::factory()->client()->create(['phone_verified_at' => null]);
        $this->actingAs($user)->get('/_phone_guard')->assertRedirect(route('phone.verify'));
    }

    public function test_guard_is_noop_when_disabled(): void
    {
        config(['auth.require_phone_verification' => false]);
        Route::middleware(['web', 'auth', 'phone.verified'])->get('/_phone_guard', fn () => 'ok');

        $user = User::factory()->client()->create(['phone_verified_at' => null]);
        $this->actingAs($user)->get('/_phone_guard')->assertOk();
    }

    public function test_guard_lets_verified_user_through(): void
    {
        config(['auth.require_phone_verification' => true]);
        Route::middleware(['web', 'auth', 'phone.verified'])->get('/_phone_guard', fn () => 'ok');

        $user = User::factory()->client()->create(['phone_verified_at' => now()]);
        $this->actingAs($user)->get('/_phone_guard')->assertOk();
    }
}
