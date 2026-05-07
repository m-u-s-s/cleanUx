<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 6.1 — Modèle de template de récurrence pré-défini.
 *
 * Templates système : créés par TemplateSystemSeeder.
 * Templates user : créés par les clients pour réutilisation.
 */
class RecurringTemplate extends Model
{
    use HasFactory;

    public const CATEGORY_OFFICE      = 'office';
    public const CATEGORY_RETAIL      = 'retail';
    public const CATEGORY_HOSPITALITY = 'hospitality';
    public const CATEGORY_RESIDENTIAL = 'residential';
    public const CATEGORY_OTHER       = 'other';

    public const FREQ_DAILY   = 'daily';
    public const FREQ_WEEKLY  = 'weekly';
    public const FREQ_MONTHLY = 'monthly';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'icon',
        'is_system',
        'owner_user_id',
        'owner_organization_id',
        'default_service_catalog_id',
        'frequency',
        'interval',
        'days',
        'default_time',
        'default_duration_minutes',
        'payload',
        'usage_count',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_system'                => 'boolean',
        'is_active'                => 'boolean',
        'days'                     => 'array',
        'payload'                  => 'array',
        'interval'                 => 'integer',
        'default_duration_minutes' => 'integer',
        'usage_count'              => 'integer',
        'display_order'            => 'integer',
    ];

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function ownerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'owner_organization_id');
    }

    public function defaultService(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'default_service_catalog_id');
    }

    // ──────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSystem(Builder $q): Builder
    {
        return $q->where('is_system', true);
    }

    public function scopeForUser(Builder $q, int $userId, ?int $organizationId = null): Builder
    {
        return $q->where(function ($q) use ($userId, $organizationId) {
            $q->where('is_system', true)
              ->orWhere('owner_user_id', $userId);

            if ($organizationId) {
                $q->orWhere('owner_organization_id', $organizationId);
            }
        });
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('display_order')->orderByDesc('usage_count')->orderBy('name');
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Description humaine de la récurrence (réutilise la logique de RecurringBookingSeries).
     */
    public function getHumanDescriptionAttribute(): string
    {
        $interval = $this->interval ?: 1;

        $base = match ($this->frequency) {
            self::FREQ_DAILY   => $interval === 1 ? 'Tous les jours' : "Tous les {$interval} jours",
            self::FREQ_WEEKLY  => $interval === 1 ? 'Toutes les semaines' : "Toutes les {$interval} semaines",
            self::FREQ_MONTHLY => $interval === 1 ? 'Tous les mois' : "Tous les {$interval} mois",
            default            => 'Récurrence personnalisée',
        };

        if ($this->frequency === self::FREQ_WEEKLY && ! empty($this->days)) {
            $dayNames = [
                'monday'    => 'lundi',
                'tuesday'   => 'mardi',
                'wednesday' => 'mercredi',
                'thursday'  => 'jeudi',
                'friday'    => 'vendredi',
                'saturday'  => 'samedi',
                'sunday'    => 'dimanche',
            ];
            $labels = array_map(fn ($d) => $dayNames[strtolower($d)] ?? $d, $this->days);
            $base .= ', ' . implode(' et ', $labels);
        }

        if ($this->default_time) {
            $base .= ' à ' . \Carbon\Carbon::parse($this->default_time)->format('H:i');
        }

        return $base;
    }
}
