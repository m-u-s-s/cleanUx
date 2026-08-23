<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Logique conditionnelle : « Type de pistolet » ne s'affiche que si « Application au pistolet » vaut oui. */
class QuestionCondition extends Model
{
    protected $fillable = [
        'question_id', 'depends_on_question_id', 'operator', 'value', 'action',
    ];

    protected $casts = ['value' => 'array'];

    /**
     * La question dont l'affichage depend de cette condition.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Celle dont la reponse decide.
     *
     * @return BelongsTo<Question, $this>
     */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'depends_on_question_id');
    }
}
