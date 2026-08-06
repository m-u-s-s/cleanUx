<?php

namespace App\Models;

use Database\Factories\TradeFormRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instantane complet du questionnaire d'un metier a l'instant d'une publication.
 *
 * La commande retient la revision employee. C'est ce qui permet de rejouer un devis vieux de six
 * mois exactement tel qu'il a ete calcule, meme si le questionnaire a change trois fois depuis.
 */
class TradeFormRevision extends Model
{
    /** @use HasFactory<TradeFormRevisionFactory> */
    use HasFactory;

    protected $fillable = ['trade_id', 'version', 'schema', 'published_by_user_id', 'published_at'];

    protected $casts = [
        'version' => 'integer',
        'schema' => 'array',
        'published_at' => 'datetime',
    ];

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
