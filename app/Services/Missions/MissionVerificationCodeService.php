<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class MissionVerificationCodeService
{
    public function createVerificationCode(Mission $mission, string $type): array
    {
        MissionVerificationCode::query()
            ->where('mission_id', $mission->id)
            ->where('code_type', $type)
            ->where('is_consumed', false)
            ->update([
                'is_consumed' => true,
            ]);

        $plainCode = $this->generatePlainCode();

        $record = MissionVerificationCode::query()->create([
            'mission_id' => $mission->id,
            'code_type' => $type,
            'code_hash' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes(20),
            'attempts' => 0,
            'is_consumed' => false,
        ]);

        return [
            'code' => $plainCode,
            'record' => $record,
        ];
    }

    public function consumeValidCode(Mission $mission, string $type, string $plainCode, User $user): MissionVerificationCode
    {
        /** @var MissionVerificationCode|null $record */
        $record = MissionVerificationCode::query()
            ->where('mission_id', $mission->id)
            ->where('code_type', $type)
            ->where('is_consumed', false)
            ->latest('id')
            ->first();

        if (! $record) {
            throw new RuntimeException('Aucun code valide trouvé pour cette mission.');
        }

        $record->increment('attempts');

        if ($record->expires_at && $record->expires_at->isPast()) {
            throw new RuntimeException('Le code a expiré.');
        }

        if (! Hash::check(trim($plainCode), $record->code_hash)) {
            throw new RuntimeException('Code invalide.');
        }

        $record->update([
            'validated_at' => now(),
            'validated_by_user_id' => $user->id,
            'is_consumed' => true,
        ]);

        return $record->fresh();
    }

    protected function generatePlainCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
