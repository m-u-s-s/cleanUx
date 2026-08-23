<?php

namespace Tests\Feature\TripTracking;

use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use Database\Factories\TripTrackingPointFactory;
use Database\Factories\TripTrackingSessionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La fabrique de relevés ne doit pas produire de collisions. */
class TripTrackingPointFactoryTest extends TestCase
{
    use RefreshDatabase;

    /** Cent relevés sur une seule session. */
    public function test_a_hundred_points_on_one_session_never_collide(): void
    {
        $session = TripTrackingSessionFactory::new()->create();

        TripTrackingPointFactory::new()->count(100)->create(['session_id' => $session->id]);

        $this->assertSame(100, TripTrackingPoint::where('session_id', $session->id)->count());
        $this->assertSame(
            100,
            TripTrackingPoint::where('session_id', $session->id)->distinct('client_sequence')->count('client_sequence'),
        );
    }

    /** Deux sessions distinctes peuvent réemployer le même numéro : l'unicité est PAR session. */
    public function test_two_sessions_may_share_the_same_sequence_number(): void
    {
        $first = TripTrackingSessionFactory::new()->create();
        $second = TripTrackingSessionFactory::new()->create();

        TripTrackingPointFactory::new()->create(['session_id' => $first->id, 'client_sequence' => 1]);

        $this->assertNotNull(
            TripTrackingPointFactory::new()->create(['session_id' => $second->id, 'client_sequence' => 1]),
        );
    }

    /** La session reste utilisable derrière : la fabrique n'a rien cassé de son comportement. */
    public function test_the_session_still_aggregates_its_points(): void
    {
        $session = TripTrackingSessionFactory::new()->create();
        TripTrackingPointFactory::new()->count(3)->create(['session_id' => $session->id]);

        $this->assertInstanceOf(TripTrackingSession::class, $session->fresh());
        $this->assertSame(3, $session->points()->count());
    }
}
