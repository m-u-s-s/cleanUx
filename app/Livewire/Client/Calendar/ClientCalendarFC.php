<?php

namespace App\Livewire\Client\Calendar;

use App\Models\Booking;
use App\Services\Client\Calendar\BookingRescheduleService;
use App\Services\Client\Calendar\CalendarDataService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Phase 6.1 — Calendrier FullCalendar avec drag-and-drop. */
class ClientCalendarFC extends Component
{
    #[Locked]
    public ?int $selectedBookingId = null;

    public ?string $message = null;

    public ?string $messageType = null; // success | error

    /** Filtres simples */
    public array $siteIds = [];

    public array $statuses = [];

    public function fetchEvents(string $startIso, string $endIso): array
    {
        $user = Auth::user();
        $service = app(CalendarDataService::class);

        $events = $service->eventsForUser($user, [
            'from' => Carbon::parse($startIso),
            'to' => Carbon::parse($endIso),
            'site_ids' => $this->siteIds ?: null,
            'statuses' => $this->statuses ?: null,
        ]);

        return $events->map(fn ($e) => [
            'id' => $e['id'],
            'title' => $e['title'],
            'start' => $e['start'],
            'end' => $e['end'],
            'backgroundColor' => $e['color'],
            'borderColor' => $e['color'],
            'editable' => $this->isEditable($e['status']),
            'extendedProps' => [
                'site_name' => $e['site_name'] ?? null,
                'service_name' => $e['service_name'] ?? null,
                'status' => $e['status'],
                'reference' => $e['reference'] ?? null,
            ],
        ])->all();
    }

    /** Appelé par le JS quand l'utilisateur drop un événement à une nouvelle date. */
    public function handleEventDrop(int $bookingId, string $newStartIso): void
    {
        $user = Auth::user();
        $booking = Booking::find($bookingId);

        if (! $booking) {
            $this->flashMessage('Réservation introuvable.', 'error');
            $this->dispatch('calendar:revert');

            return;
        }

        try {
            $newStart = Carbon::parse($newStartIso);
            app(BookingRescheduleService::class)->reschedule(
                $user,
                $booking,
                $newStart,
                $newStart->format('H:i'),
                'Reprogrammation via drag-and-drop',
            );

            $this->flashMessage(
                'Rendez-vous reprogrammé au '.$newStart->locale('fr')->isoFormat('ddd D MMM').' à '.$newStart->format('H:i'),
                'success'
            );
            $this->dispatch('calendar:refresh');
        } catch (\DomainException $e) {
            $this->flashMessage($e->getMessage(), 'error');
            $this->dispatch('calendar:revert');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Une erreur est survenue. Réessayez.', 'error');
            $this->dispatch('calendar:revert');
        }
    }

    public function selectEvent(int $bookingId): void
    {
        $this->selectedBookingId = $bookingId;
    }

    public function clearSelection(): void
    {
        $this->selectedBookingId = null;
    }

    public function clearMessage(): void
    {
        $this->message = null;
        $this->messageType = null;
    }

    private function isEditable(string $status): bool
    {
        $finals = ['termine', 'completed', 'done', 'annule', 'cancelled', 'refuse', 'sur_place', 'on_site'];

        return ! in_array($status, $finals, true);
    }

    private function flashMessage(string $text, string $type): void
    {
        $this->message = $text;
        $this->messageType = $type;
    }

    public function getSelectedBookingProperty(): ?Booking
    {
        if (! $this->selectedBookingId) {
            return null;
        }

        $booking = Booking::with(['serviceCatalog:id,name', 'organizationSite:id,name'])
            ->find($this->selectedBookingId);

        if (! $booking) {
            return null;
        }

        // Le panneau affiche l'adresse du lieu d'intervention : on repose ici
        // la règle d'appartenance du service de reprogrammation plutôt que d'en
        // écrire une seconde.
        $user = Auth::user();
        if (! $user || ! app(BookingRescheduleService::class)->peutAcceder($user, $booking)) {
            return null;
        }

        return $booking;
    }

    public function render(): View
    {
        $user = Auth::user();
        $sites = app(CalendarDataService::class)->availableSitesForUser($user);

        return view('livewire.client.calendar.client-calendar-fc', [
            'sites' => $sites,
        ]);
    }
}
