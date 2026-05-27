<?php

namespace App\Services\Assistant\Actions;

use App\Models\User;

/**
 * Detects which data-fetching actions are relevant to a user's message
 * by matching keywords and role guards.
 *
 * This is the lightweight keyword-based approach; the LLM still generates
 * the final response, but the detected action results are pre-fetched and
 * injected into the conversation context before the LLM call.
 */
class ActionDetector
{
    /**
     * @return list<string> Action names that match the message (de-duplicated, role-scoped)
     */
    public function detectActions(string $message, User $user): array
    {
        $lower   = mb_strtolower($message);
        $actions = [];

        if ($this->matchesBookings($lower) && ($user->isClient() || $user->isEntreprise())) {
            $actions[] = 'list_bookings';
        }

        if ($this->matchesLoyalty($lower) && ($user->isClient() || $user->isEntreprise())) {
            $actions[] = 'loyalty_balance';
        }

        if ($this->matchesMission($lower) && $user->isEmploye()) {
            $actions[] = 'next_mission';
        }

        if ($this->matchesEarnings($lower) && $user->isEmploye()) {
            $actions[] = 'earnings_summary';
        }

        if ($this->matchesAvailability($lower) && $user->isEmploye()) {
            $actions[] = 'availability_slots';
        }

        if ($this->matchesKpis($lower) && $user->isAdmin()) {
            $actions[] = 'platform_kpis';
        }

        if ($this->matchesTradeStats($lower) && $user->isAdmin()) {
            $actions[] = 'trade_stats';
        }

        return array_values(array_unique($actions));
    }

    // ──────────────────────────────────────────────────────
    // Private keyword matchers (one per action group)
    // ──────────────────────────────────────────────────────

    private function matchesBookings(string $lower): bool
    {
        return str_contains($lower, 'réservation')
            || str_contains($lower, 'reservation')
            || str_contains($lower, 'booking')
            || str_contains($lower, 'mes commandes')
            || str_contains($lower, 'mes missions');
    }

    private function matchesLoyalty(string $lower): bool
    {
        return str_contains($lower, 'fidélité')
            || str_contains($lower, 'fidelite')
            || str_contains($lower, 'points')
            || str_contains($lower, 'loyalty')
            || str_contains($lower, 'tier')
            || str_contains($lower, 'récompense')
            || str_contains($lower, 'recompense');
    }

    private function matchesMission(string $lower): bool
    {
        return str_contains($lower, 'mission')
            || str_contains($lower, 'prochaine')
            || str_contains($lower, 'prochaine intervention')
            || str_contains($lower, 'planning')
            || str_contains($lower, 'agenda');
    }

    private function matchesEarnings(string $lower): bool
    {
        return str_contains($lower, 'revenu')
            || str_contains($lower, 'gagné')
            || str_contains($lower, 'gagne')
            || str_contains($lower, 'earnings')
            || str_contains($lower, 'paiement')
            || str_contains($lower, 'virement')
            || str_contains($lower, 'salaire');
    }

    private function matchesAvailability(string $lower): bool
    {
        return str_contains($lower, 'disponibilité')
            || str_contains($lower, 'disponibilite')
            || str_contains($lower, 'créneaux')
            || str_contains($lower, 'creneaux')
            || str_contains($lower, 'horaire')
            || str_contains($lower, 'availability');
    }

    private function matchesKpis(string $lower): bool
    {
        return str_contains($lower, 'kpi')
            || str_contains($lower, 'tableau de bord')
            || str_contains($lower, 'statistiques')
            || str_contains($lower, 'stats')
            || str_contains($lower, 'plateforme')
            || str_contains($lower, 'global')
            || str_contains($lower, 'chiffre');
    }

    private function matchesTradeStats(string $lower): bool
    {
        return str_contains($lower, 'métier')
            || str_contains($lower, 'metier')
            || str_contains($lower, 'trade')
            || str_contains($lower, 'service le plus')
            || str_contains($lower, 'par service')
            || str_contains($lower, 'par type')
            || str_contains($lower, 'nettoyage')
            || str_contains($lower, 'peinture');
    }
}
