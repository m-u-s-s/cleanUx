<?php

namespace App\Services\Assistant\Actions;

use App\Models\User;

/** Detects which data-fetching actions are relevant to a user's message by matching keywords and role guards. */
class ActionDetector
{
    /**
     * @return list<string> Action names that match the message (de-duplicated, role-scoped)
     */
    public function detectActions(string $message, User $user): array
    {
        $lower = mb_strtolower($message);
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

        if ($this->matchesResolveDispute($lower) && $user->isAdmin()) {
            $actions[] = 'resolve_dispute';
        }

        if ($this->matchesCancelBooking($lower) && ($user->isClient() || $user->isEntreprise())) {
            $actions[] = 'cancel_booking';
        }

        if ($this->matchesCreateBooking($lower) && ($user->isClient() || $user->isEntreprise())) {
            $actions[] = 'create_booking';
        }

        if ($this->matchesUpdateAvailability($lower) && $user->isEmploye()) {
            $actions[] = 'update_availability';
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

    private function matchesCancelBooking(string $lower): bool
    {
        return str_contains($lower, 'annuler')
            || str_contains($lower, 'annule')
            || str_contains($lower, 'annulation')
            || str_contains($lower, 'cancel')
            || str_contains($lower, 'supprimer ma réservation')
            || str_contains($lower, 'supprimer ma reservation');
    }

    private function matchesCreateBooking(string $lower): bool
    {
        return str_contains($lower, 'créer une réservation')
            || str_contains($lower, 'creer une reservation')
            || str_contains($lower, 'nouvelle réservation')
            || str_contains($lower, 'nouvelle reservation')
            || str_contains($lower, 'prendre un rendez-vous')
            || str_contains($lower, 'réserver un service')
            || str_contains($lower, 'reserver un service')
            || str_contains($lower, 'commander un service');
    }

    private function matchesUpdateAvailability(string $lower): bool
    {
        return str_contains($lower, 'passer en ligne')
            || str_contains($lower, 'mettre en ligne')
            || str_contains($lower, 'passer hors ligne')
            || str_contains($lower, 'passer offline')
            || str_contains($lower, 'passer online')
            || str_contains($lower, 'en pause')
            || str_contains($lower, 'changer mon statut')
            || str_contains($lower, 'je suis disponible')
            || str_contains($lower, 'je suis indisponible');
    }

    private function matchesResolveDispute(string $lower): bool
    {
        return str_contains($lower, 'résoudre le litige')
            || str_contains($lower, 'resoudre le litige')
            || str_contains($lower, 'résoudre litige')
            || str_contains($lower, 'clore le litige')
            || str_contains($lower, 'fermer le litige')
            || str_contains($lower, 'résolution litige')
            || str_contains($lower, 'resolution litige');
    }
}
