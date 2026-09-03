<?php

namespace App\Models;

use Database\Factories\PeerStayMediumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une photo d'annonce.
 *
 * La couverture est une POSITION, pas un drapeau : deux « photos principales » s'excluent mal,
 * un ordre ne s'exclut jamais.
 */
class PeerStayMedium extends Model
{
    /** @use HasFactory<PeerStayMediumFactory> */
    use HasFactory;

    protected $table = 'peer_stay_media';

    protected $fillable = ['peer_stay_id', 'path', 'caption', 'position'];

    protected $casts = ['position' => 'integer'];

    /** @return BelongsTo<PeerStay, $this> */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(PeerStay::class, 'peer_stay_id');
    }
}
