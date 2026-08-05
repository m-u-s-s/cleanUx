<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rendez-vous de signature d'un contrat, généralement dans un local du client.
 *
 * Distinct de `RendezVous`, qui décrit une intervention de prestation.
 */
class SigningAppointment extends Model
{
    public const STATUT_PLANIFIE = 'scheduled';

    public const STATUT_SIGNE = 'completed';

    public const STATUT_ANNULE = 'cancelled';

    protected $fillable = [
        'organization_account_id',
        'contract_document_id',
        'organization_site_id',
        'signer_user_id',
        'scheduled_at',
        'status',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<ContractDocument, $this> */
    public function contractDocument(): BelongsTo
    {
        return $this->belongsTo(ContractDocument::class, 'contract_document_id');
    }

    /** @return BelongsTo<OrganizationSite, $this> */
    public function organizationSite(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
