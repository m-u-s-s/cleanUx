<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    use HasFactory;

    protected $fillable = [
        'cle',
        'valeur',
    ];

    public static function getValeur(string $cle, $default = null)
    {
        return static::where('cle', $cle)->value('valeur') ?? $default;
    }

    public static function setValeur(string $cle, $valeur): void
    {
        static::updateOrCreate(
            ['cle' => $cle],
            ['valeur' => $valeur]
        );
    }
}
