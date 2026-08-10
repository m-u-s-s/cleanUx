<?php

namespace App\Actions\Booking;

use App\Jobs\Missions\GeocodeMissionDestination;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use App\Services\Contracts\ContractBookingHook;
use App\Services\Contracts\ContractSlaService;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\Enterprise\EnterpriseBookingApprovalService;
use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * CreateBookingFromApiAction
 *
 * Encapsulates the simplified mobile-API booking creation flow.
 * Extracted from ClientBookingController::store() to keep the controller thin.
 *
 * @see App\Http\Controllers\Api\Client\ClientBookingController
 */
final class CreateBookingFromApiAction
{
    /**
     * Execute the booking creation.
     *
     * @param  User  $user  Authenticated client
     * @param  array<string, mixed>  $data  Validated request data
     * @return Booking The freshly created (and re-fetched) booking
     */
    public function execute(User $user, array $data): Booking
    {
        $now = now();
        $isAsap = ($data['booking_mode'] ?? 'scheduled') === 'asap';

        // SP4 — hook contrat unifié (no-op sans contrat). $user est le client.
        // Pose organization_contract_id / assigned_provider_organization_id /
        // devis_estime négocié / cost_center / entreprise_approval_required selon
        // le contrat-cadre applicable. Lève ContractPolicyException sur violation
        // dure (PO manquant, service hors contrat) — remontée à l'appelant API.
        $date = (string) ($data['date'] ?? $data['scheduled_date'] ?? now()->toDateString());
        $data = app(ContractBookingHook::class)->apply($user, $data, $date);

        // cost_center / purchase_order_number n'ont pas de colonne sur bookings :
        // on les conserve dans metadata (idem contexte corporate du chemin web).
        $contractMetadata = array_filter([
            'cost_center' => Arr::get($data, 'cost_center'),
            'purchase_order_number' => Arr::get($data, 'purchase_order_number'),
            'contract_price_label' => Arr::get($data, 'contract_price_label'),
        ], fn ($v) => $v !== null && $v !== '');

        $booking = Booking::create([
            'booking_reference' => $this->generateReference(),
            'customer_user_id' => $user->id,
            'client_id' => $user->id,
            'customer_organization_id' => $user->organization_account_id ?? $user->current_organization_id ?? null,
            'service_catalog_id' => $data['service_catalog_id'],
            'address' => $data['address'],
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'] ?? 'BE',
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'].':00',
            'booking_mode' => $isAsap ? 'asap' : 'scheduled',
            'status' => $isAsap ? 'confirme' : 'en_attente',
            'priority' => $data['priority'] ?? ($isAsap ? 'urgent' : 'normal'),
            'surface_m2' => $data['surface_m2'] ?? null,
            'customer_comment' => $data['customer_comment'] ?? null,
            'contact_name' => $data['contact_name'] ?? $user->name,
            'contact_phone' => $data['contact_phone'] ?? ($user->phone ?? null),
            'destination_lat' => $data['destination_lat'] ?? null,
            'destination_lng' => $data['destination_lng'] ?? null,
            'currency' => $user->preferred_currency ?? 'EUR',
            'created_by' => $user->id,
            'asap_requested_at' => $isAsap ? $now : null,
            'asap_deadline_at' => $isAsap ? $now->copy()->addHours(2) : null,
            // Trade-specific dynamic form answers (Phase multi-trade booking flow)
            'trade_form_answers' => ! empty($data['trade_form_answers']) ? $data['trade_form_answers'] : null,
            // SP2 client selection (parité web) : type prestataire souhaité + presta
            // nominatif. Le gating premium a déjà été appliqué par le contrôleur via
            // ProviderSelectionResolver. Le matching (AiDispatchService) honore le type.
            'provider_type_preference' => $data['provider_type_preference'] ?? 'any',
            'preferred_provider_user_id' => $data['preferred_provider_user_id'] ?? null,
            // SP3 Task 6 : société choisie (premium-gated + éligibilité validée par le contrôleur).
            'assigned_provider_organization_id' => Arr::get($data, 'assigned_provider_organization_id'),
            // SP4 Task 6 : lien contrat-cadre + tarif négocié posés par le hook contrat.
            'organization_contract_id' => Arr::get($data, 'organization_contract_id'),
            'devis_estime' => Arr::get($data, 'devis_estime'),
            'metadata' => ! empty($contractMetadata) ? $contractMetadata : null,
        ]);

        if ($isAsap) {
            $this->maybeDispatchAsap($booking, $now);
        }

        // SP4 — si le contrat exige une approbation manuelle (entreprise_approval_required
        // posé par le hook), route vers le workflow d'approbation comme le fait
        // CreateBookingAction. Soft : ne casse jamais la création.
        if (Arr::get($data, 'entreprise_approval_required', false)) {
            try {
                app(EnterpriseBookingApprovalService::class)
                    ->createForBooking($booking, $user, Arr::get($data, 'site_instructions'));
            } catch (\Throwable $e) {
                \Log::warning('Enterprise approval creation failed (API booking)', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $booking->fresh();
    }

    /**
     * Create a planned Mission and attempt auto-dispatch for ASAP bookings.
     */
    private function maybeDispatchAsap(Booking $booking, Carbon $now): void
    {
        if (! class_exists(Mission::class)) {
            return;
        }

        // Une réservation ASAP naît en `confirme`, ce qui réveille RendezVousObserver : celui-ci a
        // DÉJÀ créé la mission. En créer une seconde ici produisait deux missions pour une même
        // réservation — dont une seule partait en offre —, avec risque de double assignation et de
        // comptage faussé. On reprend donc l'existante, et on ne crée que si aucune n'existe (cas
        // d'un booking enregistré sans événements).
        $mission = $booking->resolveMission();

        if (! $mission) {
            $mission = Mission::create([
                'booking_id' => $booking->id,
                'status' => 'planned',
                'planned_start_at' => $now->copy()->addMinutes(30),
                // SP1 Task 5 : recopie les champs prestataire/org du booking (souvent
                // null à la création — complétés ensuite par le dispatch).
                'lead_provider_user_id' => $booking->assigned_provider_user_id,
                'lead_employee_id' => $booking->assigned_provider_user_id,
                'provider_organization_id' => $booking->assigned_provider_organization_id,
                'provider_team_id' => $booking->provider_team_id,
                'organization_account_id' => $booking->customer_organization_id,
            ]);
        }

        // SP4 — propage le contrat du booking à la mission + arme le SLA (soft).
        if ($booking->organization_contract_id) {
            $mission->forceFill(['organization_contract_id' => $booking->organization_contract_id])->save();
            app(ContractSlaService::class)->armForMission($mission);
        }

        // Sans destination, l'offre arrive sans marqueur sur la carte du prestataire : ce chemin
        // de création ne renseignait aucune coordonnée, contrairement au chemin rendez_vous_id.
        // Différé car le géocodage est un appel HTTP tiers — voir GeocodeMissionDestination.
        GeocodeMissionDestination::dispatch($mission->id);

        // ::class et non une chaîne préfixée d'un antislash : '\App\...' n'est pas la même clé de
        // conteneur que 'App\...', si bien que toute liaison enregistrée sur la classe — un
        // décorateur, un double de test — était silencieusement contournée.
        if (! class_exists(MissionDispatchService::class)) {
            return;
        }

        try {
            app(MissionDispatchService::class)->dispatchToNextProvider($mission);
        } catch (\Throwable $e) {
            \Log::warning('Auto-dispatch failed', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateReference(): string
    {
        do {
            $ref = 'CUX-'.strtoupper(bin2hex(random_bytes(3)));
        } while (Booking::where('booking_reference', $ref)->exists());

        return $ref;
    }
}
