<?php

namespace App\Models;

use Database\Factories\PeerCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * LE CODE A SIX CHIFFRES DE LA REMISE ET DU RETOUR.
 *
 * Le locataire l'affiche, le proprietaire le saisit. Il n'est jamais stocke en clair, et il
 * ne suffit pas a lui seul : l'etat des lieux doit etre signe des deux cotes.
 */
class PeerCode extends Model
{
    /** @use HasFactory<PeerCodeFactory> */
    use HasFactory;

    public const PHASE_REMISE = 'handover';

    public const PHASE_RETOUR = 'return';

    /** Cinq essais, puis le code se regenere : sinon il se devine en six mille coups. */
    public const ESSAIS_MAX = 5;

    protected $fillable = [
        'peer_rental_id', 'phase', 'code_hash', 'expires_at', 'attempts', 'consumed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /** @return BelongsTo<PeerRental, $this> */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(PeerRental::class, 'peer_rental_id');
    }

    public function correspond(string $saisi): bool
    {
        return Hash::check($saisi, $this->code_hash);
    }

    public function estUtilisable(): bool
    {
        return $this->consumed_at === null
            && $this->attempts < self::ESSAIS_MAX
            && $this->expires_at->isFuture();
    }
}
