<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringBookingSeries extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'recurring_booking_series';

    // L3 — explicit allowlist instead of $guarded = []. Covers every column the recurring
    // template service assigns (and the legacy cast columns) so creation keeps working, while
    // id + timestamps stay non-mass-assignable.
    /**
     * LES NOMS DE CE TABLEAU SONT CEUX DE LA TABLE, ET RIEN D'AUTRE.
     *
     * Quatre entrées désignaient des colonnes inexistantes — `days_of_week`, `start_date`,
     * `end_date`, `settings` — présentes ici ET dans `$casts`. La table porte `days`,
     * `starts_at`, `ends_at` et `metadata`. Personne ne les employait, mais un
     * `create(['start_date' => ...])` écrit demain aurait échoué sur « Unknown column » : une
     * entrée de `$fillable` autorise l'attribut, elle ne crée pas la colonne.
     */
    protected $fillable = [
        'customer_user_id',
        'customer_organization_id',
        'organization_site_id',
        'service_catalog_id',
        'service_zone_id',
        'frequency',
        'interval',
        'days',
        'starts_at',
        'ends_at',
        'occurrence_count',
        'status',
        'timezone',
        'next_occurrence_at',
        'last_generated_at',
        'metadata',
        'template_payload',
    ];

    protected $casts = [
        'metadata' => 'array',
        'days' => 'array',
        'template_payload' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];
}
