<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Fixture de développement : une mission géolocalisée assignée à un prestataire, afin que la
 * carte du dashboard ait quelque chose à afficher. La base de dév n'a aucune mission.
 *
 * Usage : php artisan db:seed --class=DevProviderMissionSeeder
 *         PROVIDER_EMAIL=autre@exemple.test php artisan db:seed --class=DevProviderMissionSeeder
 */
class DevProviderMissionSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PROVIDER_EMAIL', 'test@test.com');
        $provider = User::where('email', $email)->first();

        if (! $provider) {
            $this->command->error("Prestataire introuvable : {$email}");

            return;
        }

        $client = User::where('role', 'client')->first() ?? User::factory()->create(['name' => 'Client Démo']);
        $serviceCatalog = ServiceCatalog::first();

        // withoutEvents : un Booking `confirme` déclenche RendezVousObserver, qui synchronise
        // une mission legacy via `rendez_vous_id` (MissionFromRendezVousSyncService). Ce chemin
        // legacy calcule planned_start_at depuis les alias FR `date`/`heure` et plante en base
        // MySQL réelle (mode strict, datetime invalide) — un bug préexistant, hors périmètre de
        // ce seeder. On désactive les events le temps de cette création pour ne produire que LA
        // mission voulue ci-dessous (rattachée via `booking_id`, géolocalisée).
        $booking = Booking::withoutEvents(fn () => Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'service_catalog_id' => $serviceCatalog?->id,
            'address' => '10 Rue des Arts',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]));

        // destination_lat/lng, PAS start_lat/lng : ces dernières portent la position GPS du
        // prestataire aux transitions arrived/started. Les renseigner ici à la main donnait une
        // fixture qui affichait un marqueur alors que la production n'en avait aucun — c'est ce
        // qui avait fait passer la vérification manuelle du dashboard carte.
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
            'destination_lat' => 50.8466,
            'destination_lng' => 4.3528,
            'estimated_duration_minutes' => 90,
        ]);

        MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        $this->command->info("Mission #{$mission->id} assignée à {$email} (destination Bruxelles, 50.8466 / 4.3528).");
    }
}
