<?php

namespace App\Services\Client\Templates;

use App\Models\RecurringBookingSeries;
use App\Models\RecurringTemplate;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6.1 — Application d'un template pour créer une série en 1 clic.
 *
 * Workflow :
 *   1. User clique "Utiliser ce template" sur la galerie
 *   2. Form de confirmation : site cible, date de démarrage, fin éventuelle
 *   3. Cette classe crée la RecurringBookingSeries (sans encore générer
 *      les bookings — c'est ton CreateRecurringSeriesAction existant qui
 *      s'en charge ensuite, ou un job nocturne)
 *   4. Incrémente le usage_count du template (popularité)
 */
class ApplyRecurringTemplateService
{
    /**
     * @param array{
     *   organization_site_id?:int,
     *   starts_at:string,
     *   ends_at?:string,
     *   occurrence_count?:int,
     *   custom_time?:string,
     * } $params
     */
    public function apply(User $user, RecurringTemplate $template, array $params): RecurringBookingSeries
    {
        if (! $template->is_active) {
            throw new \DomainException("Ce template n'est plus disponible.");
        }

        return DB::transaction(function () use ($user, $template, $params) {
            $startsAt = Carbon::parse($params['starts_at']);

            $endsAt = isset($params['ends_at']) ? Carbon::parse($params['ends_at']) : null;
            if ($endsAt && $endsAt->lessThanOrEqualTo($startsAt)) {
                throw new \DomainException('La date de fin doit être après la date de début.');
            }

            $payload = $this->buildTemplatePayload($template, $params);

            $data = [
                'customer_user_id' => $user->id,
                'customer_organization_id' => $user->organization_account_id,
                'organization_site_id' => $params['organization_site_id'] ?? null,
                'frequency' => $template->frequency,
                'interval' => $template->interval ?? 1,
                'days' => $template->days,
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt?->toDateString(),
                'occurrence_count' => $params['occurrence_count'] ?? null,
                'status' => defined(RecurringBookingSeries::class.'::STATUS_ACTIVE')
                    ? RecurringBookingSeries::STATUS_ACTIVE
                    : 'active',
            ];

            if (Schema::hasColumn('recurring_booking_series', 'template_payload')) {
                $data['template_payload'] = $payload;
            } elseif (Schema::hasColumn('recurring_booking_series', 'metadata')) {
                $data['metadata'] = [
                    'template_payload' => $payload,
                ];
            }

            /*
             * LA PREMIÈRE ÉCHÉANCE EST POSÉE ICI, ET PAR PERSONNE D'AUTRE.
             *
             * `ProcessRecurringBookings` filtre sur `whereNotNull('next_occurrence_at')` et ne fait
             * qu'AVANCER cette date une fois la série entamée. Aucun chemin ne l'initialisait :
             * la colonne est `nullable` et sans valeur par défaut, si bien que TOUTE série créée
             * était invisible à la commande. Aucune réservation récurrente n'a jamais été générée —
             * la fonctionnalité existait de bout en bout, et ne produisait rien.
             *
             * L'HEURE VIENT DU PAYLOAD, pas d'un défaut réinventé. C'est `buildTemplatePayload()`
             * qui arbitre déjà entre l'heure demandée par le client et celle du modèle ; en
             * recalculer une seconde ici ferait deux vérités, et la réservation générée tomberait à
             * une heure que le client n'a pas choisie. La commande relit d'ailleurs les deux moitiés
             * de cette date — `toDateString()` et `format('H:i')` — pour dater la réservation.
             */
            $data['next_occurrence_at'] = $startsAt
                ->copy()
                ->setTimeFromTimeString($this->normalizeTemplateTime($payload['time'] ?? null))
                ->toDateTimeString();

            $series = RecurringBookingSeries::create($data);

            $template->incrementUsage();

            return $series;
        });
    }

    /**
     * Cette payload sera utilisée par le job de génération des bookings
     * pour copier les paramètres du template (service, durée, heure, etc.)
     * sur chaque booking créé.
     */
    private function normalizeTemplateTime(mixed $time): string
    {
        if ($time instanceof CarbonInterface) {
            return $time->format('H:i:s');
        }

        if (is_string($time) && trim($time) !== '') {
            $time = trim($time);

            if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                return $time.':00';
            }

            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                return $time;
            }
        }

        return '08:00:00';
    }

    protected function buildTemplatePayload(RecurringTemplate $template, array $params): array
    {
        $payload = [
            'template_id' => $template->id,
            'template_slug' => $template->slug,
            'template_name' => $template->name,
            'service_catalog_id' => $template->default_service_catalog_id,
            'time' => $params['custom_time'] ?? $this->normalizeTemplateTime($template->default_time ?? null),
            'duration_minutes' => $template->default_duration_minutes,
        ];

        // Merge avec le payload custom du template (si stocké)
        if ($template->payload) {
            $payload = array_merge($template->payload, $payload);
        }

        return $payload;
    }
}
