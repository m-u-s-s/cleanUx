<?php

namespace App\Services\Payments;

use App\Models\PlatformSettlementAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stripe\Stripe;
use Stripe\StripeClient;

/** CE QUE BRIO A ENCAISSÉ, ET OÙ CELA PART RÉELLEMENT. */
class PlatformSettlementService
{
    /**
     * La commission encaissée, par devise, en euros.
     *
     * @return array<string, array{montant: float, missions: int}>
     */
    public function commissionEncaissee(): array
    {
        if (! Schema::hasColumn('bookings', 'platform_fee_cents')) {
            return [];
        }

        $lignes = DB::table('bookings')
            ->selectRaw('LOWER(COALESCE(currency, ?)) as devise, SUM(platform_fee_cents) as total, COUNT(*) as nb', ['eur'])
            ->whereNotNull('platform_fee_cents')
            ->where('platform_fee_cents', '>', 0)
            ->groupBy('devise')
            ->get();

        $resultat = [];

        foreach ($lignes as $ligne) {
            $resultat[(string) $ligne->devise] = [
                'montant' => round(((int) $ligne->total) / 100, 2),
                'missions' => (int) $ligne->nb,
            ];
        }

        return $resultat;
    }

    /**
     * Les comptes déclarés, groupés par devise.
     *
     * @return array<string, Collection<int, PlatformSettlementAccount>>
     */
    public function comptesParDevise(): array
    {
        return PlatformSettlementAccount::query()
            ->orderBy('currency')
            ->orderByRaw("CASE WHEN role = 'primary' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get()
            ->groupBy('currency')
            ->all();
    }

    /**
     * LES DEVISES SANS SECOURS VÉRIFIÉ — le seul indicateur qui compte vraiment.
     *
     * @return array<int, string>
     */
    public function devisesSansSecours(): array
    {
        $encaissees = array_keys($this->commissionEncaissee());
        $declarees = array_keys($this->comptesParDevise());
        $devises = array_unique(array_merge($encaissees, $declarees));

        $couvertes = PlatformSettlementAccount::query()
            ->where('role', PlatformSettlementAccount::ROLE_BACKUP)
            ->where('status', PlatformSettlementAccount::STATUS_VERIFIED)
            ->pluck('currency')
            ->map(fn ($d) => strtolower((string) $d))
            ->all();

        $manquantes = array_values(array_diff(
            array_map('strtolower', $devises),
            $couvertes,
        ));

        sort($manquantes);

        return $manquantes;
    }

    /**
     * Les derniers versements réellement effectués par Stripe vers la banque de la plateforme.
     *
     * @return array{disponible: bool, raison?: string, versements?: array<int, array<string, mixed>>}
     */
    public function versementsRecents(int $limite = 5): array
    {
        $cle = config('cashier.secret');

        if (! $cle) {
            return ['disponible' => false, 'raison' => 'Aucune clé Stripe configurée sur cet environnement.'];
        }

        try {
            Stripe::setApiKey($cle);
            $client = new StripeClient($cle);

            $versements = [];

            foreach ($client->payouts->all(['limit' => $limite])->data as $versement) {
                $versements[] = [
                    'id' => $versement->id,
                    'montant' => round(((int) $versement->amount) / 100, 2),
                    'devise' => strtolower((string) $versement->currency),
                    'statut' => (string) $versement->status,
                    'destination' => is_string($versement->destination) ? $versement->destination : null,
                    'arrivee' => $versement->arrival_date
                        ? date('Y-m-d', (int) $versement->arrival_date)
                        : null,
                ];
            }

            return ['disponible' => true, 'versements' => $versements];
        } catch (\Throwable $e) {
            return ['disponible' => false, 'raison' => 'Lecture Stripe impossible : '.$e->getMessage()];
        }
    }

    /** Promeut un compte de secours en compte principal pour sa devise. */
    public function promouvoir(PlatformSettlementAccount $compte): void
    {
        DB::transaction(function () use ($compte) {
            PlatformSettlementAccount::query()
                ->where('currency', $compte->currency)
                ->where('role', PlatformSettlementAccount::ROLE_PRIMARY)
                ->whereKeyNot($compte->getKey())
                ->get()
                ->each(function (PlatformSettlementAccount $ancien) {
                    $ancien->update([
                        'role' => PlatformSettlementAccount::ROLE_BACKUP,
                        'status' => PlatformSettlementAccount::STATUS_RETIRED,
                    ]);
                });

            $compte->update([
                'role' => PlatformSettlementAccount::ROLE_PRIMARY,
                'activated_at' => now(),
            ]);
        });
    }
}
