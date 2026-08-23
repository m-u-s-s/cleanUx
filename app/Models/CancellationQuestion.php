<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** UNE QUESTION DU FORMULAIRE D'ANNULATION — à qui on la pose, et quand. */
class CancellationQuestion extends Model
{
    use SoftDeletes;

    public const AUDIENCE_CLIENT = 'client';

    public const AUDIENCE_PRESTATAIRE = 'provider';

    public const AUDIENCE_LES_DEUX = 'both';

    protected $fillable = [
        'code', 'audience', 'engine', 'moment',
        'label', 'help_text', 'sort_order', 'is_active', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /** @return HasMany<CancellationQuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(CancellationQuestionOption::class);
    }

    /**
     * Les questions posées à cette audience — la sienne, ou celles posées à tout le monde.
     *
     * @param  Builder<CancellationQuestion>  $query
     */
    public function scopePourAudience(Builder $query, string $audience): void
    {
        $query->whereIn('audience', [$audience, self::AUDIENCE_LES_DEUX]);
    }
}
