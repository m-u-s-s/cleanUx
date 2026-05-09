<?php

namespace App\Livewire\Provider;

use App\Models\MissionAssignment;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Phase 11 — Page de réponse à une offre de mission (web).
 *
 * Affichée quand le prestataire clique sur la notification push :
 *   /provider/missions/{assignment}/offer
 *
 * Affiche :
 *   - Timer countdown (10, 9, 8…)
 *   - Détails de la mission (service, adresse, prix, planning)
 *   - 2 gros boutons Accepter / Refuser
 *
 * Si déjà accepté/refusé/expiré → affiche le statut sans permettre l'action.
 */
class MissionOfferPage extends Component
{
    public int $assignmentId;
    public ?string $declineReason = null;
    public ?string $message = null;
    public ?string $messageType = null;

    public function mount(int $assignment): void
    {
        $this->assignmentId = $assignment;
    }

    public function getAssignmentProperty(): ?MissionAssignment
    {
        return MissionAssignment::with([
            'mission:id,booking_id,planned_start_at,status,client_price,estimated_duration_minutes',
            'mission.booking:id,booking_reference,address,city,postal_code,scheduled_date,scheduled_time,booking_mode,priority,customer_comment,service_catalog_id',
            'mission.booking.serviceCatalog:id,name',
        ])->find($this->assignmentId);
    }

    public function accept(): void
    {
        $assignment = $this->assignment;
        if (! $this->checkOwnership($assignment)) return;

        try {
            app(MissionDispatchService::class)->accept($assignment);
            $this->flashMessage('✅ Mission acceptée. Vous serez guidé vers les détails.', 'success');
            $this->redirect('/dashboard', navigate: false);
        } catch (\DomainException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur lors de l\'acceptation.', 'error');
        }
    }

    public function decline(): void
    {
        $assignment = $this->assignment;
        if (! $this->checkOwnership($assignment)) return;

        try {
            app(MissionDispatchService::class)->decline($assignment, $this->declineReason);
            $this->flashMessage('Mission refusée. Elle sera proposée à un autre prestataire.', 'success');
            $this->redirect('/dashboard', navigate: false);
        } catch (\DomainException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur lors du refus.', 'error');
        }
    }

    public function clearMessage(): void
    {
        $this->message = null;
        $this->messageType = null;
    }

    protected function checkOwnership(?MissionAssignment $assignment): bool
    {
        if (! $assignment) {
            $this->flashMessage('Cette offre n\'existe plus.', 'error');
            return false;
        }
        if ((int) $assignment->user_id !== (int) Auth::id()) {
            $this->flashMessage('Cette offre ne vous est pas destinée.', 'error');
            return false;
        }
        return true;
    }

    protected function flashMessage(string $msg, string $type): void
    {
        $this->message = $msg;
        $this->messageType = $type;
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.provider.mission-offer-page');
    }
}
