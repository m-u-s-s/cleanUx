<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogTranslations;
use App\Models\Contracts\TranslatesCatalogLabels;
use App\Services\Audit\Concerns\AuditsEloquentEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Niveau 1 du catalogue : le secteur (Batiment, Nettoyage, Espaces verts...).
 *
 * Une carte du carrousel d'accueil. `accent_color` est le seul endroit du produit ou la couleur
 * est saturee : la carte active s'en teinte, tout le reste vit en neutres.
 *
 * ── POURQUOI LE SECTEUR SE TRADUIT ───────────────────────────────────────────────────────────
 *
 * C'est la PREMIÈRE chose qu'un client voit en commandant. Le questionnaire qui suit était
 * traduisible depuis longtemps — `Question`, `QuestionStep`, `QuestionOption` portent ce trait —
 * mais le carrousel qui y mène ne l'était pas : un client néerlandophone choisissait son secteur
 * en français, puis répondait à des questions en néerlandais. La chaîne se traduisait par le
 * milieu.
 *
 * Les champs traduisibles sont `name` et `tagline`, et la liste est FERMÉE côté écran
 * d'administration : voir `CatalogCenter::CHAMPS_TRADUISIBLES`.
 */
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

    /*
     * Même domaine que les questions : « catalog ».
     *
     * Les trois niveaux se lisent alors dans un seul flux d'audit. Archiver un secteur retire tout
     * un pan du carrousel d'un geste ; répondre à « pourquoi Espaces verts a disparu jeudi »
     * demande de retrouver ce geste-là, pas de deviner.
     */
    protected function auditEventDomain(): string
    {
        return 'catalog';
    }
}
