<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** UNE OPTION RETIRÉE À QUELQU'UN — pour un temps, ou définitivement. */
class MissionFeatureSuspension extends Model
{
    /** Proposer un nouveau devis. */
    public const OPTION_REVISION = 'quote_revision';

    /** Passer commande. */
    public const OPTION_COMMANDE = 'ordering';

    protected $fillable = [
        'user_id',
        'feature',
        'level',
        'starts_at',
        'ends_at',
        'reason',
        'lifted_at',
        'lifted_by_user_id',
        'lift_reason',
    ];

    protected $casts = [
        'level' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'lifted_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Les suspensions qui MORDENT ENCORE, à cet instant.
     *
     * @param  Builder<MissionFeatureSuspension>  $query
     */
    public function scopeActives(Builder $query): void
    {
        $maintenant = Carbon::now();

        $query->whereNull('lifted_at')
            ->where('starts_at', '<=', $maintenant)
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $maintenant));
    }

    public function estDefinitive(): bool
    {
        return $this->ends_at === null;
    }
}
