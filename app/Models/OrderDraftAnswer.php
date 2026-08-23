<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une réponse, et ce qu'elle a coûté. La question peut disparaître ; la réponse, non. */
class OrderDraftAnswer extends Model
{
    protected $fillable = [
        'order_draft_item_id', 'question_id', 'question_code', 'question_label_snapshot',
        'answer_value', 'answer_label_snapshot', 'price_impact_cents', 'duration_impact_min',
        'is_unknown',
    ];

    protected $casts = [
        'answer_value' => 'array',
        'price_impact_cents' => 'integer',
        'duration_impact_min' => 'integer',
        'is_unknown' => 'boolean',
    ];

    /** @return BelongsTo<OrderDraftItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderDraftItem::class, 'order_draft_item_id');
    }

    /**
     * La question d'origine, si elle existe encore.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
