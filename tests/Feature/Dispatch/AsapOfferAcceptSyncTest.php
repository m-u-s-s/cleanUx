<?php

namespace Tests\Feature\Dispatch;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Décision produit 2026-06-11 : ASAP = dispatch par offre/escalade temps réel
 * UNIQUEMENT (plus de confirmation directe parallèle = fin du double-dispatch).
 *
 * Conséquence : quand le prestataire accepte l'offre, le booking doit être
 * synchronisé (employe_id + status confirmé) pour que le client voie sa mission
 * confirmée — sinon le booking resterait "en attente" alors que la mission est
 * assignée.
 */
class AsapOfferAcceptSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_an_asap_offer_confirms_and_assigns_the_booking(): void
    {
        Notification::fake();

        $provider = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $booking = Booking::factory()->create([
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            'employe_id' => null,
        ]);
        $mission = Mission::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'planned',
        ]);
        $offer = MissionAssignment::factory()->lead()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'assignment_status' => 'assigned',
            'notification_sent_at' => now()->subSeconds(3),
            'expires_at' => now()->addMinutes(2),
        ]);

        app(MissionDispatchService::class)->accept($offer->fresh());

        $booking->refresh();
        $this->assertSame($provider->id, $booking->employe_id, 'Le booking doit être assigné au prestataire qui a accepté.');
        $this->assertSame('confirme', $booking->status, 'Un booking ASAP accepté doit passer en confirmé.');
    }
}
