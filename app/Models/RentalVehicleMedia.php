<?php

namespace App\Models;

use Database\Factories\RentalVehicleMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** UNE IMAGE DE VÉHICULE — ET SON TYPE DÉCIDE DE TOUT. */
class RentalVehicleMedia extends Model
{
    /** @use HasFactory<RentalVehicleMediaFactory> */
    use HasFactory;

    protected $table = 'rental_vehicle_media';

    public const TYPE_GALERIE = 'gallery';

    public const TYPE_ROTATION = 'spin';

    public const TYPE_MODELE_3D = 'model3d';

    /** @var list<string> */
    public const TYPES = [self::TYPE_GALERIE, self::TYPE_ROTATION, self::TYPE_MODELE_3D];

    protected $fillable = [
        'rental_vehicle_id', 'type', 'path', 'position', 'alt', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'position' => 'integer',
    ];

    /** @return BelongsTo<RentalVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 'rental_vehicle_id');
    }

    /** L'adresse publique du fichier. */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
