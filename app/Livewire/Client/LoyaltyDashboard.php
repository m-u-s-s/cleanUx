<?php

namespace App\Livewire\Client;

use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Les deux series passent par `#[Computed]` : Livewire les expose en PROPRIETES, et son
 * cache ne fonctionne que sur cet acces-la — jamais sur l'appel de methode.
 *
 * @property-read array<int, array<string, mixed>> $pointsParMois
 * @property-read array<int, array<string, mixed>> $origineDesPoints
 */
class LoyaltyDashboard extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    /**
     * LES POINTS GAGNÉS ET DÉPENSÉS, MOIS PAR MOIS.
     *
     * L'écran montrait un solde, un palier et une liste. Aucune de ces trois choses ne dit
     * si le client gagne PLUS qu'avant — la seule question qui décide s'il continue.
     *
     * Douze mois : le palier se calcule sur douze mois glissants, une fenêtre plus courte
     * couperait la série au milieu de la période qu'elle est censée expliquer.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function pointsParMois(): array
    {
        $compte = app(LoyaltyService::class)->accountFor(Auth::user());
        // LE PREMIER DU MOIS D'ABORD. Soustraire depuis un 31 deborde sur le mois suivant,
        // et la serie de douze mois glissait d'un cran en perdant le mois courant.
        $debut = now()->copy()->startOfMonth()->subMonths(11);

        /*
         * DEUX DIALECTES, UNE SEULE EXPRESSION CHOISIE AVANT.
         *
         * La suite tourne sur SQLite, l'application sur MySQL : `DATE_FORMAT` n'existe pas
         * d'un côté, `strftime` de l'autre.
         */
        $pilote = DB::connection()->getDriverName();
        $moisSql = $pilote === 'sqlite'
            ? "strftime('%Y-%m', occurred_at)"
            : "DATE_FORMAT(occurred_at, '%Y-%m')";

        $brut = LoyaltyTransaction::query()
            ->where('loyalty_account_id', $compte->id)
            ->where('direction', LoyaltyTransaction::DIRECTION_CREDIT)
            ->where('occurred_at', '>=', $debut)
            ->selectRaw("{$moisSql} as mois, SUM(points) as total")
            ->groupBy('mois')
            ->pluck('total', 'mois');

        $serie = [];

        for ($i = 0; $i < 12; $i++) {
            $mois = $debut->copy()->addMonths($i);
            $cle = $mois->format('Y-m');

            $serie[] = [
                'mois' => $cle,
                'libelle' => $mois->translatedFormat('M'),
                'points' => (int) ($brut[$cle] ?? 0),
            ];
        }

        return $serie;
    }

    /**
     * D'OÙ VIENNENT LES POINTS.
     *
     * C'est la question que l'écran ne répondait pas : le client voyait son solde sans
     * savoir quel geste l'avait fait grandir. Un anneau le dit d'un regard, là où la liste
     * demande de compter à la main sur quinze lignes paginées.
     *
     * Les DÉBITS sont exclus : mélanger ce qu'on gagne et ce qu'on dépense dans une même
     * part rendrait chaque tranche illisible.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function origineDesPoints(): array
    {
        $compte = app(LoyaltyService::class)->accountFor(Auth::user());

        /*
         * `pluck` PLUTOT QUE `get`, et c'est un choix de type.
         *
         * `SUM(points) as total` n'est pas une colonne du modele : hydrater des
         * `LoyaltyTransaction` pour y lire `$ligne->total` cree une propriete que rien ne
         * declare. L'annoter la rendrait credible sans la rendre vraie. Une paire
         * cle/valeur dit exactement ce que la requete rend.
         */
        return LoyaltyTransaction::query()
            ->where('loyalty_account_id', $compte->id)
            ->where('direction', LoyaltyTransaction::DIRECTION_CREDIT)
            ->groupBy('type')
            ->orderByDesc(DB::raw('SUM(points)'))
            ->pluck(DB::raw('SUM(points)'), 'type')
            ->map(fn ($total, string $type): array => [
                'type' => $type,
                'libelle' => self::libelleDuType($type),
                'points' => (int) $total,
            ])
            ->values()
            ->all();
    }

    /**
     * LE VOCABULAIRE DES POINTS — celui du client, pas celui de la base.
     *
     * L'historique affichait `earn_booking` et `redeem` en chasse fixe : des identifiants
     * techniques, montrés tels quels à qui vient voir ses points. Le repli sur la valeur
     * brute est délibéré — un type que le service ajouterait resterait VISIBLE plutôt que
     * de disparaître derrière un libellé vide.
     */
    public static function libelleDuType(string $type): string
    {
        return match ($type) {
            LoyaltyTransaction::TYPE_EARN_BOOKING => __('Réservation'),
            LoyaltyTransaction::TYPE_EARN_REFERRAL => __('Parrainage'),
            LoyaltyTransaction::TYPE_EARN_RATING => __('Avis déposé'),
            LoyaltyTransaction::TYPE_EARN_SIGNUP => __('Bienvenue'),
            LoyaltyTransaction::TYPE_EARN_ANNIVERSARY => __('Anniversaire'),
            LoyaltyTransaction::TYPE_EARN_PROMO => __('Offre promotionnelle'),
            LoyaltyTransaction::TYPE_EARN_ADJUSTMENT => __('Ajustement'),
            LoyaltyTransaction::TYPE_REDEEM => __('Points utilisés'),
            LoyaltyTransaction::TYPE_EXPIRE => __('Points expirés'),
            LoyaltyTransaction::TYPE_PENALTY => __('Pénalité'),
            LoyaltyTransaction::TYPE_ADMIN_ADJUST => __('Correction'),
            default => $type,
        };
    }

    public function render(): View
    {
        $account = app(LoyaltyService::class)->accountFor(Auth::user());

        $currentTier = $account->currentTier;
        $allTiers = LoyaltyTier::query()->active()->ranked()->get();

        $nextTier = $allTiers
            ->filter(fn ($t) => $t->min_period_points > ($currentTier?->min_period_points ?? 0))
            ->sortBy('min_period_points')
            ->first();

        $progressPercent = 0;
        $pointsToNextTier = 0;
        if ($nextTier) {
            $currentMin = $currentTier?->min_period_points ?? 0;
            $delta = max(1, $nextTier->min_period_points - $currentMin);
            $progressPercent = min(100, (int) round((($account->period_points - $currentMin) / $delta) * 100));
            $pointsToNextTier = max(0, $nextTier->min_period_points - $account->period_points);
        }

        $transactions = LoyaltyTransaction::query()
            ->where('loyalty_account_id', $account->id)
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(15);

        return view('livewire.client.loyalty-dashboard', [
            'account' => $account,
            'currentTier' => $currentTier,
            'nextTier' => $nextTier,
            'allTiers' => $allTiers,
            'progressPercent' => $progressPercent,
            'pointsToNextTier' => $pointsToNextTier,
            'transactions' => $transactions,
            'pointsParMois' => $this->pointsParMois,
            'origineDesPoints' => $this->origineDesPoints,
            'totalGagne' => array_sum(array_column($this->origineDesPoints, 'points')),
        ]);
    }
}
