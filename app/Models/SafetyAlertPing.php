<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE POSITION RELEVÉE PENDANT UNE ALERTE (E33).
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
