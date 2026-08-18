<?php

namespace App\Models;

use Database\Factories\MissionChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionChecklistItem extends Model
{
    /** @use HasFactory<MissionChecklistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'mission_checklist_id',
        'label',
        'item_type',
        'is_required',
        'status',
        'completed_by_user_id',
        'completed_at',
        'notes',
        // F6 — ce qui fait d'une liste un guide : une séquence, une consigne, et la preuve
        // attendue pour les étapes qui la méritent.
        'sort_order',
        'requires_photo',
        'mission_media_id',
        'guidance',
        /*
         * QUI A POSÉ CETTE TÂCHE, ET QUAND LA LISTE S'EST FIGÉE.
         *
         * Hors de cette liste, Eloquent les ÉCARTE EN SILENCE : la tâche du client se créerait
         * avec `source = 'template'`, elle deviendrait indistinguable d'une suggestion, et le
         * client n'aurait plus le droit de retirer ce qu'il vient d'écrire.
         */
        'source',
        'created_by_user_id',
        'locked_at',
    ];

    protected $casts = [
        'requires_photo' => 'boolean',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'completed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    /** @return BelongsTo<MissionChecklist, $this> */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(MissionChecklist::class, 'mission_checklist_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /**
     * QUI A DEMANDÉ CETTE TÂCHE.
     *
     * Distincte de `completedBy()` : l'une dit qui l'a écrite, l'autre qui l'a faite. Le
     * prestataire a besoin de la première pour savoir ce qui se discute avec le client.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
