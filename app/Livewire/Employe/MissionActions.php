<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use App\Services\Cancellation\CancelBookingService;
use App\Services\Missions\MissionDelayService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\RideLifecycleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionActions extends Component
{
    public Mission $mission;

    /** Le motif du retard, saisi par le prestataire. Court par construction. */
    public string $motifDuRetard = '';

    public string $startCode = '';

    public string $endCode = '';

    public ?string $generatedStartCode = null;

    public ?string $generatedEndCode = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    /** Position lue par le navigateur au moment de clôturer. */
    public ?float $lat = null;

    public ?float $lng = null;

    public ?float $accuracyM = null;

    /** Le statut au moment où la requête a commencé — jamais envoyé au navigateur. */
    protected ?string $statutAvantLAction = null;

    public function mount(Mission $mission): void
    {
        $this->mission = $mission->load(['assignments', 'verificationCodes', 'booking']);
        $this->statutAvantLAction = $this->mission->status;
    }

    public function hydrate(): void
    {
        $this->statutAvantLAction = $this->mission->status;
    }

    /** PRÉVENIR LA PAGE QUE LE STATUT A CHANGÉ. */
    protected function annoncerSiLeStatutAChange(): void
    {
        if ($this->statutAvantLAction === null || $this->statutAvantLAction === $this->mission->status) {
            return;
        }

        $this->dispatch('mission-statut-change', missionId: $this->mission->id);
        $this->statutAvantLAction = $this->mission->status;
    }

    /** CETTE MISSION EST-ELLE UNE COURSE ? */
    public function estUneCourse(): bool
    {
        return app(RideLifecycleService::class)->estUneCourse($this->mission);
    }

    /** L'ATTENTE AU POINT DE PRISE EN CHARGE, en secondes restantes. */
    public function secondesAvantAbsence(): ?int
    {
        $reservation = $this->mission->booking;

        if (! $reservation?->estUneCourse() || $this->mission->status !== 'arrived') {
            return null;
        }

        $arrivee = $this->mission->assignments()
            ->whereNotNull('arrived_at')
            ->orderByDesc('arrived_at')
            ->value('arrived_at');

        if (! $arrivee) {
            return null;
        }

        $echeance = Carbon::parse($arrivee)
            ->addMinutes((int) config('cancellation.no_show.ride_grace_minutes', 5));

        return max(0, (int) now()->diffInSeconds($echeance, false));
    }

    /** « Le client n'est pas venu. */
    public function declarerClientAbsent(): void
    {
        $this->resetMessages();

        try {
            app(CancelBookingService::class)->markClientNoShow(
                $this->mission->fresh()->booking,
                Auth::user(),
            );

            $this->mission = $this->mission->fresh(['assignments', 'verificationCodes', 'booking']);
            $this->successMessage = 'Absence du client enregistrée. La course est close.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /** Le client est monté : la course démarre, sans code. */
    public function demarrerLaCourse(): void
    {
        $this->resetMessages();

        try {
            $this->mission = app(RideLifecycleService::class)->demarrerLaCourse(
                $this->mission->fresh(),
                Auth::user(),
                $this->lat,
                $this->lng,
            );

            $this->successMessage = 'Course démarrée. Bonne route.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /** Arrivé à destination : la course se termine et le paiement est capturé. */
    public function terminerLaCourse(): void
    {
        $this->resetMessages();

        try {
            $this->mission = app(RideLifecycleService::class)->terminerLaCourse(
                $this->mission->fresh(),
                Auth::user(),
                $this->lat,
                $this->lng,
            );

            $this->successMessage = 'Course terminée.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function setEnRoute(): void
    {
        $this->resetMessages();

        try {
            $this->mission = $this->service()->setEnRoute(
                $this->mission->fresh(),
                Auth::user()
            );

            $this->successMessage = 'Mission passée en route.';
            $this->dispatch('mission-en-route-start-tracking', missionId: $this->mission->id);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function setArrived(): void
    {
        $this->resetMessages();

        try {
            $this->mission = $this->service()->setArrived(
                $this->mission->fresh(),
                Auth::user()
            );

            $this->generatedStartCode = session('mission_start_code_'.$this->mission->id);
            $this->successMessage = 'Arrivée confirmée. Code de début généré.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function startMission(): void
    {
        $this->resetMessages();

        $this->validate([
            'startCode' => ['required', 'digits:6'],
        ]);

        try {
            $this->mission = $this->service()->validateStartCode(
                $this->mission->fresh(),
                Auth::user(),
                $this->startCode
            );

            $this->startCode = '';
            $this->generatedStartCode = null;
            $this->successMessage = 'Mission démarrée avec succès.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function prepareEndCode(): void
    {
        $this->resetMessages();

        try {
            $generated = $this->service()->generateEndCode($this->mission->fresh());
            $this->generatedEndCode = $generated['code'];

            session()->put('mission_end_code_'.$this->mission->id, $generated['code']);

            $this->mission = $this->mission->fresh(['assignments', 'verificationCodes', 'booking']);

            $this->successMessage = 'Code de fin généré.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /** Clôture la mission — encaissement compris — et exige d'être sur place. */
    public function finishMission(): void
    {
        $this->resetMessages();

        $this->validate([
            'endCode' => ['required', 'digits:6'],
        ]);

        try {
            $this->mission = $this->service()->validateEndCode(
                $this->mission->fresh(),
                Auth::user(),
                $this->endCode,
                $this->lat,
                $this->lng,
                $this->accuracyM,
                false,
                requirePosition: true,
            );

            $this->endCode = '';
            $this->generatedEndCode = null;
            $this->successMessage = 'Mission terminée avec succès.';
            session()->forget('mission_end_code_'.$this->mission->id);
            session()->forget('mission_start_code_'.$this->mission->id);
        } catch (\Throwable $e) {
            // Le motif remonte tel quel, y compris pour un refus de position : `summarize()` place le premier message d'une ValidationException dans `getMessage()`.
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function service(): MissionLifecycleService
    {
        return app(MissionLifecycleService::class);
    }

    protected function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    /** ANNONCER SON RETARD — le seul geste qui evite l'annulation gratuite. */
    public function annoncerLeRetard(int $minutes): void
    {
        $this->resetMessages();

        $booking = $this->mission->booking;

        if ($booking === null) {
            return;
        }

        app(MissionDelayService::class)->annoncerParLePrestataire(
            $booking,
            Carbon::now()->addMinutes(max(1, min(600, $minutes))),
            $this->motifDuRetard === '' ? null : $this->motifDuRetard,
        );

        $this->successMessage = "Votre client est prevenu : arrivee annoncee dans {$minutes} min.";
    }

    public function render()
    {
        $this->annoncerSiLeStatutAChange();

        $booking = $this->mission->booking;

        return view('livewire.employe.mission-actions', [
            'retard' => $booking === null ? null : app(MissionDelayService::class)->etat($booking),
        ]);
    }
}
