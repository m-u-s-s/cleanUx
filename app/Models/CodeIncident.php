<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UN INCIDENT DE CODE — une erreur, vue N fois.
 *
 * Le regroupement se fait sur l'EMPREINTE (classe + fichier + ligne), pas sur le message : celui
 * d'une ressource introuvable change à chaque identifiant et grouperait mal.
 */
class CodeIncident extends Model
{
    public const OUVERT = 'ouvert';

    public const CONTENU = 'contenu';

    public const RESOLU = 'resolu';

    public const IGNORE = 'ignore';

    protected $fillable = [
        'fingerprint', 'exception_class', 'message', 'file', 'line',
        'route_name', 'path', 'method', 'famille',
        'occurrences', 'utilisateurs_touches', 'premiere_fois', 'derniere_fois',
        'statut', 'note', 'traite_par', 'traite_le',
    ];

    protected $casts = [
        'line' => 'integer',
        'occurrences' => 'integer',
        'utilisateurs_touches' => 'integer',
        'premiere_fois' => 'datetime',
        'derniere_fois' => 'datetime',
        'traite_le' => 'datetime',
    ];

    /** @return HasMany<CodeIncidentVictim, $this> */
    public function victimes(): HasMany
    {
        return $this->hasMany(CodeIncidentVictim::class);
    }

    /** @return BelongsTo<User, $this> */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOuverts(Builder $query): Builder
    {
        return $query->where('statut', self::OUVERT);
    }

    /** IL SAIGNE ENCORE : vu dans l'heure. Un incident d'il y a trois jours n'appelle pas la même urgence. */
    public function saigneEncore(): bool
    {
        return $this->derniere_fois->gt(now()->subHour());
    }

    public function courtCirconstance(): string
    {
        return basename($this->file).':'.$this->line;
    }
}
