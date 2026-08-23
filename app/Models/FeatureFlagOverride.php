<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DB override for a feature flag defined in config/features.php. */
class FeatureFlagOverride extends Model
{
    use HasFactory;

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
