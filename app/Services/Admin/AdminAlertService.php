<?php

namespace App\Services\Admin;

use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AdminAlertService
{
    public function alerts(): array
    {
        return [
            'late_missions' => $this->lateMissions(),
            'not_started_soon' => $this->notStartedSoon(),
            'tracking_inactive' => $this->trackingInactive(),
            'payment_not_captured' => $this->paymentNotCaptured(),
            // TROIS ALERTES DE DISPONIBILITÉ — il n'y en avait AUCUNE.
            'providers_without_availability' => $this->providersWithoutAvailability(),
            'providers_fully_closed_week' => $this->providersFullyClosedThisWeek(),
            'providers_closing_spree' => $this->providersClosingSpree(),
        ];
    }

    /**
     * Qui est prestataire — la même définition que `DefaultAvailabilityProvisioner::provision()`.
     *
     * @param  Builder<User>  $query
     */
    protected function scopePrestataires(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereHas('providerProfile')->orWhere('role', 'employe');
        });
    }

    /**
     * Aucun créneau du tout : le compte existe, il est actif, et la planification ne le verra jamais.
     *
     * @return EloquentCollection<int, User>
     */
    protected function providersWithoutAvailability(): EloquentCollection
    {
        // LA MÊME DÉFINITION QUE LA FICHE, SINON L'ALERTE OFFRE DES LIENS MORTS.
        return User::query()
            ->tap(fn ($q) => $this->scopePrestataires($q))
            ->where('is_active', true)
            ->whereDoesntHave('availabilitySlots')
            ->orderBy('name')
            ->take(30)
            ->get()
            ->filter(fn (User $u) => $u->isEmploye())
            ->take(10)
            ->values();
    }

    /**
     * Une semaine entièrement fermée par exceptions : le prestataire a des créneaux, mais chaque jour des sept prochains est explicitement fermé.
     *
     * @return Collection<int, User>
     */
    protected function providersFullyClosedThisWeek(): Collection
    {
        $debut = now()->startOfDay();
        $fin = now()->addDays(6)->endOfDay();

        return User::query()
            ->tap(fn ($q) => $this->scopePrestataires($q))
            ->where('is_active', true)
            ->whereHas('availabilitySlots')
            ->withCount(['availabilityExceptions as jours_fermes' => fn ($q) => $q
                ->where('exception_type', AvailabilityException::TYPE_CLOSED)
                ->whereBetween('date', [$debut, $fin])])
            ->get()
            ->where('jours_fermes', '>=', 7)
            ->take(10)
            ->values();
    }

    /**
     * Beaucoup de fermetures d'un coup, sur trente jours à venir.
     *
     * @return Collection<int, User>
     */
    protected function providersClosingSpree(): Collection
    {
        return User::query()
            ->tap(fn ($q) => $this->scopePrestataires($q))
            ->where('is_active', true)
            ->withCount(['availabilityExceptions as jours_fermes' => fn ($q) => $q
                ->where('exception_type', AvailabilityException::TYPE_CLOSED)
                ->whereBetween('date', [now()->startOfDay(), now()->addDays(30)->endOfDay()])])
            ->get()
            ->where('jours_fermes', '>=', 15)
            ->sortByDesc('jours_fermes')
            ->take(10)
            ->values();
    }

    protected function lateMissions()
    {
        return Mission::query()
            ->with(['booking.client', 'leadEmployee'])
            ->whereNotNull('planned_start_at')
            ->where('planned_start_at', '<', now()->subMinutes(15))
            ->whereNotIn('status', ['started', 'completed', 'cancelled'])
            ->latest()
            ->take(10)
            ->get();
    }

    protected function notStartedSoon()
    {
        return Mission::query()
            ->with(['booking.client', 'leadEmployee'])
            ->whereBetween('planned_start_at', [now(), now()->addMinutes(30)])
            ->whereIn('status', ['assigned', 'confirme'])
            ->latest()
            ->take(10)
            ->get();
    }

    protected function trackingInactive()
    {
        return Mission::query()
            ->with(['booking.client', 'leadEmployee', 'activeTrackingSession'])
            ->where('status', 'en_route')
            ->whereDoesntHave('activeTrackingSession')
            ->latest()
            ->take(10)
            ->get();
    }

    protected function paymentNotCaptured()
    {
        return Booking::query()
            ->with(['client', 'employe', 'mission'])
            ->whereHas('mission', fn ($q) => $q->where('status', 'completed'))
            ->where('payment_status', 'authorized')
            ->latest()
            ->take(10)
            ->get();
    }
}
