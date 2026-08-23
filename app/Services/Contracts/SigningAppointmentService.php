<?php

namespace App\Services\Contracts;

use App\Models\ContractDocument;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\SigningAppointment;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Planifier et clore un rendez-vous de signature de contrat. */
class SigningAppointmentService
{
    /**
     * @param  OrganizationSite|null  $site  le local visé ; son appartenance est revérifiée ici
     * @return SigningAppointment|null `null` si la demande est irrecevable
     */
    public function planifier(
        OrganizationAccount $organisation,
        User $signataire,
        Carbon $quand,
        ?OrganizationSite $site = null,
        ?ContractDocument $document = null,
        ?string $notes = null,
    ): ?SigningAppointment {
        // Un rendez-vous déjà écoulé ne serait jamais honoré : le refuser vaut mieux que le créer
        // puis le laisser pourrir dans la liste.
        if ($quand->isPast()) {
            return null;
        }

        // Le local vient d'une sélection côté navigateur.
        if ($site !== null && (int) $site->organization_account_id !== (int) $organisation->id) {
            return null;
        }

        return SigningAppointment::query()->create([
            'organization_account_id' => $organisation->id,
            'contract_document_id' => $document?->id,
            'organization_site_id' => $site?->id,
            'signer_user_id' => $signataire->id,
            'scheduled_at' => $quand,
            'status' => SigningAppointment::STATUT_PLANIFIE,
            'notes' => $notes,
        ]);
    }

    /** Le contrat a été signé : le rendez-vous sort des échéances à venir. */
    public function marquerSigne(SigningAppointment $rdv): void
    {
        $rdv->update([
            'status' => SigningAppointment::STATUT_SIGNE,
            'completed_at' => now(),
        ]);
    }

    public function annuler(SigningAppointment $rdv): void
    {
        $rdv->update(['status' => SigningAppointment::STATUT_ANNULE]);
    }
}
