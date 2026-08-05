<?php

namespace App\Services\Contracts;

use App\Models\ContractDocument;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\SigningAppointment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Planifier et clore un rendez-vous de signature de contrat.
 *
 * La signature électronique et les rendez-vous d'intervention existaient déjà, sans lien entre eux.
 * Ce service comble ce manque pour le cas B2B courant : faire signer un contrat-cadre en présence,
 * dans un local du client.
 */
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

        /*
         * Le local vient d'une sélection côté navigateur. Sans cette vérification, on pourrait
         * fixer un rendez-vous dans les murs d'une autre société — et en révéler l'adresse au
         * passage, puisque l'écran l'affiche ensuite.
         */
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
