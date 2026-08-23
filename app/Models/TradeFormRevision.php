<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Instantane complet du questionnaire d'un metier a l'instant d'une publication. */
class TradeFormRevision extends Model
{
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
