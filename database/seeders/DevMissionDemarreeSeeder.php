<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\Missions\MissionLifecycleService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** FIXTURE DE DÉVELOPPEMENT : une mission DÉJÀ DÉMARRÉE, chez un prestataire précis. */
class DevMissionDemarreeSeeder extends Seeder
{
    public function run(): void
    {
        $provider = $this->resoudreLePrestataire();
        $client = $this->resoudreLeClient();

        if (! $provider || ! $client) {
            return;
        }

        [$trade, $zoneId, $prix] = $this->resoudreLeService();

        if (! $trade) {
            $this->command?->error('Aucun métier ouvert : il faut au moins une ligne trade_zone_pricing active.');

            return;
        }

        $booking = $this->creerLaReservation($client, $trade, $zoneId, $prix);

        // La confirmation, et elle seule, fait naître la mission : l'observateur de réservation
        // appelle la synchronisation. On ne crée donc PAS la mission ici — deux fabriques
        // donneraient deux missions pour une seule réservation.
        $booking->update(['status' => BookingStatus::CONFIRME]);

        $mission = Mission::query()->where('booking_id', $booking->id)->firstOrFail();

        $dispatch = app(MissionDispatchService::class);
        $lifecycle = app(MissionLifecycleService::class);

        // L'offre puis l'acceptation : c'est `accept()` qui pose `lead_provider_user_id`, la clé
        // que lit `/api/provider/missions/active`. Sans elle, la mission existe et le prestataire
        // ne la voit nulle part.
        $assignment = $dispatch->createOffer($mission, $provider);
        $dispatch->accept($assignment);

        $mission->refresh();

        $lifecycle->setEnRoute($mission, $provider);
        $mission->refresh();

        // Les coordonnées passées ici sont celles du PRESTATAIRE à l'arrivée (start_lat/lng), pas
        // celles du client : on le pose sur le pas de la porte, à quelques mètres de la destination.
        $lifecycle->setArrived($mission, $provider, 50.8465, 4.3527);
        $mission->refresh();

        $code = $lifecycle->generateStartCode($mission);
        $lifecycle->validateStartCode($mission, $provider, $code['code'], 50.8465, 4.3527);

        $mission->refresh();

        // LE GÉOCODAGE PEUT NE PAS RÉPONDRE — il interroge Nominatim, hors du poste de travail.
        // La réservation porte déjà le point A ; la mission naissait alors sans destination et la
        // carte du prestataire n'avait rien à afficher. On reporte, sans réinventer.
        if ($mission->destination_lat === null && $booking->destination_lat !== null) {
            $mission->forceFill([
                'destination_lat' => $booking->destination_lat,
                'destination_lng' => $booking->destination_lng,
            ])->save();
        }

        $this->rapport($mission->refresh(), $booking->refresh(), $provider, $client, $trade);
    }

    protected function resoudreLePrestataire(): ?User
    {
        $email = env('PROVIDER_EMAIL', 'bsanchez@example.org');
        $provider = User::query()->where('email', $email)->first();

        if (! $provider) {
            $this->command?->error("Prestataire introuvable : {$email}");

            return null;
        }

        // Les deux portes que `createOffer()` puis `accept()` referment. Les vérifier ICI donne au
        // développeur la cause exacte, plutôt qu'une DomainException au milieu du parcours.
        if ($provider->providerProfile === null) {
            $this->command?->error("{$email} n'a pas de profil prestataire : aucune offre ne peut lui être faite.");

            return null;
        }

        if (! $provider->hasClearedKyc()) {
            $this->command?->error("{$email} n'a pas de KYC validé : l'acceptation sera refusée.");

            return null;
        }

        return $provider;
    }

    protected function resoudreLeClient(): ?User
    {
        $email = env('CLIENT_EMAIL');

        $client = $email
            ? User::query()->where('email', $email)->first()
            : User::query()->where('role', 'client')->orderBy('id')->first();

        if (! $client) {
            $this->command?->error('Aucun client en base : semez des comptes de développement au préalable.');
        }

        return $client;
    }

    /**
     * LE MÉTIER, SA ZONE ET SON TARIF — les trois ensemble, ou rien.
     *
     * @return array{0: ?object, 1: ?int, 2: float}
     */
    protected function resoudreLeService(): array
    {
        $slug = env('TRADE_SLUG', 'nettoyage');

        $ligne = DB::table('trade_zone_pricing')
            ->join('trades', 'trades.id', '=', 'trade_zone_pricing.trade_id')
            ->where('trade_zone_pricing.is_active', true)
            ->where('trades.is_active', true)
            ->when($slug, fn ($q) => $q->where('trades.slug', $slug))
            ->select([
                'trades.id as trade_id',
                'trades.slug',
                'trades.name',
                'trades.estimated_duration_min',
                'trade_zone_pricing.service_zone_id',
                'trade_zone_pricing.base_rate_cents',
            ])
            ->orderBy('trade_zone_pricing.id')
            ->first();

        if (! $ligne) {
            return [null, null, 0.0];
        }

        return [
            $ligne,
            (int) $ligne->service_zone_id,
            round(((int) $ligne->base_rate_cents) / 100, 2),
        ];
    }

    protected function creerLaReservation(User $client, object $trade, ?int $zoneId, float $prix): Booking
    {
        // L'heure prévue est DERRIÈRE nous de quinze minutes : une mission qui démarre à l'instant
        // sur un rendez-vous prévu dans deux jours serait un état qu'aucun parcours ne produit.
        $prevu = now()->subMinutes(15);
        $duree = max(60, (int) ($trade->estimated_duration_min ?: 120));

        // La forme est celle qu'écrit le moteur de commande (`OrderConfirmationService`) : métier +
        // zone + point A, et les alias FR que la synchronisation de mission lit pour l'horaire.
        // `en_attente` d'abord : c'est la confirmation, plus bas, qui fait naître la mission.
        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'status' => BookingStatus::EN_ATTENTE,
            'booking_mode' => 'scheduled',
            'priority' => 'normal',

            'trade_id' => (int) $trade->trade_id,
            'service_zone_id' => $zoneId,

            'address' => '10 Rue des Arts',
            'adresse' => '10 Rue des Arts',
            'city' => 'Bruxelles',
            'ville' => 'Bruxelles',
            'postal_code' => '1000',
            'code_postal' => '1000',
            'postal_code_id' => DB::table('postal_codes')->where('code', '1000')->value('id'),
            'country' => 'BE',
            'destination_lat' => 50.8466,
            'destination_lng' => 4.3528,

            'scheduled_at' => $prevu,
            'scheduled_date' => $prevu->toDateString(),
            'scheduled_time' => $prevu->format('H:i:s'),
            'date' => $prevu->toDateString(),
            'heure' => $prevu->format('H:i:s'),

            'estimated_duration_minutes' => $duree,
            'estimated_price' => $prix > 0 ? $prix : null,
            'devis_estime' => $prix > 0 ? $prix : null,
            'currency' => 'EUR',

            'contact_name' => $client->name,
            'contact_phone' => $client->phone,
            'customer_comment' => 'Fixture de développement : mission déjà démarrée.',
        ]);
    }

    protected function rapport(Mission $mission, Booking $booking, User $provider, User $client, object $trade): void
    {
        $this->command?->info("Mission #{$mission->id} — statut : {$mission->status}");
        $this->command?->line("  métier       : {$trade->name}");
        $this->command?->line("  prestataire  : {$provider->name} <{$provider->email}> (lead_provider_user_id = {$mission->lead_provider_user_id})");
        $this->command?->line("  client       : {$client->name} <{$client->email}>");
        $this->command?->line("  réservation  : #{$booking->id} {$booking->booking_reference} — statut : {$booking->status}");
        $this->command?->line('  démarrée à   : '.($mission->actual_start_at?->format('Y-m-d H:i:s') ?? '—'));

        if ($mission->status !== MissionStatus::STARTED) {
            $this->command?->error('  ⚠ La mission n\'est PAS démarrée : le parcours s\'est arrêté avant.');
        }
    }
}
