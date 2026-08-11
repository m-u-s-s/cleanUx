<?php

namespace App\Models;

use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UN MOUVEMENT DE STOCK, AVEC SA CAUSE.
 *
 * C'est la table la plus importante des deux. Le stock courant seul répond « il reste douze » sans
 * jamais pouvoir répondre « pourquoi » — or c'est la seconde question qui se pose vraiment quand un
 * stock fond plus vite que prévu.
 *
 * LA QUANTITÉ EST SIGNÉE : positive à la réception, négative à la consommation. Un seul signe
 * obligerait chaque lecteur à savoir dans quel sens compter, et l'un d'eux se tromperait.
 *
 * @property int $id
 * @property int $inventory_item_id
 * @property int|null $user_id
 * @property int|null $mission_id
 * @property string $type
 * @property int $quantity
 * @property string|null $reason
 */
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    /** Entrée de stock : livraison, retour d'équipe. */
    public const TYPE_RECEPTION = 'reception';

    /** Sortie liée à une intervention. C'est le mouvement que F7 enregistre depuis le terrain. */
    public const TYPE_CONSUMPTION = 'consumption';

    /** Correction d'inventaire : casse, perte, recomptage. Elle se déclare, elle ne se cache pas. */
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'inventory_item_id',
        'user_id',
        'mission_id',
        'type',
        'quantity',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Qui a déclaré ce mouvement.
     *
     * Nullable : un mouvement peut venir d'un traitement automatique. « Système » est une réponse
     * honnête ; inventer un auteur en serait une fausse.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
