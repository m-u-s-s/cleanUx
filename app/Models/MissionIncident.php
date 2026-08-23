<?php

namespace App\Models;

use Database\Factories\MissionIncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionIncident extends Model
{
    /** @use HasFactory<MissionIncidentFactory> */
    use HasFactory;

    /** Les imprévus qu'un prestataire rencontre en arrivant, nommés une fois pour toutes. */
    public const TYPE_PREEXISTING_DAMAGE = 'preexisting_damage';

    public const TYPE_ACCESS_IMPOSSIBLE = 'access_impossible';

    public const TYPE_MISSING_ITEM = 'missing_item';

    public const TYPE_OTHER = 'other';

    /** @return list<string> */
    public static function typesTerrain(): array
    {
        return [
            self::TYPE_PREEXISTING_DAMAGE,
            self::TYPE_ACCESS_IMPOSSIBLE,
            self::TYPE_MISSING_ITEM,
            self::TYPE_OTHER,
        ];
    }

    public static function libelleType(?string $type): string
    {
        return match ($type) {
            self::TYPE_PREEXISTING_DAMAGE => 'Dégât préexistant',
            self::TYPE_ACCESS_IMPOSSIBLE => 'Accès impossible',
            self::TYPE_MISSING_ITEM => 'Objet ou fourniture manquant',
            default => 'Autre imprévu',
        };
    }

    protected $fillable = [
        'mission_id',
        'reported_by_user_id',
        'resolved_by_user_id',
        'incident_type',
        'severity',
        'status',
        'title',
        'description',
        'resolution_notes',
        'client_visible',
        'reported_at',
        'resolved_at',
        'meta',
        // Ce qui fait d'un signalement autre chose qu'une note : la photo, l'instant où le client
        // l'a su, et le dossier de litige qu'il a ouvert ensuite.
        'mission_media_id',
        'notified_at',
        'complaint_case_id',
    ];

    protected $casts = [
        'client_visible' => 'boolean',
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
        'notified_at' => 'datetime',
        'meta' => 'array',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /** @return BelongsTo<MissionMedia, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MissionMedia::class, 'mission_media_id');
    }

    /** @return BelongsTo<ComplaintCase, $this> */
    public function complaintCase(): BelongsTo
    {
        return $this->belongsTo(ComplaintCase::class, 'complaint_case_id');
    }
}
