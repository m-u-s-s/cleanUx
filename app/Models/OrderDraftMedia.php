<?php

namespace App\Models;

use Database\Factories\OrderDraftMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Photo jointe a une ligne de commande.
 *
 * Une photo vaut dix questions : elle permet au prestataire de comprendre en deux secondes ce que
 * dix questions decrivent mal. Distincte de MissionMedia, qui appartient au dossier d'execution.
 */
class OrderDraftMedia extends Model
{
    /** @use HasFactory<OrderDraftMediaFactory> */
    use HasFactory;

    protected $table = 'order_draft_media';

    protected $fillable = [
        'order_draft_item_id', 'uploaded_by_user_id', 'path', 'caption', 'size_bytes', 'mime_type',
    ];

    protected $casts = ['size_bytes' => 'integer'];

    /** @return BelongsTo<OrderDraftItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderDraftItem::class, 'order_draft_item_id');
    }
}
