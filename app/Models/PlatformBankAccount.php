<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LE COMPTE QUI REÇOIT LES COMMISSIONS.
 *
 * ON NE MODIFIE JAMAIS UNE LIGNE : on en ajoute une, et l'ancienne se ferme. Un détournement qui
 * pourrait réécrire la ligne en place effacerait sa propre trace ; ici il en laisse deux.
 *
 * Les valeurs sont chiffrées au repos : une copie de la base ne les rend pas.
 */
class PlatformBankAccount extends Model
{
    protected $fillable = [
        'holder_name', 'iban', 'bic', 'bank_name', 'iban_last4',
        'country_code', 'currency', 'note', 'is_active',
        'created_by', 'created_ip', 'closed_at',
    ];

    protected $casts = [
        'holder_name' => 'encrypted',
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'bank_name' => 'encrypted',
        'is_active' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /** L'IBAN N'EST JAMAIS RENDU EN ENTIER par une API ou un export. */
    protected $hidden = ['iban', 'bic', 'holder_name'];

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** CE QUI S'AFFICHE PARTOUT : reconnaître son compte ne demande pas de le lire en entier. */
    public function masque(): string
    {
        return '•••• •••• •••• '.$this->iban_last4;
    }

    public static function quatreDerniers(string $iban): string
    {
        $propre = preg_replace('/[^A-Za-z0-9]/', '', $iban) ?? '';

        return mb_substr($propre, -4);
    }
}
