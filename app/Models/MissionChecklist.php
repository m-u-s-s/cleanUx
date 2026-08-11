<?php

namespace App\Models;

use Database\Factories\MissionChecklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionChecklist extends Model
{
    /** @use HasFactory<MissionChecklistFactory> */
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'service_catalog_id',
        'template_name',
        'status',
        'completion_rate',
        // La colonne existe depuis la création de la table et ne figurait pas ici : un titre écrit
        // à la création était écarté en silence, et la checklist s'affichait sans nom.
        'title',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<ServiceCatalog, $this> */
    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    /** @return HasMany<MissionChecklistItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MissionChecklistItem::class);
    }
}
