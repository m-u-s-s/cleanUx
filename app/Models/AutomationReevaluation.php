<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une entree de la file : un evenement a repasser sur une entite. */
class AutomationReevaluation extends Model
{
    protected $table = 'automation_reevaluations';

    public $timestamps = false;

    protected $fillable = ['evenement', 'entite_type', 'entite_id', 'depose_le'];

    protected $casts = [
        'entite_id' => 'integer',
        'depose_le' => 'datetime',
    ];
}
