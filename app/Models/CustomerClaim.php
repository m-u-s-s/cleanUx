<?php

namespace App\Models;

use App\Support\Claims\ResolutionAffichee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class CustomerClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'rendez_vous_id',
        'category',
        'priority',
        'status',
        'title',
        'description',
        'attachments',
        'sla_due_at',
        'resolved_at',

        // L'ISSUE, ÉCRITE PAR LE SUPPORT ET LUE PAR LE CLIENT. Elle manquait à cette liste :
        // `update(['resolution' => ...])` levait une MassAssignmentException, et l'écran du
        // client n'avait donc jamais rien à afficher sous « Résolution ».
        'resolution',

        // ÉCRITES PAR LE CODE, ÉCARTÉES PAR ELOQUENT. Ces colonnes existent en base et des
        // appels d'écriture les renseignent, mais leur absence de cette liste les faisait
        // disparaître SANS ERREUR — Eloquent écarte en silence ce qu'il ne peut pas assigner.
        'customer_user_id',
    ];

    protected $casts = [
        'attachments' => 'array',
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * LE FIL DE LA RÉCLAMATION — ce que l'écran client affiche et alimente.
     *
     * @return HasMany<CustomerClaimEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CustomerClaimEvent::class, 'customer_claim_id')->oldest();
    }

    /**
     * LA RÉSOLUTION, PRÉSENTÉE COMME UNE LISTE — et il y en a au plus une.
     *
     * La vue parcourt `resolutions` : c'est la forme qu'elle attend. Cette réclamation-ci ne
     * porte qu'une issue, dans ses colonnes `resolution` et `resolved_at`. On la rend donc
     * sous la forme attendue plutôt que de créer une table pour une ligne unique — et sans
     * emprunter celle de l'autre modèle de litige, qui a ses propres règles.
     *
     * @return Collection<int, ResolutionAffichee>
     */
    public function getResolutionsAttribute(): Collection
    {
        if (blank($this->resolution)) {
            return collect();
        }

        return collect([new ResolutionAffichee(
            resolution_type: $this->status === 'closed' ? 'Clôturée' : 'Résolue',
            explanation: (string) $this->resolution,
            created_at: $this->resolved_at,
        )]);
    }

    /**
     * La référence lisible. `claim_reference` n'est renseignée par aucun chemin d'écriture :
     * l'écran affichait un vide là où le client cherche un numéro à citer au support.
     */
    public function getReferenceAttribute(): string
    {
        return filled($this->claim_reference)
            ? (string) $this->claim_reference
            : 'REC-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** L'intitulé, sous le nom que la vue emploie. */
    public function getSubjectAttribute(): string
    {
        return (string) $this->title;
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Ouvert',
            'in_review' => 'En traitement',
            'waiting_client' => 'En attente client',
            'resolved' => 'Résolu',
            'closed' => 'Clôturé',
            default => ucfirst($this->status),
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'quality' => 'Qualité du nettoyage',
            'delay' => 'Retard',
            'damage' => 'Dégât / dommage',
            'billing' => 'Facturation',
            'employee_behavior' => 'Comportement employé',
            'missing_service' => 'Service non réalisé',
            default => ucfirst($this->category),
        };
    }
}
