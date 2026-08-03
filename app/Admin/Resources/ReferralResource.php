<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Referral;

/**
 * Les parrainages et leurs récompenses.
 *
 * Aucune requalification manuelle : un parrainage devient qualifiant parce qu’une commande a
 * été honorée, et le forcer ici créerait une récompense sans contrepartie.
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
}
