<?php

namespace App\Models;

use Database\Factories\OrderDraftAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une réponse, et ce qu'elle a coûté.
 *
 * La question peut disparaître ; la réponse, non. `question_id` se détache si la question est
 * archivée — ce sont le CODE stable et les deux instantanés de libellé qui portent la vérité,
 * tels qu'ils étaient affichés au client au moment où il a répondu.
 *
 * Sans cet instantané, renommer une question six mois plus tard réécrirait rétroactivement des
 * devis et des factures déjà émis, et rendrait indéfendable tout litige portant dessus.
 *
 * `price_impact_cents` est ce qui rend le devis explicable ligne par ligne — chaque euro est
 * rattaché à une réponse. C'est la meilleure prévention des litiges : ils n'ont plus de prise.
 */
class OrderDraftAnswer extends Model
{
    /** @use HasFactory<OrderDraftAnswerFactory> */
    use HasFactory;

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
     * Peut être nulle sans que la réponse perde son sens : c'est précisément l'intérêt des
     * instantanés.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
