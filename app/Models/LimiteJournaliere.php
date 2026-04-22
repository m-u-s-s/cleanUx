<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LimiteJournaliere extends Model
{
    protected $table = 'limites_journalieres';

    protected $fillable = [
        'user_id',
        'date',
        'limite',
        'verrou_admin',
    ];

    protected $casts = [
        'date' => 'date',
        'limite' => 'integer',
        'verrou_admin' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}