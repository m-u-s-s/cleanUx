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

    /**
     * Types de médias, nommés ici pour qu'il n'en existe qu'un vocabulaire.
     *
     * Deux écrivains employaient deux orthographes : le contrôleur terrain posait `before`/`after`,
     * tout le reste lit `before_photo`/`after_photo`. Les photos prises sur place étaient donc
     * écrites dans le vide — invisibles pour le client, absentes du rapport PDF, et comptées à zéro
     * par le score qualité. Rien ne signalait l'écart, chaque moitié étant cohérente avec elle-même.
     */
    public const TYPE_BEFORE_PHOTO = 'before_photo';

    public const TYPE_AFTER_PHOTO = 'after_photo';

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
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'meta' => 'array',
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
