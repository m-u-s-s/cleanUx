<?php

namespace App\Models;

use Database\Factories\PeerReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UN AVIS CROISE, REVELE A L'AVEUGLE.
 *
 * Les deux avis n'apparaissent qu'une fois les DEUX deposes, ou passe le delai : revele
 * plus tot, le second se calque sur le premier et la note ne mesure plus rien.
 */
class PeerReview extends Model
{
    /** @use HasFactory<PeerReviewFactory> */
    use HasFactory;

    public const ROLE_PROPRIETAIRE = 'owner';

    public const ROLE_LOCATAIRE = 'renter';

    /** Passe ce delai, l'avis depose se revele seul : l'autre ne viendra plus. */
    public const JOURS_AVANT_REVELATION = 14;

    protected $fillable = [
        'peer_rental_id', 'author_id', 'target_id', 'author_role',
        'rating', 'comment', 'submitted_at', 'revealed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'revealed_at' => 'datetime',
        'rating' => 'integer',
    ];

    /** @return BelongsTo<PeerRental, $this> */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(PeerRental::class, 'peer_rental_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    /**
     * @param  Builder<PeerReview>  $query
     * @return Builder<PeerReview>
     */
    public function scopeReveles(Builder $query): Builder
    {
        return $query->whereNotNull('revealed_at');
    }
}
