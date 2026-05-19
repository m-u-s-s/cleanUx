<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TradeZoneSetting — activation et tarification d'un métier (Trade) dans une zone (ServiceZone).
 *
 * Absence de ligne = métier implicitement actif avec multiplicateur 1.00 (back-compat).
 * Présence avec is_active=false = métier explicitement désactivé dans la zone.
 */
class TradeZoneSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'trade_id',
        'service_zone_id',
        'is_active',
        'price_multiplier',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'price_multiplier' => 'decimal:2',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
