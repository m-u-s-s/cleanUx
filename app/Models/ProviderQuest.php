<?php

namespace App\Models;

use Database\Factories\ProviderQuestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * UN OBJECTIF PROPOSÉ AUX PRESTATAIRES (E13).
 *
 * CE QUI MANQUE N'EST PAS LA GAMIFICATION, C'EST LA VISIBILITÉ DU PROGRÈS. Les badges existent mais
 * se découvrent une fois obtenus : on n'a jamais dit à quelqu'un qu'il lui manquait deux missions.
 * Une quête sans compteur visible n'est pas une quête, c'est une surprise.
 *
 * @property int $id
 * @property string $code
 * @property string $metric
 * @property int $target
 * @property string $reward_type
 * @property int $reward_value
 * @property bool $is_active
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 */
class ProviderQuest extends Model
{
    /** @use HasFactory<ProviderQuestFactory> */
    use HasFactory;

    public const METRIC_MISSIONS = 'missions_completed';

    public const METRIC_RATINGS = 'ratings_received';

    /** La récompense passe par Loyalty : en inventer une monnaie de plus créerait une comptabilité. */
    public const REWARD_LOYALTY = 'loyalty_points';

    public const REWARD_BONUS = 'bonus_cents';

    protected $fillable = [
        'code', 'title', 'description', 'metric', 'target',
        'starts_on', 'ends_on', 'reward_type', 'reward_value',
        'is_active', 'metadata',
    ];

    protected $casts = [
        'target' => 'integer',
        'reward_value' => 'integer',
        'is_active' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'metadata' => 'array',
    ];

    /** @return HasMany<ProviderQuestProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(ProviderQuestProgress::class);
    }

    /**
     * Cet objectif court-il encore ?
     *
     * L'ABSENCE DE DATES N'EST PAS UNE ABSENCE D'OBJECTIF : une quête sans échéance est un palier de
     * carrière, et elle court indéfiniment. Les traiter comme périmées les ferait disparaître.
     */
    public function estEnCours(?Carbon $moment = null): bool
    {
        $moment ??= Carbon::now();

        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_on !== null && $moment->lessThan($this->starts_on->startOfDay())) {
            return false;
        }

        return $this->ends_on === null || $moment->lessThanOrEqualTo($this->ends_on->endOfDay());
    }
}
