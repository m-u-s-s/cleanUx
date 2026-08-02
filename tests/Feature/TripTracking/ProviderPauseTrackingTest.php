<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Audit LOW (UX/RGPD) — le prestataire doit pouvoir mettre en pause le partage de
 * sa position en cours de mission ; aucune position ne doit être enregistrée
 * pendant la pause.
 */
class ProviderPauseTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function trackingSession(): TripTrackingSession
    {
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create();

        return app(TripTrackingService::class)->startSession($provider, $booking);
    }

    public function test_pause_blocks_position_sharing(): void
    {
        $service = app(TripTrackingService::class);
        $session = $this->trackingSession();

        $paused = $service->pauseSession($session);
        $this->assertTrue((bool) $paused->is_paused);

        $this->expectException(ValidationException::class);
        $service->recordPing($paused, 50.85, 4.35);
    }

    public function test_resume_reenables_position_sharing(): void
    {
        $service = app(TripTrackingService::class);
        $session = $this->trackingSession();

        $service->pauseSession($session);
        $resumed = $service->resumeSession($session->fresh());
        $this->assertFalse((bool) $resumed->is_paused);

        $point = $service->recordPing($resumed, 50.85, 4.35);
        $this->assertInstanceOf(TripTrackingPoint::class, $point);
    }
}
