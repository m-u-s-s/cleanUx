<?php

namespace App\Services\Client\Templates;

use App\Models\RecurringBookingSeries;
use App\Models\RecurringTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
                throw new \DomainException("La date de fin doit être après la date de début.");
            }

            $series = RecurringBookingSeries::create([
                'customer_user_id'         => $user->id,
                'customer_organization_id' => $user->organization_account_id,
                'organization_site_id'     => $params['organization_site_id'] ?? null,
                'frequency'                => $template->frequency,
                'interval'                 => $template->interval,
                'days'                     => $template->days,
                'starts_at'                => $startsAt->toDateString(),
                'ends_at'                  => $endsAt?->toDateString(),
                'occurrence_count'         => $params['occurrence_count'] ?? null,
                'status'                   => RecurringBookingSeries::STATUS_ACTIVE,
                'template_payload'         => $this->buildTemplatePayload($template, $params),
            ]);

            $template->incrementUsage();

            return $series;
        });
    }

    /**
     * Cette payload sera utilisée par le job de génération des bookings
     * pour copier les paramètres du template (service, durée, heure, etc.)
     * sur chaque booking créé.
     */
    protected function buildTemplatePayload(RecurringTemplate $template, array $params): array
    {
        $payload = [
            'template_id'   => $template->id,
            'template_slug' => $template->slug,
            'template_name' => $template->name,
            'service_catalog_id' => $template->default_service_catalog_id,
            'time'          => $params['custom_time'] ?? ($template->default_time?->format('H:i:s') ?? '08:00:00'),
            'duration_minutes' => $template->default_duration_minutes,
        ];

        // Merge avec le payload custom du template (si stocké)
        if ($template->payload) {
            $payload = array_merge($template->payload, $payload);
        }

        return $payload;
    }
}
