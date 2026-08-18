<?php

namespace App\Models;

use Database\Factories\RentalVehicleMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * UNE IMAGE DE VÉHICULE — ET SON TYPE DÉCIDE DE TOUT.
 *
 * Trois natures cohabitent dans la même table parce qu'elles partagent exactement le même cycle de
 * vie : téléversées par l'administrateur, rangées sur le disque public, supprimées avec la voiture.
 * Ce qui les sépare est ce qu'on en fait, et c'est `type` qui le dit.
 *
 *   `gallery`  les photos ordinaires ; celle de position 0 sert de vignette au catalogue
 *   `spin`     la séquence de rotation à 360° — L'ORDRE EST LE SENS DE ROTATION
 *   `model3d`  un fichier glTF/GLB unique, pour les véhicules qui en ont un
 *
 * L'ADMINISTRATEUR CHOISIT VÉHICULE PAR VÉHICULE. Une voiture peut avoir sa rotation photo, une
 * autre son modèle 3D, une troisième aucun des deux — et la fiche s'adapte sans que personne ait à
 * trancher globalement.
 */
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

    /**
     * L'adresse publique du fichier.
     *
     * Les médias de location vivent sur le disque `public` : ce sont des images de catalogue,
     * destinées à être vues par des visiteurs sans compte. Rien d'exécutable n'y entre — le
     * téléversement passe par la même règle partagée que le reste du dépôt.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
