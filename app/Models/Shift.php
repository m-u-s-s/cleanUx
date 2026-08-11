<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UN CRÉNEAU DE TRAVAIL PLANIFIÉ PAR L'EMPLOYEUR (E19).
 *
 * À ne pas confondre avec les créneaux de disponibilité : ceux-ci sont un concept d'indépendant, qui
 * publie ses horaires sur la place de marché. Un salarié n'en déclare pas — c'est son employeur qui
 * le planifie, et c'est ce que porte cette table.
 *
 * @property int $id
 * @property int $organization_account_id
 * @property int|null $provider_agency_id
 * @property int|null $field_team_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property string $status
 */
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    /** En préparation : ne rend PAS la personne assignable. */
    public const STATUS_PLANNED = 'planned';

    /** Arrêté et communiqué : c'est ce statut qui ouvre l'assignation. */
    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_account_id',
        'provider_agency_id',
        'field_team_id',
        'user_id',
        'starts_at',
        'ends_at',
        'status',
        'recurrence_rule',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ce shift couvre-t-il ce moment ?
     *
     * Bornes INCLUSIVE à gauche, EXCLUSIVE à droite : une mission qui commence à l'heure exacte de
     * fin d'un shift n'est pas couverte, sinon deux shifts consécutifs se chevaucheraient d'une
     * seconde et rendraient la personne « doublement disponible ».
     */
    public function couvre(\Illuminate\Support\Carbon $moment): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $moment->greaterThanOrEqualTo($this->starts_at)
            && $moment->lessThan($this->ends_at);
    }
}
