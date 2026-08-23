<?php

namespace App\Services\Client\Calendar;

use App\Models\Booking;
use App\Models\OrganizationSite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Feeds the client FullCalendar component (ClientCalendarFC): the user's bookings as calendar events, and the list of sites they can filter by. */
class CalendarDataService
{
    /** Booking status → FullCalendar colour. */
    private const STATUS_COLORS = [
        'en_attente' => '#f59e0b',
        'confirme' => '#3b82f6',
        'en_route' => '#6366f1',
        'arrived' => '#8b5cf6',
        'sur_place' => '#8b5cf6',
        'en_cours' => '#8b5cf6',
        'termine' => '#10b981',
        'completed' => '#10b981',
        'annule' => '#ef4444',
        'cancelled' => '#ef4444',
        'refuse' => '#ef4444',
    ];

    /**
     * @param  array{from?:Carbon, to?:Carbon, site_ids?:array<int,int>|null, statuses?:array<int,string>|null}  $opts
     * @return Collection<int, array<string, mixed>>
     */
    public function eventsForUser(User $user, array $opts = []): Collection
    {
        $query = Booking::query()
            ->where(fn ($w) => $w->where('client_id', $user->id)->orWhere('customer_user_id', $user->id))
            ->with(['serviceCatalog:id,name', 'organizationSite:id,name']);

        if (! empty($opts['from'])) {
            $query->whereDate('date', '>=', $opts['from']->toDateString());
        }
        if (! empty($opts['to'])) {
            $query->whereDate('date', '<=', $opts['to']->toDateString());
        }
        if (! empty($opts['site_ids'])) {
            $query->whereIn('organization_site_id', $opts['site_ids']);
        }
        if (! empty($opts['statuses'])) {
            $query->whereIn('status', $opts['statuses']);
        }

        return $query->orderBy('date')->get()->map(function (Booking $b): array {
            $start = $this->startOf($b);
            $durationMin = (int) ($b->duree_estimee ?? $b->estimated_duration_minutes ?? 60);

            return [
                'id' => $b->id,
                'title' => $b->serviceCatalog?->name ?? 'Prestation',
                'start' => $start?->toIso8601String(),
                'end' => $start?->copy()->addMinutes($durationMin > 0 ? $durationMin : 60)->toIso8601String(),
                'color' => self::STATUS_COLORS[(string) $b->status] ?? '#64748b',
                'status' => (string) $b->status,
                'site_name' => $b->organizationSite?->name,
                'service_name' => $b->serviceCatalog?->name,
                'reference' => $b->booking_reference,
            ];
        })->values();
    }

    /**
     * Sites the user has bookings at (for the calendar's site filter).
     *
     * @return Collection<int, OrganizationSite>
     */
    public function availableSitesForUser(User $user): Collection
    {
        $siteIds = Booking::query()
            ->where(fn ($w) => $w->where('client_id', $user->id)->orWhere('customer_user_id', $user->id))
            ->whereNotNull('organization_site_id')
            ->distinct()
            ->pluck('organization_site_id')
            ->all();

        if ($siteIds === []) {
            return collect();
        }

        return OrganizationSite::query()->whereIn('id', $siteIds)->orderBy('name')->get(['id', 'name']);
    }

    private function startOf(Booking $b): ?Carbon
    {
        if ($b->scheduled_at) {
            return Carbon::parse($b->scheduled_at);
        }
        if (! $b->date) {
            return null;
        }

        $time = $b->heure ?: '09:00';

        return Carbon::parse($b->date->toDateString().' '.$time);
    }
}
