<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Models\MissionVerificationCode;
use App\Models\User;
use App\Services\Missions\MissionVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * M23 — direct error-path coverage for the mission verification code guard (start/end of the
 * money-critical QR flow), including the brute-force cap.
 */
class MissionVerificationCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCode(array $overrides = []): array
    {
        $mission = Mission::create(['status' => 'started']);
        $code = MissionVerificationCode::create(array_merge([
            'mission_id' => $mission->id,
            'code_type' => 'end',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(20),
            'attempts' => 0,
            'is_consumed' => false,
        ], $overrides));

        return [$mission, $code];
    }

    public function test_valid_code_is_consumed(): void
    {
        [$mission] = $this->makeCode();
        $user = User::factory()->employe()->create();

        $record = app(MissionVerificationCodeService::class)
            ->consumeValidCode($mission, 'end', '123456', $user);

        $this->assertTrue((bool) $record->is_consumed);
    }

    public function test_expired_code_throws(): void
    {
        [$mission] = $this->makeCode(['expires_at' => now()->subMinute()]);
        $user = User::factory()->employe()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expiré');
        app(MissionVerificationCodeService::class)->consumeValidCode($mission, 'end', '123456', $user);
    }

    public function test_wrong_code_throws(): void
    {
        [$mission] = $this->makeCode();
        $user = User::factory()->employe()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalide');
        app(MissionVerificationCodeService::class)->consumeValidCode($mission, 'end', '000000', $user);
    }

    /**
     * Le refus DIT QUOI FAIRE. Le code de fin n'étant plus émis à l'arrivée mais à la demande, son
     * absence est une étape pas encore franchie — pas une anomalie. « Aucun code valide trouvé »
     * laissait le prestataire devant un mur alors que le bouton qui le débloque est au-dessus.
     */
    public function test_no_unconsumed_code_throws(): void
    {
        [$mission] = $this->makeCode(['is_consumed' => true]);
        $user = User::factory()->employe()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Générer code fin');
        app(MissionVerificationCodeService::class)->consumeValidCode($mission, 'end', '123456', $user);
    }

    /** LE TÉMOIN : sur un code de DÉBUT, le message d'origine est conservé. */
    public function test_no_unconsumed_start_code_keeps_the_generic_message(): void
    {
        [$mission] = $this->makeCode(['is_consumed' => true, 'code_type' => 'start']);
        $user = User::factory()->employe()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aucun code valide');
        app(MissionVerificationCodeService::class)->consumeValidCode($mission, 'start', '123456', $user);
    }

    public function test_brute_force_is_blocked_after_max_attempts(): void
    {
        config(['missions.verification_max_attempts' => 5]);
        [$mission, $code] = $this->makeCode();
        $user = User::factory()->employe()->create();
        $svc = app(MissionVerificationCodeService::class);

        // 5 wrong attempts → each rejected as invalid.
        for ($i = 0; $i < 5; $i++) {
            try {
                $svc->consumeValidCode($mission, 'end', '000000', $user);
            } catch (RuntimeException $e) {
                // expected
            }
        }

        // 6th attempt — even with the CORRECT code — is blocked and the code is burned.
        try {
            $svc->consumeValidCode($mission, 'end', '123456', $user);
            $this->fail('Expected brute-force block');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Trop de tentatives', $e->getMessage());
        }

        $this->assertTrue((bool) $code->fresh()->is_consumed, 'code must be burned after brute-force');
    }
}
