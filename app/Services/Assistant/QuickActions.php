<?php

namespace App\Services\Assistant;

use App\Models\User;

/** Returns role-specific quick-action suggestions for the chatbot widget. */
class QuickActions
{
    /**
     * @return array<int, array{label: string, prompt: string}>
     */
    public function forUser(User $user): array
    {
        if ($user->isPlatformAdmin()) {
            return [
                ['label' => 'KPIs du jour',     'prompt' => 'Montre-moi les KPIs principaux du jour'],
                ['label' => 'Litiges en cours',  'prompt' => 'Quels sont les litiges ouverts ?'],
                ['label' => 'Top métiers',        'prompt' => 'Quel métier a le plus de réservations ce mois ?'],
            ];
        }

        if ($user->isClientCompany()) {
            return [
                ['label' => 'Mes réservations', 'prompt' => 'Montre mes réservations en cours'],
                ['label' => 'Nouveau service',   'prompt' => 'Je veux réserver un service pour un de mes locaux'],
                ['label' => 'Factures',          'prompt' => 'Montre mes dernières factures'],
            ];
        }

        if ($user->isEmploye()) {
            return [
                ['label' => 'Prochaine mission', 'prompt' => 'Quelle est ma prochaine mission ?'],
                ['label' => 'Mes revenus',        'prompt' => 'Combien ai-je gagné cette semaine ?'],
                ['label' => 'Disponibilités',     'prompt' => 'Montre mon planning de disponibilités'],
            ];
        }

        // Default: client personal
        return [
            ['label' => 'Réserver',          'prompt' => 'Aide-moi à choisir le bon service'],
            ['label' => 'Mes réservations',  'prompt' => 'Où en sont mes réservations ?'],
            ['label' => 'Fidélité',          'prompt' => 'Combien de points de fidélité ai-je ?'],
        ];
    }
}
