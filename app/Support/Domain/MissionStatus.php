<?php

namespace App\Support\Domain;

final class MissionStatus
{
    public const PLANNED = 'planned';
    public const ASSIGNED = 'assigned';
    public const EN_ROUTE = 'en_route';
    public const ARRIVED = 'arrived';
    public const STARTED = 'started';
    public const PAUSED = 'paused';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::PLANNED,
            self::ASSIGNED,
            self::EN_ROUTE,
            self::ARRIVED,
            self::STARTED,
            self::PAUSED,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }

    public static function trackable(): array
    {
        return [
            self::EN_ROUTE,
            self::ARRIVED,
            self::STARTED,
            self::PAUSED,
        ];
    }

    public static function canSetEnRoute(): array
    {
        return [
            self::PLANNED,
            self::ASSIGNED,
        ];
    }

    public static function canSetArrived(): array
    {
        return [
            self::EN_ROUTE,
            self::ASSIGNED,
        ];
    }

    public static function canStart(): array
    {
        return [
            self::ARRIVED,
        ];
    }

    public static function canFinish(): array
    {
        return [
            self::STARTED,
            self::PAUSED,
        ];
    }

    public static function initialFor(bool $hasLeadEmployee): string
    {
        return $hasLeadEmployee ? self::ASSIGNED : self::PLANNED;
    }
}
