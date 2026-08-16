<?php

namespace Database\Factories;

use App\Models\ProviderFaceProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderFaceProfile>
 */
class ProviderFaceProfileFactory extends Factory
{
    protected $model = ProviderFaceProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => ProviderFaceProfile::STATUS_PENDING,
            'id_match_status' => ProviderFaceProfile::MATCH_PENDING,
            'consecutive_failures' => 0,
        ];
    }

    /**
     * Un prestataire enrôlé, consentant, et à jour : le point de départ de la
     * plupart des tests. `next_check_due_at` dans le futur = aucun contrôle dû.
     */
    public function enrolled(): self
    {
        return $this->state(fn () => [
            'status' => ProviderFaceProfile::STATUS_ENROLLED,
            'reference_path' => 'providers/face/reference.enc',
            'reference_hash' => str_repeat('a', 64),
            'reference_mime' => 'image/jpeg',
            'captured_at' => now()->subDays(30),
            'consent_given_at' => now()->subDays(30),
            'consent_version' => '1.0',
            'id_match_status' => ProviderFaceProfile::MATCH_OK,
            'id_match_score' => 88.0,
            'id_match_checked_at' => now()->subDays(30),
            'next_check_due_at' => now()->addDay(),
            'last_check_at' => now()->subHours(6),
        ]);
    }

    /** Enrôlé, mais un contrôle est dû maintenant. */
    public function due(): self
    {
        return $this->enrolled()->state(fn () => [
            'next_check_due_at' => now()->subMinute(),
        ]);
    }

    public function blocked(string $reason = ProviderFaceProfile::BLOCK_FAILED_CHECKS): self
    {
        return $this->enrolled()->state(fn () => [
            'blocked_at' => now()->subHour(),
            'block_reason' => $reason,
        ]);
    }
}
