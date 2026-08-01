<?php

namespace Tests\Feature\TripTracking;

use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use Database\Factories\TripTrackingPointFactory;
use Database\Factories\TripTrackingSessionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La fabrique de relevés ne doit pas produire de collisions.
 *
 * `trip_tracking_points` porte un index UNIQUE sur `(session_id, client_sequence)`. Cette colonne
 * était tirée au hasard entre 1 et 100 : créer trois relevés sur une même session — ce que fait le
 * test du détail de session — collisionnait environ trois fois sur cent.
 *
 * Concrètement, la suite échouait une exécution sur trente-quatre, sur un test qui n'avait rien
 * fait de mal. Une suite qui rougit au hasard finit par ne plus être crue, et c'est le jour où
 * elle a raison qu'on l'ignore.
 */
class TripTrackingPointFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cent relevés sur une seule session.
     *
     * Le nombre est choisi pour être décisif : sur cent valeurs possibles, cent tirages
     * collisionnent à coup sûr. Trois relevés n'auraient prouvé le correctif que 3 % du temps —
     * autant dire jamais.
     */
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

    /**
     * Deux sessions distinctes peuvent réemployer le même numéro : l'unicité est PAR session.
     *
     * Le numéro vient du téléphone du prestataire et sert à dédoublonner ses propres relevés ; il
     * repart à un pour chaque trajet. Une unicité globale interdirait au second prestataire
     * d'envoyer son premier relevé.
     */
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
