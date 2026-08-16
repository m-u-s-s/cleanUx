<?php

namespace Database\Factories;

use App\Models\ProviderFaceIncident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderFaceIncident>
 */
class ProviderFaceIncidentFactory extends Factory
{
    protected $model = ProviderFaceIncident::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ProviderFaceIncident::TYPE_PROVIDER_REPORT,
            'severity' => ProviderFaceIncident::SEVERITY_INFO,
            'status' => ProviderFaceIncident::STATUS_OPEN,
            'message' => 'La caméra reste noire quand j\'ouvre le contrôle.',
            'diagnostics' => ['platform' => 'android', 'app_version' => '1.4.0'],
            'occurrence_count' => 1,
        ];
    }

    public function fraudSuspicion(): self
    {
        return $this->state(fn () => [
            'type' => ProviderFaceIncident::TYPE_REPEATED_ABANDON,
            'severity' => ProviderFaceIncident::SEVERITY_CRITICAL,
            'message' => 'Six contrôles abandonnés en sept jours.',
            'occurrence_count' => 6,
        ]);
    }
}
