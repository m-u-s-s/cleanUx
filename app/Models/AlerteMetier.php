<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une alerte metier levee, persistee. Jusqu'ici elle n'existait que dans Sentry. */
class AlerteMetier extends Model
{
    protected $table = 'business_alertes';

    public $timestamps = false;

    protected $fillable = [
        'cle', 'niveau', 'message', 'contexte', 'entite_type', 'entite_id', 'levee_le',
    ];

    protected $casts = [
        'contexte' => 'array',
        'entite_id' => 'integer',
        'levee_le' => 'datetime',
    ];
}
