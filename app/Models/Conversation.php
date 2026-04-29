<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;
    protected $fillable = [
        'rendez_vous_id',
        'mission_id',
        'type',
        'status',
    ];

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function rendezVous()
    {
        return $this->belongsTo(RendezVous::class);
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }
}
