<?php

namespace App\Services\Commission;

use App\Models\CommissionRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * QUEL TAUX S'APPLIQUE ICI — la porte unique.
 *
 * Cinq taux vivaient en dur dans `config/`. Ils restent le REPLI : tant qu'aucune règle ne
 * couvre un cas, le comportement d'avant tient exactement, à la virgule près. C'est ce qui
 * permet de poser ce socle sans rien casser.
 *
 * LA PLUS PRÉCISE GAGNE. Une règle qui pose quatre conditions bat celle qui en pose trois ;
 * à précision égale, la priorité tranche ; à priorité égale, la plus récente. Sans cet ordre,
 * poser un taux de zone effacerait par accident un taux de métier.
 *
 * LA LECTURE EST MISE EN CACHE. Le partage de l'argent passe ici sur CHAQUE devis, chaque
 * supplément, chaque règlement horaire : relire la table à chaque fois ferait une requête de
 * plus sur le chemin le plus chaud de la plateforme. Toute écriture vide le cache.
 */
class ResolveurDeCommission
{
    private const CLE_CACHE = 'commission:regles:actives';

    private const TTL_SECONDES = 300;

    /**
     * LA TABLE EXISTE-T-ELLE ? Memorise pour la requete.
     *
     * `hasTable` n'est PAS memoise par Laravel : l'appeler a chaque devis ajouterait une
     * requete sur le chemin le plus chaud de la plateforme.
     */
    private static ?bool $tablePresente = null;

    public function pour(ContexteDeCommission $contexte): TauxDeCommission
    {
        $regle = $this->meilleureRegle($contexte);

        if ($regle !== null) {
            return new TauxDeCommission(
                taux: $regle->taux(),
                planchercents: $regle->min_cents ?? $this->plancherParDefaut($contexte->module),
                regle: $regle,
                origine: $regle->label,
            );
        }

        // LE REPLI, ET IL EST EXACTEMENT CE QUI EXISTAIT AVANT.
        return new TauxDeCommission(
            taux: $this->tauxDeRepli($contexte),
            planchercents: $this->plancherParDefaut($contexte->module),
            regle: null,
            origine: 'taux par défaut de la plateforme',
        );
    }

    /** LES RÈGLES QUI COUVRENT CE CAS, la meilleure en tête. */
    public function meilleureRegle(ContexteDeCommission $contexte): ?CommissionRule
    {
        return $this->reglesApplicables($contexte)->first();
    }

    /**
     * TOUTES CELLES QUI COUVRENT LE CAS, ordonnées.
     *
     * L'écran de réglage s'en sert pour montrer laquelle gagne ET celles qu'elle masque : une
     * règle qui ne s'applique jamais parce qu'une plus précise la couvre est un piège classique.
     *
     * @return Collection<int, CommissionRule>
     */
    public function reglesApplicables(ContexteDeCommission $contexte): Collection
    {
        $date = $contexte->date ?? now();

        return $this->regles()
            ->filter(fn (CommissionRule $r): bool => $this->sApplique($r, $contexte, $date))
            ->sortByDesc(fn (CommissionRule $r): array => [$r->precision(), $r->priority, $r->id])
            ->values();
    }

    /** @return Collection<int, CommissionRule> */
    public function regles(): Collection
    {
        // ENTRE LA MISE EN LIGNE DU CODE ET LA MIGRATION, LA TABLE N'EXISTE PAS ENCORE.
        // Sans cette garde, CHAQUE devis leverait une exception pendant ce laps de temps —
        // et le chemin de l'argent est le dernier endroit ou l'on veut decouvrir ca.
        // Le repli est exactement le comportement d'avant : aucune regle, taux d'origine.
        if (! $this->laTableExiste()) {
            return collect();
        }

        /** @var Collection<int, CommissionRule> $regles */
        $regles = Cache::remember(
            self::CLE_CACHE,
            self::TTL_SECONDES,
            fn () => CommissionRule::query()->actives()->with(['trade', 'serviceZone'])->get(),
        );

        return $regles;
    }

    /** TOUTE ÉCRITURE VIDE LE CACHE : un taux réglé doit s'appliquer au devis suivant. */
    public function oublierLeCache(): void
    {
        Cache::forget(self::CLE_CACHE);
        self::$tablePresente = null;
    }

    private function laTableExiste(): bool
    {
        return self::$tablePresente ??= Schema::hasTable('commission_rules');
    }

    private function sApplique(CommissionRule $regle, ContexteDeCommission $contexte, CarbonInterface $date): bool
    {
        if (! $regle->couvre($date)) {
            return false;
        }

        // UN DISCRIMINANT NUL NE FILTRE RIEN : la règle générale couvre tout le monde.
        if ($regle->module !== null && $regle->module !== $contexte->module) {
            return false;
        }

        if ($regle->asset_type !== null && $regle->asset_type !== $contexte->typeDeBien) {
            return false;
        }

        if ($regle->trade_id !== null && $regle->trade_id !== $contexte->tradeId) {
            return false;
        }

        if ($regle->service_zone_id !== null && $regle->service_zone_id !== $contexte->zoneId) {
            return false;
        }

        // LA DURÉE EST UN SEUIL, PAS UNE ÉGALITÉ : « après deux semaines » couvre le vingtième
        // jour comme le quatorzième.
        if ($regle->min_duration_days !== null) {
            return $contexte->dureeJours !== null && $contexte->dureeJours >= $regle->min_duration_days;
        }

        return true;
    }

    /**
     * LE COMPORTEMENT D'AVANT, INTACT.
     *
     * Chaque module gardait son taux en dur. Tant qu'aucune règle ne le couvre, il le garde —
     * sinon poser ce socle changerait silencieusement le prix de tout le monde.
     */
    private function tauxDeRepli(ContexteDeCommission $contexte): float
    {
        return match ($contexte->module) {
            CommissionRule::MODULE_LOCATION_MEMBRES => $this->tauxLocationEntreMembres($contexte->typeDeBien),
            CommissionRule::MODULE_POURBOIRE => ((int) config('tips.platform_fee_percent', 0)) / 100,
            default => ((int) config('brio.platform_fee_percent', 15)) / 100,
        };
    }

    private function tauxLocationEntreMembres(?string $typeDeBien): float
    {
        $general = (int) config('peer_rental.commission_percent', 25);

        $propre = $typeDeBien === null
            ? null
            : config('peer_rental.commission_percent_par_type.'.$typeDeBien);

        return ($propre === null || $propre === '' ? $general : (int) $propre) / 100;
    }

    /**
     * LE PLANCHER PAR MODULE.
     *
     * Il ne s'applique qu'aux prestations : la location entre membres ne l'a jamais eu, et le lui
     * imposer prélèverait 2 € sur une commission calculée à zéro.
     */
    private function plancherParDefaut(string $module): int
    {
        return $module === CommissionRule::MODULE_PRESTATION
            ? max(0, (int) config('brio.minimum_commission_cents', 200))
            : 0;
    }
}
