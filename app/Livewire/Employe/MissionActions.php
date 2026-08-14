<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use App\Services\Cancellation\CancelBookingService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\RideLifecycleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionActions extends Component
{
    public Mission $mission;

    public string $startCode = '';

    public string $endCode = '';

    public ?string $generatedStartCode = null;

    public ?string $generatedEndCode = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    /**
     * Position lue par le navigateur au moment de clôturer.
     *
     * Renseignée depuis la vue avant l'appel à {@see finishMission()}. Elle vient du client, donc
     * de la partie contrôlée — comme sur mobile. Ce n'est pas ce qui la rend inutile : le serveur
     * ne lui fait pas confiance, il la CONFRONTE au lieu de l'intervention. Mentir suppose de
     * choisir des coordonnées, pas seulement d'omettre une preuve.
     */
    public ?float $lat = null;

    public ?float $lng = null;

    public ?float $accuracyM = null;

    /**
     * Le statut au moment où la requête a commencé — jamais envoyé au navigateur.
     *
     * Il ne sert qu'à répondre, en fin de requête, à une seule question : « le statut a-t-il
     * changé ? » {@see self::dehydrate()}
     */
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

    /**
     * PRÉVENIR LA PAGE QUE LE STATUT A CHANGÉ.
     *
     * Les blocs voisins — suivi de trajet, exécution — sont ouverts ou fermés par la page parente,
     * d'après un statut qu'elle a lu au chargement. Ce composant le faisait avancer sans le lui
     * dire : le prestataire passait « en route » et lisait, juste en dessous, « le tracking
     * devient disponible quand la mission passe en route ». Il fallait recharger la page pour que
     * la phrase cesse de se contredire, et rien ne le disait.
     *
     * L'annonce est posée ICI plutôt que dans chaque action : il y en a sept aujourd'hui, et celle
     * qu'on ajoutera demain n'aurait pas eu à y penser.
     *
     * Elle part depuis `render()` et non depuis `dehydrate()` : ce dernier s'exécute une fois les
     * effets de la réponse déjà rassemblés, et l'événement n'atteignait jamais le navigateur.
     */
    protected function annoncerSiLeStatutAChange(): void
    {
        if ($this->statutAvantLAction === null || $this->statutAvantLAction === $this->mission->status) {
            return;
        }

        $this->dispatch('mission-statut-change', missionId: $this->mission->id);
        $this->statutAvantLAction = $this->mission->status;
    }

    /**
     * CETTE MISSION EST-ELLE UNE COURSE ?
     *
     * La vue s'en sert pour proposer « Client à bord » et « Terminer la course » à la place des
     * champs de code. Recalculé à chaque appel plutôt que figé au montage : Livewire ne rejoue pas
     * `mount()`, et un état retenu là deviendrait faux dès que la réservation change.
     */
    public function estUneCourse(): bool
    {
        return app(RideLifecycleService::class)->estUneCourse($this->mission);
    }

    /**
     * L'ATTENTE AU POINT DE PRISE EN CHARGE, en secondes restantes.
     *
     * `null` quand la question ne se pose pas — pas une course, pas encore arrivé, ou déjà
     * démarrée. `0` quand l'attente est écoulée et que l'absence peut être déclarée.
     *
     * Le décompte est calculé par le SERVEUR, jamais par un minuteur de page : un compte à rebours
     * client seul se remet à zéro d'un rechargement, et il suffirait alors d'actualiser pour
     * déclarer un passager absent au bout de trois secondes.
     */
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

    /**
     * « Le client n'est pas venu. »
     *
     * Ce geste n'existait pas sur une course : la détection d'absence exigeait un horaire prévu, et
     * une commande immédiate n'en a pas — le conducteur devant une porte fermée n'avait donc
     * littéralement aucune action disponible, ni pour partir, ni pour être payé de son
     * déplacement.
     */
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

    /**
     * Arrivé à destination : la course se termine et le paiement est capturé.
     *
     * La position part avec l'appel, comme pour une clôture ordinaire — elle sera confrontée au
     * point de DÉPOSE, pas au point de départ.
     */
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

    /**
     * Clôture la mission — encaissement compris — et exige d'être sur place.
     *
     * Le code de fin atteste d'une possession : photographié ou dicté au téléphone, il permettrait
     * de facturer une intervention quittée depuis longtemps. Ce tableau de bord est celui du
     * prestataire (route `role:employe`), pas un outil d'administration : exiger la position n'y
     * bloque aucune correction faite depuis un bureau.
     */
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
            /*
             * Le motif remonte tel quel, y compris pour un refus de position : `summarize()` place
             * le premier message d'une ValidationException dans `getMessage()`. Un traitement
             * dédié n'ajouterait rien — vérifié plutôt que supposé, un bloc `catch` séparé s'est
             * révélé strictement équivalent. Ce que le prestataire doit lire, c'est qu'il est trop
             * loin ou que sa localisation est coupée, pas une formule générique.
             */
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

    public function render()
    {
        $this->annoncerSiLeStatutAChange();

        return view('livewire.employe.mission-actions');
    }
}
