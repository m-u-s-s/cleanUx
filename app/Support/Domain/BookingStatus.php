<?php

namespace App\Support\Domain;

final class BookingStatus
{
    public const EN_ATTENTE = 'en_attente';
    public const CONFIRME = 'confirme';
    public const EN_ROUTE = 'en_route';
    public const SUR_PLACE = 'sur_place';
    public const TERMINE = 'termine';
    public const ANNULE = 'annule';
    public const REFUSE = 'refuse';

    public static function all(): array
    {
        return [
            self::EN_ATTENTE,
            self::CONFIRME,
            self::EN_ROUTE,
            self::SUR_PLACE,
            self::TERMINE,
            self::ANNULE,
            self::REFUSE,
        ];
    }

    public static function active(): array
    {
        return [
            self::EN_ATTENTE,
            self::CONFIRME,
            self::EN_ROUTE,
            self::SUR_PLACE,
        ];
    }

    public static function employeeDashboard(): array
    {
        return [
            self::SUR_PLACE,
            self::EN_ROUTE,
            self::CONFIRME,
            self::EN_ATTENTE,
            self::TERMINE,
            self::REFUSE,
        ];
    }

    public static function clientEditable(): array
    {
        return [
            self::EN_ATTENTE,
            self::CONFIRME,
        ];
    }

    public static function clientLocked(): array
    {
        return [
            self::EN_ROUTE,
            self::SUR_PLACE,
            self::TERMINE,
            self::ANNULE,
            self::REFUSE,
        ];
    }

    public static function final(): array
    {
        return [
            self::ANNULE,
            self::REFUSE,
            self::TERMINE,
        ];
    }

    public static function employeeDashboardCaseSql(string $column = 'status'): string
    {
        $cases = collect(self::employeeDashboard())
            ->values()
            ->map(fn (string $status, int $index) => sprintf("WHEN '%s' THEN %d", $status, $index + 1))
            ->implode(' ');

        return sprintf('CASE %s %s ELSE 999 END', $column, $cases);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::CONFIRME => 'confirmée',
            self::ANNULE => 'annulée',
            self::REFUSE => 'refusée',
            self::EN_ATTENTE => 'mise en attente',
            self::EN_ROUTE => 'en route',
            self::SUR_PLACE => 'en cours sur place',
            self::TERMINE => 'terminée',
            default => 'mise à jour',
        };
    }

    public static function mailLabel(string $status): string
    {
        return match ($status) {
            self::CONFIRME => 'confirmée ✅',
            self::ANNULE => 'annulée ❌',
            self::REFUSE => 'refusée ❌',
            self::EN_ATTENTE => 'mise en attente ⏳',
            self::EN_ROUTE => 'en route 🚗',
            self::SUR_PLACE => 'en cours sur place 📍',
            self::TERMINE => 'terminée ✅',
            default => 'mise à jour',
        };
    }

    public static function notificationSeverity(string $status): string
    {
        return match ($status) {
            self::CONFIRME, self::TERMINE => 'success',
            self::ANNULE, self::REFUSE => 'danger',
            self::EN_ROUTE, self::SUR_PLACE => 'info',
            default => 'warning',
        };
    }

    public static function requiresReminderReset(string $status): bool
    {
        return $status === self::EN_ATTENTE;
    }
}
