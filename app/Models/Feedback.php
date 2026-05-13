<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'rendez_vous_id',
        'booking_id',
        'mission_id',
        'client_id',
        'employe_id',
        'note',
        'rating',
        'commentaire',
        'comment',
        'reponse_admin',
        'answered_by',
        'answered_at',
        'status',
        'metadata',
        'feedback',
    ];

    protected $casts = [
        'note' => 'integer',
        'rating' => 'integer',
        'answered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function rendezVous()
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
