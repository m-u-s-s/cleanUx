<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogTranslations;
use App\Models\Contracts\TranslatesCatalogLabels;
use App\Services\Audit\Concerns\AuditsEloquentEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Niveau 1 du catalogue : le secteur (Batiment, Nettoyage, Espaces verts...). */
class Sector extends Model implements TranslatesCatalogLabels
{
    use AuditsEloquentEvents, HasCatalogTranslations, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'tagline', 'icon', 'cover_image_path', 'accent_color',
        'sort_order', 'is_active', 'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    /** @return HasMany<Trade, $this> */
    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    /** Metiers reellement proposables : actifs, dans l'ordre voulu par l'administrateur. */
    public function publishedTrades(): HasMany
    {
        return $this->trades()->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    // Même domaine que les questions : « catalog ».
    protected function auditEventDomain(): string
    {
        return 'catalog';
    }
}
