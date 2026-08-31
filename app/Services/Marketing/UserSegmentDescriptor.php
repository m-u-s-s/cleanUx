<?php

namespace App\Services\Marketing;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WeakMap;

/** Les utilisateurs, vus par un arbre de conditions. La configuration garde la main. */
class UserSegmentDescriptor implements EntityDescriptor
{
    /** @var WeakMap<QueryBuilder, array<string, true>>|null alias deja joints, par requete racine — pas par instance */
    protected ?WeakMap $jointuresParRequete = null;

    /** @var array<string, FieldBinding>|null */
    protected ?array $champs = null;

    protected ?string $colonneClientCache = null;

    protected ?string $colonneMontantCache = null;

    public function libelle(): string
    {
        return 'Utilisateur';
    }

    /** @return Builder<Model> */
    public function baseQuery(): Builder
    {
        return $this->modele()->newQuery();
    }

    /** Type de retour large a dessein : le contrat de l'interface n'exige rien de plus. */
    protected function modele(): Model
    {
        return new User;
    }

    /** Memoise : l'evaluateur appelle `fields()` a CHAQUE feuille, jusqu'a 200 fois. */
    public function fields(): array
    {
        if ($this->champs !== null) {
            return $this->champs;
        }

        $liaisons = [];

        foreach ((array) config('marketing.segment_fields', []) as $champ) {
            $liaisons[$champ] = $this->liaison((string) $champ);
        }

        return $this->champs = $liaisons;
    }

    public function operators(): array
    {
        return array_values((array) config('marketing.segment_operators', []));
    }

    protected function liaison(string $champ): FieldBinding
    {
        return match ($champ) {
            // `wrapForDomain` rendait la valeur inchangee : une colonne suffit.
            'email_domain' => FieldBinding::colonne('users.email'),
            'bookings_count' => $this->agregat('b_count_agg', fn () => DB::raw('COUNT(*) AS agg')),
            'last_booking_at' => $this->agregat('b_lastat_agg', fn () => DB::raw('MAX(created_at) AS agg')),
            'total_spent_cents' => $this->agregatDeMontant(),
            default => FieldBinding::colonne('users.'.$champ),
        };
    }

    /** @param  \Closure(): mixed  $selection */
    protected function agregat(string $alias, \Closure $selection): FieldBinding
    {
        return FieldBinding::jointe(function (Builder $racine) use ($alias, $selection): ?string {
            $client = $this->colonneClient();

            if ($client === null) {
                return null;
            }

            $this->joindreUneSeuleFois($racine, $alias, $client, $selection());

            return $alias.'.agg';
        });
    }

    protected function agregatDeMontant(): FieldBinding
    {
        return FieldBinding::jointe(function (Builder $racine): ?string {
            $client = $this->colonneClient();
            $montant = $this->colonneDeMontant();

            if ($client === null || $montant === null) {
                return null;
            }

            // `final_price` est en euros ; `total_spent_cents` promet des centimes.
            $expression = $montant === 'final_price' ? "SUM({$montant}) * 100" : "SUM({$montant})";

            $this->joindreUneSeuleFois($racine, 'b_spent_agg', $client, DB::raw("{$expression} AS agg"));

            return 'b_spent_agg.agg';
        });
    }

    /** L'alias est fixe : deux emplois du meme champ posaient deux jointures identiques.
     *
     * @param  Builder<Model>  $racine
     */
    protected function joindreUneSeuleFois(Builder $racine, string $alias, string $client, mixed $selection): void
    {
        $sousJacente = $racine->getQuery();
        $deja = $this->jointures()[$sousJacente] ?? [];

        if (isset($deja[$alias])) {
            return;
        }

        $sous = DB::table('bookings')
            ->select($selection, DB::raw($client.' AS uid'))
            ->groupBy(DB::raw($client));

        $racine->leftJoinSub($sous, $alias, fn ($jointure) => $jointure->on('users.id', '=', $alias.'.uid'));

        $deja[$alias] = true;
        $this->jointures()[$sousJacente] = $deja;
    }

    /** Cle = la requete Query\Builder sous-jacente, jamais le descripteur : rien n'exige un descripteur par requete.
     *
     * @return WeakMap<QueryBuilder, array<string, true>>
     */
    protected function jointures(): WeakMap
    {
        return $this->jointuresParRequete ??= new WeakMap;
    }

    /** `client_id` est la compatibilite legacy, `customer_user_id` la vraie cle etrangere. */
    protected function colonneClient(): ?string
    {
        return $this->colonneClientCache ??= $this->resoudreColonneClient();
    }

    protected function resoudreColonneClient(): ?string
    {
        $legacy = Schema::hasColumn('bookings', 'client_id');
        $moderne = Schema::hasColumn('bookings', 'customer_user_id');

        return match (true) {
            $legacy && $moderne => 'COALESCE(client_id, customer_user_id)',
            $legacy => 'client_id',
            $moderne => 'customer_user_id',
            default => null,
        };
    }

    protected function colonneDeMontant(): ?string
    {
        return $this->colonneMontantCache ??= $this->resoudreColonneDeMontant();
    }

    protected function resoudreColonneDeMontant(): ?string
    {
        foreach (['final_price', 'payment_amount_cents'] as $colonne) {
            if (Schema::hasColumn('bookings', $colonne)) {
                return $colonne;
            }
        }

        return null;
    }
}
