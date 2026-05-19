<?php

namespace Database\Seeders;

use App\Models\CancellationExemptReason;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyTier;
use Illuminate\Database\Seeder;

class CancellationPoliciesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'policy' => [
                    'code' => 'default_client',
                    'name' => 'Annulation client — standard',
                    'description' => 'Tiers progressifs : >48h gratuit, 48-24h 25%, 24-2h 50%, <2h 100%',
                    'trade_codes' => null,
                    'actor_role' => CancellationPolicy::ACTOR_CLIENT,
                    'is_active' => true,
                    'version' => 1,
                ],
                'tiers' => [
                    ['min_hours_before' => 48, 'max_hours_before' => null, 'fee_percent' => 0.00, 'description' => '> 48h : gratuit'],
                    ['min_hours_before' => 24, 'max_hours_before' => 48, 'fee_percent' => 25.00, 'description' => '48h-24h : 25%'],
                    ['min_hours_before' => 2, 'max_hours_before' => 24, 'fee_percent' => 50.00, 'description' => '24h-2h : 50%'],
                    ['min_hours_before' => 0, 'max_hours_before' => 2, 'fee_percent' => 100.00, 'description' => '< 2h : 100%'],
                ],
                'exempt_reasons' => [
                    ['reason_code' => 'force_majeure', 'label' => 'Force majeure (catastrophe naturelle, événement extraordinaire)', 'requires_proof' => true],
                    ['reason_code' => 'medical_emergency', 'label' => 'Urgence médicale', 'requires_proof' => true, 'max_per_user_per_30d' => 2],
                    ['reason_code' => 'provider_no_show', 'label' => 'Provider absent / annulation provider', 'requires_proof' => false],
                ],
            ],
            [
                'policy' => [
                    'code' => 'default_provider',
                    'name' => 'Annulation provider — pénalité forfaitaire',
                    'description' => 'Penalité forfaitaire 15€ + 10% du montant si <24h. Affecte rating provider.',
                    'trade_codes' => null,
                    'actor_role' => CancellationPolicy::ACTOR_PROVIDER,
                    'is_active' => true,
                    'version' => 1,
                ],
                'tiers' => [
                    ['min_hours_before' => 24, 'max_hours_before' => null, 'fee_percent' => 0.00, 'fee_flat_cents' => 0, 'description' => '> 24h : aucune pénalité'],
                    ['min_hours_before' => 2, 'max_hours_before' => 24, 'fee_percent' => 10.00, 'fee_flat_cents' => 1500, 'description' => '24h-2h : 1500c flat + 10%'],
                    ['min_hours_before' => 0, 'max_hours_before' => 2, 'fee_percent' => 25.00, 'fee_flat_cents' => 3000, 'description' => '< 2h : 3000c flat + 25%'],
                ],
                'exempt_reasons' => [
                    ['reason_code' => 'force_majeure', 'label' => 'Force majeure', 'requires_proof' => true],
                    ['reason_code' => 'medical_emergency', 'label' => 'Urgence médicale provider', 'requires_proof' => true, 'max_per_user_per_30d' => 3],
                ],
            ],
        ];

        foreach ($templates as $template) {
            $policy = CancellationPolicy::query()->updateOrCreate(
                ['code' => $template['policy']['code']],
                $template['policy'],
            );

            // Wipe and re-seed for dev convenience
            $policy->tiers()->delete();
            foreach (array_values($template['tiers']) as $i => $tier) {
                CancellationPolicyTier::create(array_merge([
                    'policy_id' => $policy->id,
                    'position' => $i + 1,
                    'fee_flat_cents' => 0,
                ], $tier));
            }

            $policy->exemptReasons()->delete();
            foreach ($template['exempt_reasons'] as $reason) {
                CancellationExemptReason::create(array_merge([
                    'policy_id' => $policy->id,
                    'is_active' => true,
                ], $reason));
            }
        }
    }
}
