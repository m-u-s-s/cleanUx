<?php

namespace App\Models;

use Database\Factories\TradeBundleSuggestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * « Souvent commande avec » : les associations sont des donnees, configurables par
 * l'administrateur sur chaque metier, jamais une liste ecrite en dur.
 *
 * `default_sequence_gap_min` porte le temps de sechage : le carreleur passe apres le plombier,
 * et pas immediatement apres.
 */
class TradeBundleSuggestion extends Model
{
    /** @use HasFactory<TradeBundleSuggestionFactory> */
    use HasFactory;

    protected $fillable = [
        'trade_id', 'suggested_trade_id', 'default_sequence_gap_min', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'default_sequence_gap_min' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return BelongsTo<Trade, $this> */
    public function suggestedTrade(): BelongsTo
    {
        return $this->belongsTo(Trade::class, 'suggested_trade_id');
    }
}
