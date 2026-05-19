<?php

namespace App\Services\Sms;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(protected SmsService $smsService)
    {
    }

    public function sendCode(User $user, string $phone, string $purpose = 'phone_verification'): PhoneVerificationCode
    {
        $phone = $this->smsService->normalizePhone($phone);

        if (! $this->smsService->isValidE164($phone)) {
            throw ValidationException::withMessages([
                'phone' => "Numéro de téléphone invalide (format E.164 requis, ex: +32412345678).",
            ]);
        }

        // Cooldown check
        $cooldown = (int) Config::get('sms.otp.cooldown_seconds', 60);
        $lastCode = PhoneVerificationCode::query()
            ->forUser($user->id)
            ->where('phone', $phone)
            ->latest('id')
            ->first();

        if ($lastCode && $lastCode->created_at->diffInSeconds(now()) < $cooldown) {
            $remaining = $cooldown - $lastCode->created_at->diffInSeconds(now());
            throw ValidationException::withMessages([
                'phone' => "Patientez {$remaining}s avant de demander un nouveau code.",
            ]);
        }

        $length = (int) Config::get('sms.otp.length', 6);
        $code = $this->generateNumericCode($length);

        $record = PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => (int) Config::get('sms.otp.max_attempts', 5),
            'expires_at' => now()->addMinutes((int) Config::get('sms.otp.expires_minutes', 10)),
            'purpose' => $purpose,
            'ip_address' => request()?->ip(),
        ]);

        $appName = (string) Config::get('app.name', 'CleanUx');
        $body = sprintf('%s : votre code est %s. Valide %d min.',
            $appName,
            $code,
            (int) Config::get('sms.otp.expires_minutes', 10),
        );

        $this->smsService->dispatch(
            toPhone: $phone,
            body: $body,
            user: $user,
            source: $record,
            category: \App\Models\SmsMessage::CATEGORY_VERIFICATION,
            idempotencyKey: 'otp:' . $record->id,
        );

        ActivityLogger::log('phone_verification.code_sent', $record, [
            'user_id' => $user->id,
            'purpose' => $purpose,
        ]);

        return $record;
    }

    public function verify(User $user, string $code, string $purpose = 'phone_verification'): bool
    {
        $active = PhoneVerificationCode::query()
            ->forUser($user->id)
            ->activeForPurpose($purpose)
            ->latest('id')
            ->first();

        if (! $active) {
            throw ValidationException::withMessages([
                'code' => 'Aucun code en cours. Demandez-en un nouveau.',
            ]);
        }

        $active->increment('attempts');
        $active->refresh();

        if (! $active->hasAttemptsLeft()) {
            ActivityLogger::log('phone_verification.max_attempts', $active, [
                'user_id' => $user->id,
            ]);
            throw ValidationException::withMessages([
                'code' => 'Trop de tentatives. Demandez un nouveau code.',
            ]);
        }

        if (! Hash::check((string) $code, $active->code_hash)) {
            throw ValidationException::withMessages([
                'code' => 'Code incorrect.',
            ]);
        }

        $active->forceFill(['used_at' => now()])->save();

        // Mark user phone verified
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone_verified_at')) {
            $user->forceFill([
                'phone' => $active->phone,
                'phone_verified_at' => now(),
            ])->save();
        } else {
            $user->forceFill(['phone' => $active->phone])->save();
        }

        ActivityLogger::log('phone_verification.success', $active, [
            'user_id' => $user->id,
        ]);

        return true;
    }

    protected function generateNumericCode(int $length): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }
}
