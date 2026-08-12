<?php

namespace App\Services\Payments;

use App\Models\PlatformSettlementAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stripe\Stripe;
use Stripe\StripeClient;

/**
 * CE QUE BRIO A ENCAISSÉ, ET OÙ CELA PART RÉELLEMENT.
 *
 * Le registre local dit ce qu'on CROIT ; Stripe dit ce qui EST. Ce service tient les deux côte à
 * côte, parce qu'un registre qu'on ne confronte jamais au réel finit par décrire une banque dont
 * on est parti depuis six mois — et c'est exactement le genre d'écart qu'on découvre le jour d'un
 * contrôle.
 *
 * Toutes les lectures Stripe sont en échec doux : sans clé d'API, hors ligne, ou en cas de refus,
 * la page doit rester consultable. Un tableau de bord financier qui tombe en panne parce qu'un
 * appel réseau échoue n'est pas consultable au moment où on en a le plus besoin.
 */
class PlatformSettlementService
{
    /**
     * La commission encaissée, par devise, en euros.
     *
     * Lue sur `platform_fee_cents`, la colonne que `completeMission()` écrit à la capture depuis
     * `CommissionService` — donc la même source que le portefeuille prestataire et que le montant
     * réellement prélevé par Stripe. La recalculer ici rouvrirait la divergence que l'unification
     * de 2026-06-11 a fermée.
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
     * @return array<string, \Illuminate\Support\Collection<int, PlatformSettlementAccount>>
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
     * Changer de banque chez Stripe prend deux minutes ; faire vérifier un IBAN ajouté dans
     * l'urgence prend des jours ouvrés. Une devise encaissée sans compte de secours déjà vérifié
     * est donc une devise sur laquelle un changement de banque prendra une semaine, quoi qu'il
     * arrive. C'est ce que cette page doit crier, pas le solde.
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
     * C'est la pièce qui transforme ce registre en attestation : elle montre la destination et la
     * date d'arrivée telles que Stripe les a exécutées, pas telles qu'on les a saisies.
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

    /**
     * Promeut un compte de secours en compte principal pour sa devise.
     *
     * NE DÉPLACE AUCUN ARGENT, et ne prétend pas le faire : c'est l'enregistrement d'une décision,
     * à faire suivre du même changement dans le Dashboard Stripe, qui seul redirige les versements.
     * L'ancien principal est retiré plutôt que supprimé — un registre financier garde ses traces.
     */
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
