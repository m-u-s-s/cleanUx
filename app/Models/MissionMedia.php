<?php

namespace App\Models;

use Database\Factories\MissionMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionMedia extends Model
{
    /** @use HasFactory<MissionMediaFactory> */
    use HasFactory;

    /** Types de médias, nommés ici pour qu'il n'en existe qu'un vocabulaire. */
    public const TYPE_BEFORE_PHOTO = 'before_photo';

    public const TYPE_AFTER_PHOTO = 'after_photo';

    /** La photo d'un imprévu — un troisième moment, ni avant ni après. */
    public const TYPE_INCIDENT_PHOTO = 'incident_photo';

    /** @return list<string> */
    public static function typesTerrain(): array
    {
        return [self::TYPE_BEFORE_PHOTO, self::TYPE_AFTER_PHOTO, self::TYPE_INCIDENT_PHOTO];
    }

    protected $fillable = [
        'mission_id',
        'uploaded_by_user_id',
        'media_type',
        'path',
        'caption',
        'taken_at',
        'lat',
        'lng',
        'meta',
        // La preuve horodatée : empreinte du fichier, précision de la position, et le droit du
        // client à la voir. Voir la migration `socle_du_kit_sur_place`.
        'sha256',
        'accuracy_m',
        'client_visible',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'meta' => 'array',
        'accuracy_m' => 'float',
        'client_visible' => 'boolean',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
