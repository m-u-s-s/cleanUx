<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'rendez_vous_id',
        'client_id',
        'commentaire',
        'note',
        'reponse_admin',
        'feedback',
    ];

    protected $casts = [
        'note' => 'integer',
    ];

    public function rendezVous()
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}