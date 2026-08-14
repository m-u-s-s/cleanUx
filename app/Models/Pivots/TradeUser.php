<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * LE RATTACHEMENT D'UN PRESTATAIRE À UN MÉTIER.
 *
 * `trade_user` ne dit pas seulement « cette personne exerce ce métier » : elle porte le NIVEAU
 * déclaré, et lequel des métiers est le principal. Ces deux informations départagent les candidats
 * d'une même mission, et n'ont de sens que pour ce couple-là — un « expert » n'est pas un attribut
 * du métier, ni de la personne, mais de leur rencontre.
 *
 * La classe existe pour que ces colonnes soient DÉCLARÉES. Sans elle, l'analyse statique voit un
 * pivot générique et ne peut rien vérifier : une faute de frappe sur `proficiency` rendrait
 * silencieusement `null`, et le classement des candidats se ferait à l'aveugle sans qu'aucun test
 * ne tombe.
 *
 * @property bool $is_primary
 * @property string|null $proficiency
 * @property string|null $notes
 * @property int|null $created_by
 */
class TradeUser extends Pivot
{
    protected $table = 'trade_user';

    protected $casts = [
        'is_primary' => 'boolean',
    ];
}
