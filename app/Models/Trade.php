<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Trade — corps de métier (Nettoyage, Bâtiment, Peinture, Levage…).
 *
 * Regroupe les ServiceCatalog en familles cohérentes côté UI marketplace
 * et côté matching prestataire (skills_required, certifications…).
 *
 * @property int    $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property bool   $is_active
 * @property int    $sort_order
 */
class Trade extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'code',
        'name',
        'icon',
        'color',
        'cover_image_path',
        'short_description',
        'description',
        'is_active',
        'requires_certification',
        'requires_insurance_proof',
        'is_b2b_default',
        'is_personal_default',
        'sort_order',
        'settings',
        'metadata',
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'requires_certification'   => 'boolean',
        'requires_insurance_proof' => 'boolean',
        'is_b2b_default'           => 'boolean',
        'is_personal_default'      => 'boolean',
        'sort_order'               => 'integer',
        'settings'                 => 'array',
        'metadata'                 => 'array',
    ];

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    public function services(): HasMany
    {
        return $this->hasMany(ServiceCatalog::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function activeServices(): HasMany
    {
        return $this->services()->where('is_active', true);
    }

    // ──────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    // ──────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name ?: $this->code);
    }

    public function getDefaultIconAttribute(): string
    {
        return $this->icon ?: 'briefcase';
    }
}
