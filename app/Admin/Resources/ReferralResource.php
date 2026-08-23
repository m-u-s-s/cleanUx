<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Support\ActivityLogger;

/**
 * Les parrainages et leurs récompenses.
 *
 * @extends EloquentResource<Referral>
 */
class ReferralResource extends EloquentResource
{
    public function key(): string
    {
        return 'referrals';
    }

    protected function model(): string
    {
        return Referral::class;
    }

    protected function columnSpec(): array
    {
        return [
            'referral_code' => ['Code'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'referee_email' => ['Filleul'],
            'referrer_reward_amount' => ['Récompense parrain', Column::TYPE_MONEY],
            'created_at' => ['Créé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['referral_code', 'referee_email'];
    }

    protected function searchLabel(): string
    {
        return 'Code ou email du filleul';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'invited', 'label' => 'Invité'],
                ['value' => 'signed_up', 'label' => 'Inscrit'],
                ['value' => 'qualified', 'label' => 'Qualifié'],
                ['value' => 'rewarded', 'label' => 'Récompensé'],
                ['value' => 'expired', 'label' => 'Expiré'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'source_channel' => 'Canal',
            'qualified_at' => 'Qualifié le',
            'rewarded_at' => 'Récompensé le',
            'expires_at' => 'Expire le',
        ];
    }

    public function actions(): array
    {
        return [
            // Marquer un parrainage frauduleux RÉVOQUE aussi ses récompenses.
            Action::make('flag-fraud', 'Marquer frauduleux', function (Referral $referral) {
                $referral->forceFill(['status' => Referral::STATUS_FRAUD])->save();

                $referral->rewards()->update([
                    'status' => ReferralReward::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoked_reason' => 'Marqué frauduleux par admin',
                ]);

                ActivityLogger::log('referral.flagged_fraud', $referral, [
                    'admin_user_id' => request()->user()?->id,
                ]);

                return ['ok' => true];
            })->destructive('Le parrainage et ses récompenses seront révoqués.'),
        ];
    }
}
