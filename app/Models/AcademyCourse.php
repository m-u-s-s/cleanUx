<?php

namespace App\Models;

use Database\Factories\AcademyCourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UNE FORMATION DE L'ACADÉMIE (E16). RÉUSSIR DOIT CHANGER QUELQUE CHOSE, sinon personne ne suit.
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property int|null $trade_id
 * @property string|null $badge_code
 * @property int $specialty_bonus
 * @property bool $is_published
 */
class AcademyCourse extends Model
{
    /** @use HasFactory<AcademyCourseFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'title', 'summary', 'content', 'trade_id',
        'duration_minutes', 'badge_code', 'specialty_bonus',
        'is_published', 'metadata',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'specialty_bonus' => 'integer',
        'is_published' => 'boolean',
        'metadata' => 'array',
    ];

    /** @return HasMany<AcademyCompletion, $this> */
    public function completions(): HasMany
    {
        return $this->hasMany(AcademyCompletion::class);
    }

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }
}
