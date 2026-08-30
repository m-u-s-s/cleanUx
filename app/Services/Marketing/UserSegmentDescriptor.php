<?php

namespace App\Services\Marketing;

use App\Models\User;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Les utilisateurs, vus par un arbre de conditions. La configuration garde la main. */
class UserSegmentDescriptor implements EntityDescriptor
{
    /** @var array<string, true> les alias deja joints sur CETTE requete racine */
    protected array $jointures = [];

    /** @var array<string, FieldBinding>|null */
    protected ?array $champs = null;

    /** @return Builder<Model> */
    public function baseQuery(): Builder
    {
        $this->jointures = [];

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
            'bookings_count' => $this->agregat('b_count_agg', fn ($col) => DB::raw('COUNT(*) AS agg')),
            'last_booking_at' => $this->agregat('b_lastat_agg', fn ($col) => DB::raw('MAX(created_at) AS agg')),
            'total_spent_cents' => $this->agregatDeMontant(),
            default => FieldBinding::colonne('users.'.$champ),
        };
    }

    /** @param  \Closure(string): mixed  $selection */
    protected function agregat(string $alias, \Closure $selection): FieldBinding
    {
        return FieldBinding::jointe(function (Builder $racine) use ($alias, $selection): ?string {
            $client = $this->colonneClient();

            if ($client === null) {
                return null;
            }

            $this->joindreUneSeuleFois($racine, $alias, $client, $selection($client));

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

            $this->joindreUneSeuleFois($racine, 'b_spent_agg', $client, DB::raw("SUM({$montant}) AS agg"));

            return 'b_spent_agg.agg';
        });
    }

    /** L'alias est fixe : deux emplois du meme champ posaient deux jointures identiques.
     *
     * @param  Builder<Model>  $racine
     */
    protected function joindreUneSeuleFois(Builder $racine, string $alias, string $client, mixed $selection): void
    {
        if (isset($this->jointures[$alias])) {
            return;
        }

        $sous = DB::table('bookings')
            ->select($selection, $client.' AS uid')
            ->groupBy($client);

        $racine->leftJoinSub($sous, $alias, fn ($jointure) => $jointure->on('users.id', '=', $alias.'.uid'));

        $this->jointures[$alias] = true;
    }

    protected function colonneClient(): ?string
    {
        foreach (['client_id', 'customer_user_id'] as $colonne) {
            if (Schema::hasColumn('bookings', $colonne)) {
                return $colonne;
            }
        }

        return null;
    }

    protected function colonneDeMontant(): ?string
    {
        foreach (['final_price', 'payment_amount_cents'] as $colonne) {
            if (Schema::hasColumn('bookings', $colonne)) {
                return $colonne;
            }
        }

        return null;
    }
}
