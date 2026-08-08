<?php

namespace App\Models;

use Database\Factories\OrderDraftItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une ligne de commande = un métier.
 *
 * C'est cette table qui rend le mode multi-services possible sans cas particulier : une commande
 * à un métier est simplement une commande à une ligne. Le mode « panier » n'est donc pas une
 * branche du code, c'est le cas général.
 *
 * `depends_on_item_id` et `sequence_gap_min` portent l'ordonnancement du chantier réel : le
 * carreleur passe après le plombier, et pas immédiatement après — il faut laisser sécher.
 */
class OrderDraftItem extends Model
{
    /** @use HasFactory<OrderDraftItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_draft_id', 'trade_id', 'trade_form_revision_id', 'provider_id',
        'sequence', 'depends_on_item_id', 'sequence_gap_min',
        'status', 'scheduled_at',
        'estimate_min_cents', 'estimate_max_cents', 'duration_min', 'metadata',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'sequence_gap_min' => 'integer',
        'scheduled_at' => 'datetime',
        'estimate_min_cents' => 'integer',
        'estimate_max_cents' => 'integer',
        'duration_min' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<OrderDraft, $this> */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(OrderDraft::class, 'order_draft_id');
    }

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return HasMany<OrderDraftAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(OrderDraftAnswer::class, 'order_draft_item_id');
    }

    /** @return HasMany<OrderDraftMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(OrderDraftMedia::class, 'order_draft_item_id');
    }

    /** @return BelongsTo<self, $this> */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_item_id');
    }

    /** @return BelongsTo<TradeFormRevision, $this> */
    public function formRevision(): BelongsTo
    {
        return $this->belongsTo(TradeFormRevision::class, 'trade_form_revision_id');
    }

    /**
     * Réponses indexées par code de question.
     *
     * Le code, jamais l'identifiant : c'est la seule clé qui traverse un export, une duplication
     * de questionnaire et l'archivage d'une question.
     *
     * @return array<string, OrderDraftAnswer>
     */
    public function answersByCode(): array
    {
        return $this->answers->keyBy('question_code')->all();
    }
}
