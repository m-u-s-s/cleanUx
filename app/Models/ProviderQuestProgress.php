<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * OÙ EN EST QUELQU'UN D'UN OBJECTIF (E13).
 *
 * `completed_at` ET `rewarded_at` SONT DEUX CHOSES. Atteindre l'objectif et être payé sont deux
 * événements distincts : les confondre ferait payer deux fois au moindre rejeu, ou jamais.
 *
 * @property int $id
 * @property int $provider_quest_id
 * @property int $user_id
 * @property int $progress
 * @property Carbon|null $completed_at
 * @property Carbon|null $rewarded_at
 */
class ProviderQuestProgress extends Model
{
    protected $table = 'provider_quest_progress';

    protected $fillable = [
        'provider_quest_id', 'user_id', 'progress', 'completed_at', 'rewarded_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'completed_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    /** @return BelongsTo<ProviderQuest, $this> */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(ProviderQuest::class, 'provider_quest_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
