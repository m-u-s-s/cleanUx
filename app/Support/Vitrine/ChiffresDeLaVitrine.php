<?php

namespace App\Support\Vitrine;

use App\Models\CancellationPolicyTier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les chiffres que la page d'accueil annonce, LUS DANS LE MOTEUR au lieu d'être recopiés.
 *
 * La vitrine a longtemps promis « 30+ métiers dans 9 pays » alors que le catalogue en ouvrait
 * 19 dans un seul : un chiffre écrit à la main vieillit sans que personne s'en aperçoive.
 */
final class ChiffresDeLaVitrine
{
    /** Une heure : la page est publique et très lue, le catalogue bouge en heures, pas en secondes. */
    private const DUREE_DU_CACHE = 3600;

    /** Nombre de métiers réellement commandables, toutes zones ouvertes confondues. */
    public static function metiersOuverts(): int
    {
        return self::compte('vitrine.metiers', fn () => DB::table('trade_zone_pricing')
            ->where('is_active', true)
            ->distinct()
            ->count('trade_id'));
    }

    /** Métiers dont au moins une zone accepte l'intervention immédiate. */
    public static function metiersEnImmediat(): int
    {
        return self::compte('vitrine.metiers_immediat', fn () => DB::table('trade_zone_pricing')
            ->where('is_active', true)
            ->where('asap_enabled', true)
            ->distinct()
            ->count('trade_id'));
    }

    /** Secteurs portant au moins un métier ouvert. */
    public static function secteursOuverts(): int
    {
        return self::compte('vitrine.secteurs', function () {
            $metiers = DB::table('trade_zone_pricing')->where('is_active', true)->distinct()->pluck('trade_id');

            return DB::table('trades')->whereIn('id', $metiers)->distinct()->count('sector_id');
        });
    }

    /** Zones de service rattachées à un pays ouvert à la réservation. */
    public static function zonesOuvertes(): int
    {
        return self::compte('vitrine.zones', fn () => DB::table('service_zones')
            ->join('countries', 'service_zones.country_id', '=', 'countries.id')
            ->where('countries.booking_enabled', true)
            ->count());
    }

    /** Pays où l'on peut réellement réserver — pas ceux qui existent en base. */
    public static function paysOuverts(): int
    {
        return self::compte('vitrine.pays', fn () => DB::table('countries')
            ->where('booking_enabled', true)
            ->count());
    }

    /** Métiers ouverts qui n'ont pas encore de questionnaire : leur prix ne veut rien dire. */
    public static function metiersSansQuestionnaire(): int
    {
        return self::compte('vitrine.metiers_sans_questionnaire', function () {
            $ouverts = DB::table('trade_zone_pricing')->where('is_active', true)->distinct()->pluck('trade_id');
            $questionnes = DB::table('question_steps')->distinct()->pluck('trade_id');

            // Un trajet se chiffre à la distance : l'absence de questionnaire n'y est pas un trou.
            return DB::table('trades')
                ->whereIn('id', $ouverts)
                ->whereNotIn('id', $questionnes)
                ->whereNotIn('id', DB::table('trade_zone_pricing')
                    ->where('distance_pricing_enabled', true)
                    ->distinct()
                    ->pluck('trade_id'))
                ->count();
        });
    }

    /**
     * Le barème d'annulation du client, tel que le moteur l'appliquera.
     *
     * LA BASE FAIT FOI, PAS LA CONFIGURATION : `config/cancellation.php` ne sert que de repli
     * quand aucune politique n'est enregistrée, et les deux ne disent pas la même chose.
     *
     * @return list<array{heures: ?int, pourcentage: float}>
     */
    public static function baremeDAnnulation(): array
    {
        if (! Schema::hasTable('cancellation_policy_tiers') || ! Schema::hasTable('cancellation_policies')) {
            return self::baremeDeSecours();
        }

        $paliers = CancellationPolicyTier::query()
            ->whereHas('policy', fn ($q) => $q->where('actor_role', 'client')->where('is_active', true))
            ->orderBy('position')
            ->get(['min_hours_before', 'fee_percent']);

        if ($paliers->isEmpty()) {
            return self::baremeDeSecours();
        }

        return $paliers
            ->map(fn ($p) => [
                'heures' => (int) $p->min_hours_before,
                'pourcentage' => (float) $p->fee_percent,
            ])
            ->all();
    }

    /** @return list<array{heures: ?int, pourcentage: float}> */
    private static function baremeDeSecours(): array
    {
        $paliers = [];

        foreach ((array) config('cancellation.client.fee_tiers', []) as $palier) {
            $palier = (array) $palier;
            $paliers[] = [
                'heures' => isset($palier['min_hours_before']) ? (int) $palier['min_hours_before'] : null,
                'pourcentage' => (float) ($palier['fee_percent'] ?? 0),
            ];
        }

        return $paliers;
    }

    /** @param  callable(): int  $mesure */
    private static function compte(string $cle, callable $mesure): int
    {
        if (! Schema::hasTable('trade_zone_pricing')) {
            return 0;
        }

        /** @var int|string $valeur */
        $valeur = cache()->remember($cle, self::DUREE_DU_CACHE, static fn () => (int) $mesure());

        return (int) $valeur;
    }
}
