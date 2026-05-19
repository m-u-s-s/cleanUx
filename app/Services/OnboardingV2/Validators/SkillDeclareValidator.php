<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifie que le provider a déclaré au moins N trades / skills.
 * Schema-defensive : utilise provider_trades si dispo, sinon provider_profiles.metadata.trade_codes.
 */
class SkillDeclareValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        $minCount = (int) ($step->metadata['min_skills_count'] ?? 1);

        // Option 1 : table pivot provider_trades / provider_skills
        foreach (['provider_trades', 'provider_skills'] as $pivotTable) {
            if (Schema::hasTable($pivotTable)) {
                $count = DB::table($pivotTable)->where('user_id', $user->id)->count();
                if ($count >= $minCount) {
                    return OnboardingStepValidation::pass(metadata: [
                        'source' => $pivotTable,
                        'count' => $count,
                    ]);
                }
            }
        }

        // Option 2 : provider_profiles.metadata.trade_codes JSON
        if (Schema::hasTable('provider_profiles')) {
            $profile = DB::table('provider_profiles')->where('user_id', $user->id)->first();
            if ($profile && ! empty($profile->metadata ?? null)) {
                $meta = is_string($profile->metadata) ? json_decode($profile->metadata, true) : [];
                $trades = (array) ($meta['trade_codes'] ?? []);
                if (count($trades) >= $minCount) {
                    return OnboardingStepValidation::pass(metadata: [
                        'source' => 'provider_profiles.metadata',
                        'count' => count($trades),
                    ]);
                }
            }
        }

        // Option 3 : payload contient une déclaration trade
        $declared = (array) ($payload['trade_codes'] ?? []);
        if (count($declared) >= $minCount) {
            return OnboardingStepValidation::pass(
                normalizedData: ['trade_codes' => $declared],
                metadata: ['source' => 'payload'],
            );
        }

        return OnboardingStepValidation::fail([
            'skills' => "Au moins {$minCount} trade(s) doivent être déclarés.",
        ]);
    }
}
