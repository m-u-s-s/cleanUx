<?php

namespace Tests\Feature\Sms;

use App\Models\PhoneVerificationCode;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\PhoneVerificationService;
use App\Services\Sms\Providers\SmsMockProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhoneVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(SmsProviderInterface::class, SmsMockProvider::class);
        Config::set('sms.enabled', true);
        Config::set('sms.otp.length', 6);
        Config::set('sms.otp.expires_minutes', 10);
        Config::set('sms.otp.max_attempts', 5);
        Config::set('sms.otp.cooldown_seconds', 60);
    }

    public function test_send_code_creates_otp_record_and_dispatches_sms(): void
    {
        $user = User::factory()->client()->create();

        $record = app(PhoneVerificationService::class)->sendCode($user, '+32412345678');

        $this->assertInstanceOf(PhoneVerificationCode::class, $record);
        $this->assertSame('+32412345678', $record->phone);
        $this->assertSame($user->id, $record->user_id);
        $this->assertNull($record->used_at);
        $this->assertTrue($record->expires_at->isFuture());

        $sms = SmsMessage::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($sms);
        $this->assertSame(SmsMessage::CATEGORY_VERIFICATION, $sms->category);
    }

    public function test_send_code_throws_on_invalid_phone(): void
    {
        $user = User::factory()->client()->create();

        $this->expectException(ValidationException::class);

        app(PhoneVerificationService::class)->sendCode($user, '0412345678');
    }

    public function test_send_code_enforces_cooldown(): void
    {
        $user = User::factory()->client()->create();
        Config::set('sms.otp.cooldown_seconds', 60);

        app(PhoneVerificationService::class)->sendCode($user, '+32412345678');

        $this->expectException(ValidationException::class);

        app(PhoneVerificationService::class)->sendCode($user, '+32412345678');
    }

    public function test_verify_with_correct_code_marks_user_phone_verified(): void
    {
        $user = User::factory()->client()->create(['phone' => null]);

        $plainCode = '123456';
        $record = PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => '+32499887766',
            'code_hash' => Hash::make($plainCode),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'purpose' => 'phone_verification',
        ]);

        $ok = app(PhoneVerificationService::class)->verify($user, $plainCode);

        $this->assertTrue($ok);

        $record->refresh();
        $this->assertNotNull($record->used_at);

        $user->refresh();
        $this->assertSame('+32499887766', $user->phone);
        if (\Schema::hasColumn('users', 'phone_verified_at')) {
            $this->assertNotNull($user->phone_verified_at);
        }
    }

    public function test_verify_with_wrong_code_throws_validation(): void
    {
        $user = User::factory()->client()->create();

        PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => '+32499887766',
            'code_hash' => Hash::make('999999'),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'purpose' => 'phone_verification',
        ]);

        $this->expectException(ValidationException::class);

        app(PhoneVerificationService::class)->verify($user, '000000');
    }

    public function test_verify_without_active_code_throws_validation(): void
    {
        $user = User::factory()->client()->create();

        $this->expectException(ValidationException::class);

        app(PhoneVerificationService::class)->verify($user, '123456');
    }

    public function test_verify_blocks_after_max_attempts_reached(): void
    {
        $user = User::factory()->client()->create();

        PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => '+32499887766',
            'code_hash' => Hash::make('123456'),
            'attempts' => 5,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'purpose' => 'phone_verification',
        ]);

        $this->expectException(ValidationException::class);

        app(PhoneVerificationService::class)->verify($user, '123456');
    }

    public function test_expired_code_is_not_found_as_active(): void
    {
        $user = User::factory()->client()->create();

        PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => '+32499887766',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->subMinute(),
            'purpose' => 'phone_verification',
        ]);

        $this->expectException(ValidationException::class);

        app(PhoneVerificationService::class)->verify($user, '123456');
    }
}
