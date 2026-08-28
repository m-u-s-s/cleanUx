<?php

namespace App\Livewire\Concerns;

use App\Models\Booking;
use App\Services\CancellationV2\CancellationEngine;
use App\Services\CancellationV2\CancellationQuote;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Le parcours d'annulation d'un client : devis, modale, confirmation.
 * Partage par le tableau de bord et « Mes rendez-vous » — une seule implementation.
 */
trait AnnuleUnRendezVousClient
{
    public ?int $cancelRdvId = null;

    public string $cancelReason = '';

    /** Ouvre la modale sur ce rendez-vous. N'annule rien : le devis se lit d'abord. */
    public function demanderAnnulation(int $id): void
    {
        $rdv = Booking::findOrFail($id);
        Gate::authorize('cancel', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', message: 'Ce rendez-vous ne peut plus être annulé.', type: 'error');

            return;
        }

        $this->cancelRdvId = $id;
        $this->cancelReason = '';
    }

    /**
     * CE QUE L'ANNULATION VA COUTER, AVANT DE LA CONFIRMER.
     *
     * `null` quand le devis ne peut pas etre etabli : la modale montre alors ce qu'elle sait,
     * sans inventer un montant.
     */
    #[Computed]
    public function devisAnnulation(): ?CancellationQuote
    {
        if ($this->cancelRdvId === null) {
            return null;
        }

        try {
            return app(CancellationEngine::class)->quote(
                bookingId: $this->cancelRdvId,
                actorRole: 'client',
                actorUserId: Auth::id(),
            );
        } catch (\Throwable $e) {
            Log::warning('[cancellation_v2] devis indisponible', [
                'booking_id' => $this->cancelRdvId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function fermerAnnulation(): void
    {
        $this->cancelRdvId = null;
        $this->cancelReason = '';
    }

    /**
     * Annule pour de bon, par le moteur CancellationV2 — le meme que `AnnulerLaMission`.
     * L'autorisation se rejoue ici : `cancelRdvId` est publique, donc modifiable par `$set`.
     */
    public function confirmerAnnulation(): void
    {
        if ($this->cancelRdvId === null) {
            return;
        }

        $rdv = Booking::findOrFail($this->cancelRdvId);
        Gate::authorize('cancel', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', message: 'Ce rendez-vous ne peut plus être annulé.', type: 'error');
            $this->fermerAnnulation();

            return;
        }

        $raison = trim($this->cancelReason) ?: null;

        try {
            app(CancellationEngine::class)->execute(
                bookingId: $rdv->id,
                actor: Auth::user(),
                actorRole: 'client',
                reasonText: $raison,
            );
        } catch (ValidationException $e) {
            $this->dispatch('toast',
                message: collect($e->errors())->flatten()->first() ?? 'Annulation impossible.',
                type: 'error');
            $this->fermerAnnulation();

            return;
        }

        ActivityLogger::log('rdv_annule_par_client', $rdv, [
            'date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'heure' => $rdv->heure,
            'service' => $rdv->service_display_name,
            'service_identifier' => $rdv->service_identifier_display,
            'reason' => $raison,
        ]);

        $this->fermerAnnulation();
        $this->dispatch('toast', message: 'Rendez-vous annulé.', type: 'success');
    }
}
