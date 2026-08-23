<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * LE RATTACHEMENT D'UN PRESTATAIRE À UN MÉTIER.
 *
 * @property bool $is_primary
 * @property string|null $proficiency
 * @property string|null $notes
 * @property int|null $created_by
 */
class TradeUser extends Pivot
{
    protected $table = 'trade_user';

    /**
     * Les deux clés sont déclarées, et pas seulement les attributs « métier ».
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'trade_id',
        'is_primary',
        'proficiency',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];
}
