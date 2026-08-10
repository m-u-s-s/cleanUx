<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
