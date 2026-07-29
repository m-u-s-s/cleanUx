<?php

namespace App\Services\Sms;

use App\Models\PhoneVerificationCode;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    /**
     * Codes émis avant l'existence du compte, pour le premier écran de l'inscription prestataire.
     * Ils portent `user_id = null` et s'échangent contre un jeton présenté à l'inscription.
     */
    public const PURPOSE_REGISTRATION = 'registration';

    /**
     * Durée de vie du jeton remis après vérification. Assez large pour finir le formulaire sans
     * se presser, assez courte pour qu'un jeton intercepté ne serve pas des semaines plus tard.
     */
    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(protected SmsService $smsService) {}

    public function sendCode(User $user, string $phone, string $purpose = 'phone_verification'): PhoneVerificationCode
    {
        $phone = $this->smsService->normalizePhone($phone);

        if (! $this->smsService->isValidE164($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Numéro de téléphone invalide (format E.164 requis, ex: +32412345678).',
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
            category: SmsMessage::CATEGORY_VERIFICATION,
            idempotencyKey: 'otp:'.$record->id,
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
        if (Schema::hasColumn('users', 'phone_verified_at')) {
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

    /**
     * Envoie un code à un numéro qui n'appartient encore à personne.
     *
     * Volontairement muet sur l'existence d'un compte portant déjà ce numéro : répondre
     * différemment ferait de cet endpoint public un oracle d'énumération d'utilisateurs.
     */
    public function sendRegistrationCode(string $phone): PhoneVerificationCode
    {
        $phone = $this->smsService->normalizePhone($phone);

        if (! $this->smsService->isValidE164($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Numéro de téléphone invalide (format E.164 requis, ex: +32412345678).',
            ]);
        }

        $cooldown = (int) Config::get('sms.otp.cooldown_seconds', 60);
        $lastCode = $this->pendingRegistrationQuery($phone)->latest('id')->first();

        if ($lastCode && $lastCode->created_at->diffInSeconds(now()) < $cooldown) {
            $remaining = $cooldown - $lastCode->created_at->diffInSeconds(now());
            throw ValidationException::withMessages([
                'phone' => "Patientez {$remaining}s avant de demander un nouveau code.",
            ]);
        }

        $code = $this->generateNumericCode((int) Config::get('sms.otp.length', 6));

        $record = PhoneVerificationCode::create([
            'user_id' => null,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => (int) Config::get('sms.otp.max_attempts', 5),
            'expires_at' => now()->addMinutes((int) Config::get('sms.otp.expires_minutes', 10)),
            'purpose' => self::PURPOSE_REGISTRATION,
            'ip_address' => request()?->ip(),
        ]);

        $appName = (string) Config::get('app.name', 'CleanUx');
        $this->smsService->dispatch(
            toPhone: $phone,
            body: sprintf('%s : votre code est %s. Valide %d min.',
                $appName,
                $code,
                (int) Config::get('sms.otp.expires_minutes', 10),
            ),
            user: null,
            source: $record,
            category: SmsMessage::CATEGORY_VERIFICATION,
            idempotencyKey: 'otp:'.$record->id,
        );

        ActivityLogger::log('phone_verification.registration_code_sent', $record, [
            'phone' => $phone,
        ]);

        return $record;
    }

    /**
     * Vérifie le code d'inscription et renvoie le jeton à présenter à `POST /api/auth/register`.
     */
    public function verifyRegistrationCode(string $phone, string $code): string
    {
        $phone = $this->smsService->normalizePhone($phone);

        $active = $this->pendingRegistrationQuery($phone)
            ->where('expires_at', '>', now())
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
            ActivityLogger::log('phone_verification.registration_max_attempts', $active, [
                'phone' => $phone,
            ]);
            throw ValidationException::withMessages([
                'code' => 'Trop de tentatives. Demandez un nouveau code.',
            ]);
        }

        if (! Hash::check($code, $active->code_hash)) {
            throw ValidationException::withMessages([
                'code' => 'Code incorrect.',
            ]);
        }

        $active->forceFill(['used_at' => now()])->save();

        ActivityLogger::log('phone_verification.registration_verified', $active, [
            'phone' => $phone,
        ]);

        return Crypt::encryptString((string) json_encode([
            'id' => $active->id,
            'phone' => $active->phone,
            'exp' => now()->addMinutes(self::TOKEN_TTL_MINUTES)->timestamp,
        ]));
    }

    /**
     * Échange le jeton contre le numéro vérifié qu'il porte, une seule fois.
     *
     * Renvoie `null` sur tout jeton illisible, expiré, déjà échangé ou portant un autre numéro que
     * celui soumis : l'inscription se poursuit alors sans téléphone vérifié plutôt que d'échouer.
     */
    public function consumeRegistrationToken(string $token, ?string $expectedPhone = null): ?string
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['id'], $payload['phone'], $payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] < now()->timestamp) {
            return null;
        }

        $record = PhoneVerificationCode::query()
            ->whereKey($payload['id'])
            ->where('purpose', self::PURPOSE_REGISTRATION)
            ->whereNotNull('used_at')
            ->whereNull('consumed_at')
            ->first();

        if (! $record || $record->phone !== $payload['phone']) {
            return null;
        }

        if ($expectedPhone !== null
            && $this->smsService->normalizePhone($expectedPhone) !== $record->phone) {
            return null;
        }

        $record->forceFill(['consumed_at' => now()])->save();

        return $record->phone;
    }

    /** @return Builder<PhoneVerificationCode> */
    private function pendingRegistrationQuery(string $phone)
    {
        return PhoneVerificationCode::query()
            ->whereNull('user_id')
            ->where('phone', $phone)
            ->where('purpose', self::PURPOSE_REGISTRATION)
            ->whereNull('consumed_at');
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
