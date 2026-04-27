<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCredit extends Model
{
    protected $fillable = [
        'client_id',
        'rendez_vous_id',
        'type',
        'amount',
        'remaining_amount',
        'status',
        'reason',
        'notes',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function isUsable(): bool
    {
        return $this->status === 'active'
            && $this->remaining_amount > 0
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}