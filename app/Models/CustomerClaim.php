<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerClaim extends Model
{
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
    ];

    protected $casts = [
        'attachments' => 'array',
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
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