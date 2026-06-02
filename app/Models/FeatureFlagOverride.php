<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DB override for a feature flag defined in config/features.php.
 *
 * When a row exists for a given flag_key, its is_enabled value takes
 * precedence over the config file value.  The override_config JSON column
 * mirrors the advanced rollout array format (percentage / users / roles).
 */
class FeatureFlagOverride extends Model
{
    protected $fillable = [
        'flag_key',
        'is_enabled',
        'override_config',
        'reason',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'override_config' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
