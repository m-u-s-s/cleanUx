<?php

namespace App\Services\Availability;

use App\Models\AvailabilitySlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * LA DISPONIBILITÉ QU'ON A EN SORTANT DE L'INSCRIPTION.
 *
 * Un prestataire sans le moindre créneau est INVISIBLE à la planification : `AvailabilityService`
 * ne lui trouve aucune fenêtre, `MatchingScorer` le note en conséquence, et il attend des missions
 * qui ne viendront jamais sans qu'aucun écran ne lui dise pourquoi. Le sortir de l'inscription
 * fermé par défaut, c'est le sortir cassé.
 *
 * SEPT JOURS, 08:00–17:00. Le défaut d'une place de marché doit être « ouvert » : une intervention
 * immédiate ne connaît pas les jours ouvrables, et le samedi est un volume réel. Fermer se fait en
 * deux gestes depuis l'application ; découvrir après trois semaines qu'on n'était joignable nulle
 * part ne se rattrape pas.
 *
 * UN SEUL ÉCRIVAIN, APPELÉ PARTOUT — même raison que `ProviderCoverageWriter` : l'inscription web
 * indépendante, l'inscription web société et l'inscription mobile passent toutes ici. Trois
 * écritures séparées finiraient par diverger, et une écriture manquante quelque part se traduit
 * par un prestataire muet, sans erreur nulle part.
 */
class DefaultAvailabilityProvisioner
{
    public const DEFAULT_START = '08:00:00';

    public const DEFAULT_END = '17:00:00';

    /**
     * Les sept jours, dans la convention de `AvailabilitySlot` : 0 = dimanche … 6 = samedi.
     * On énumère du lundi au dimanche pour que l'ordre de création se lise comme une semaine.
     *
     * @var list<int>
     */
    public const DEFAULT_WEEKDAYS = [
        AvailabilitySlot::WEEKDAY_MONDAY,
        AvailabilitySlot::WEEKDAY_TUESDAY,
        AvailabilitySlot::WEEKDAY_WEDNESDAY,
        AvailabilitySlot::WEEKDAY_THURSDAY,
        AvailabilitySlot::WEEKDAY_FRIDAY,
        AvailabilitySlot::WEEKDAY_SATURDAY,
        AvailabilitySlot::WEEKDAY_SUNDAY,
    ];

    /**
     * Dote un prestataire de ses créneaux par défaut.
     *
     * IDEMPOTENT, ET LE TEST PORTE SUR « A-T-IL DÉJÀ CHOISI », pas sur « ce jour-ci existe-t-il ».
     * Un prestataire qui a délibérément fermé son dimanche ne doit pas le voir réapparaître au
     * prochain passage : n'importe quel créneau existant, actif ou non, vaut décision prise.
     *
     * @return int le nombre de créneaux créés — 0 si le prestataire avait déjà une semaine à lui
     */
    public function provision(User $provider): int
    {
        if (! $provider->isEmploye()) {
            return 0;
        }

        return DB::transaction(function () use ($provider): int {
            /*
             * Verrou de lecture sur les créneaux existants : deux inscriptions rejouées en
             * parallèle — un double clic, un webhook réémis — verraient toutes deux « aucun
             * créneau » et en créeraient quatorze.
             */
            $dejaChoisi = AvailabilitySlot::query()
                ->where('provider_user_id', $provider->id)
                ->lockForUpdate()
                ->exists();

            if ($dejaChoisi) {
                return 0;
            }

            $fuseau = (string) config('availability.default_timezone', config('app.timezone', 'Europe/Brussels'));
            $maintenant = now();

            $lignes = [];

            foreach (self::DEFAULT_WEEKDAYS as $jour) {
                $lignes[] = [
                    'provider_user_id' => $provider->id,
                    'weekday' => $jour,
                    'start_time' => self::DEFAULT_START,
                    'end_time' => self::DEFAULT_END,
                    'valid_from' => null,
                    'valid_until' => null,
                    'timezone' => $fuseau,
                    'is_active' => true,
                    // Tracé : distinguer plus tard un horaire choisi d'un horaire hérité du défaut.
                    'metadata' => json_encode(['source' => 'default_on_registration']),
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];
            }

            AvailabilitySlot::query()->insert($lignes);

            return count($lignes);
        });
    }
}
