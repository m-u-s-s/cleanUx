<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ServiceZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ActivityLogger
{
    public static function log(string $action, ?Model $target = null, array $meta = []): void
    {
        self::write(Auth::id(), $action, $target, $meta);
    }

    public static function system(string $action, ?Model $target = null, array $meta = []): void
    {
        self::write(null, $action, $target, $meta);
    }

    public static function critical(string $action, ?Model $target = null, array $meta = []): void
    {
        $meta['is_critical'] = true;

        self::write(Auth::id(), $action, $target, $meta);
    }

    protected static function write(?int $userId, string $action, ?Model $target, array $meta): void
    {
        $columns = self::availableColumns();
        $request = app()->bound('request') ? request() : null;
        [$domain, $severity, $isCritical] = self::classify($action, $meta);

        $payload = [
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->getKey(),
            'meta' => self::sanitizeMeta($meta),
            'domain' => Arr::get($meta, 'domain', $domain),
            'severity' => Arr::get($meta, 'severity', $severity),
            'is_critical' => (bool) Arr::get($meta, 'is_critical', $isCritical),
            'route_name' => $request?->route()?->getName(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'request_id' => self::resolveRequestId($request),
            'service_zone_id' => self::resolveServiceZoneId($target, $meta),
        ];

        ActivityLog::create(Arr::only($payload, $columns));
    }

    protected static function availableColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        $default = ['user_id', 'action', 'target_type', 'target_id', 'meta'];

        try {
            if (! Schema::hasTable('activity_logs')) {
                return $columns = $default;
            }

            $extra = [
                'domain',
                'severity',
                'is_critical',
                'route_name',
                'ip_address',
                'user_agent',
                'request_id',
                'service_zone_id',
            ];

            return $columns = array_merge(
                $default,
                array_values(array_filter($extra, fn (string $column) => Schema::hasColumn('activity_logs', $column)))
            );
        } catch (\Throwable $e) {
            return $columns = $default;
        }
    }

    protected static function sanitizeMeta(array $meta): array
    {
        return Arr::except($meta, ['domain', 'severity', 'is_critical']);
    }

    protected static function classify(string $action, array $meta): array
    {
        $domain = Arr::get($meta, 'domain');

        if (! $domain) {
            $domain = str_contains($action, '.') ? Str::before($action, '.') : self::inferDomainFromAction($action);
        }

        $severity = Arr::get($meta, 'severity', self::inferSeverityFromAction($action));
        $isCritical = (bool) Arr::get($meta, 'is_critical', self::inferCriticalFromAction($action, $severity));

        return [$domain ?: 'general', $severity, $isCritical];
    }

    protected static function inferDomainFromAction(string $action): string
    {
        return match (true) {
            str_contains($action, 'finance'), str_contains($action, 'payment'), str_contains($action, 'invoice'), str_contains($action, 'quote') => 'finance',
            str_contains($action, 'user'), str_contains($action, 'security'), str_contains($action, 'role'), str_contains($action, 'permission') => 'security',
            str_contains($action, 'zone'), str_contains($action, 'service') => 'operations',
            str_contains($action, 'incident'), str_contains($action, 'complaint'), str_contains($action, 'quality') => 'quality',
            str_contains($action, 'booking'), str_contains($action, 'rendez'), str_contains($action, 'mission') => 'booking',
            default => 'general',
        };
    }

    protected static function inferSeverityFromAction(string $action): string
    {
        return match (true) {
            str_contains($action, 'failed'), str_contains($action, 'error'), str_contains($action, 'blocked') => 'error',
            str_contains($action, 'delete'), str_contains($action, 'supprim'), str_contains($action, 'suspend'), str_contains($action, 'refuse'), str_contains($action, 'export') => 'warning',
            default => 'info',
        };
    }

    protected static function inferCriticalFromAction(string $action, string $severity): bool
    {
        if ($severity === 'error') {
            return true;
        }

        foreach (['delete', 'supprim', 'suspend', 'role', 'permission', 'export', 'finance', 'security'] as $keyword) {
            if (str_contains($action, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected static function resolveRequestId(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        return $request->headers->get('X-Request-Id')
            ?: $request->headers->get('X-Correlation-Id')
            ?: (string) Str::uuid();
    }

    protected static function resolveServiceZoneId(?Model $target, array $meta): ?int
    {
        $zoneId = Arr::get($meta, 'service_zone_id')
            ?? Arr::get($meta, 'zone_id')
            ?? Arr::get($meta, 'service_zone.id');

        if ($zoneId) {
            return (int) $zoneId;
        }

        if ($target && isset($target->service_zone_id)) {
            return $target->service_zone_id ? (int) $target->service_zone_id : null;
        }

        if ($target instanceof ServiceZone) {
            return (int) $target->getKey();
        }

        return null;
    }
}
