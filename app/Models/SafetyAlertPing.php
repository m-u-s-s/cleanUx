<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE POSITION RELEVÉE PENDANT UNE ALERTE (E33).
 *
 * TABLE SÉPARÉE parce que ces lignes sont NOMBREUSES et que l'alerte, elle, est unique : les empiler
 * dans un JSON sur la ligne d'alerte ferait grossir sans fin une ligne qu'on relit en urgence, et
 * rendrait impossible de retrouver « où était-il à 14 h 12 ».
 *
 * PAS DE `timestamps` : `pinged_at` porte l'heure du RELEVÉ, qui n'est pas celle de l'écriture. Un
 * téléphone hors réseau accumule et envoie plus tard ; confondre les deux placerait toute la trace
 * au moment où la connexion est revenue.
 *
 * @property int $id
 * @property int $safety_alert_id
 * @property float $lat
 * @property float $lng
 * @property Carbon $pinged_at
 */
class SafetyAlertPing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'safety_alert_id',
        'lat',
        'lng',
        'accuracy_m',
        'pinged_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'accuracy_m' => 'integer',
        'pinged_at' => 'datetime',
    ];

    /** @return BelongsTo<SafetyAlert, $this> */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(SafetyAlert::class, 'safety_alert_id');
    }
}
