<?php

namespace App\Models;

use Database\Factories\ClientPlaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UN LIEU DU CARNET D'UN CLIENT (E2).
 *
 * CE QUI COMPTE N'EST PAS L'ADRESSE, ce sont les CONSIGNES qui l'accompagnent : l'étage, le
 * digicode, la clé chez la voisine, le chien, l'allergie aux produits chlorés. Ces informations se
 * redonnent oralement à chaque nouveau prestataire — ou se perdent.
 *
 * LES CONSIGNES D'ACCÈS SONT DES CLÉS DE DOMICILE. Elles ne se révèlent au prestataire qu'à
 * l'arrivée confirmée sur place : c'est `MissionAccessSheetService` qui garde cette porte.
 *
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $address
 * @property string|null $postal_code
 * @property float|null $lat
 * @property float|null $lng
 * @property int|null $service_zone_id
 * @property bool $is_default
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $archived_at
 */
class ClientPlace extends Model
{
    /** @use HasFactory<ClientPlaceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'address',
        'city',
        'postal_code',
        'country',
        'lat',
        'lng',
        'service_zone_id',
        'floor',
        'access_instructions',
        'alarm_code_required',
        'access_start_time',
        'access_end_time',
        'preferences',
        'is_default',
        'archived_at',
        'metadata',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'alarm_code_required' => 'boolean',
        'is_default' => 'boolean',
        'preferences' => 'array',
        'metadata' => 'array',
        'archived_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function estArchive(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Les préférences, sous une forme lisible par un humain sur le terrain.
     *
     * RENDUE MÊME VIDE, avec les clés attendues : une fiche dont les champs apparaissent et
     * disparaissent selon ce qui est rempli se lit mal, et fait douter de ce qui manque.
     *
     * @return array<string, mixed>
     */
    public function preferencesLisibles(): array
    {
        $preferences = (array) ($this->preferences ?? []);

        return [
            'products' => $preferences['products'] ?? null,
            'allergies' => $preferences['allergies'] ?? null,
            'pets' => $preferences['pets'] ?? null,
            'notes' => $preferences['notes'] ?? null,
        ];
    }
}
