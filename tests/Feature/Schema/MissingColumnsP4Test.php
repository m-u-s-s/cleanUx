<?php

namespace Tests\Feature\Schema;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\ChatThread;
use App\Models\FleetEquipment;
use App\Models\LoyaltyReward;
use App\Models\OrganizationMember;
use App\Models\ProviderBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** P4 — columns that code referenced but were never migrated (writes silently dropped). */
class MissingColumnsP4Test extends TestCase
{
    use RefreshDatabase;

    public function test_booking_persists_the_newly_provisioned_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('bookings', 'feedback_demande_envoye_at'));
        $this->assertTrue(Schema::hasColumn('bookings', 'remarque_terrain'));

        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        // Mass-assignment path (SendRendezVousReminders uses $rdv->update([...])).
        $booking->update([
            'feedback_demande_envoye_at' => now(),
            'remarque_terrain' => 'Portail à gauche, code 1234.',
        ]);

        $fresh = $booking->fresh();
        $this->assertNotNull($fresh->feedback_demande_envoye_at, 'timestamp must persist (was silently dropped)');
        $this->assertSame('Portail à gauche, code 1234.', $fresh->remarque_terrain);
    }

    public function test_enterprise_work_orders_has_the_generation_columns(): void
    {
        // Les trois colonnes verifiees ensemble : le generateur les ecrit toutes les trois, et
        // savoir qu'il en manque une ne dit rien des deux autres.
        $absentes = array_values(array_filter(
            ['generated_batch_id', 'generation_status', 'generation_started_at'],
            fn (string $col) => ! Schema::hasColumn('enterprise_work_orders', $col),
        ));

        $this->assertSame([], $absentes, 'Le generateur de missions ecrit ces colonnes sur enterprise_work_orders.');
    }

    /** P4 — models that previously had a factory but no HasFactory trait now resolve Model::factory(). */
    public function test_model_factory_now_resolves_for_previously_traitless_models(): void
    {
        // Les six modeles verifies ensemble : un trait `HasFactory` oublie l'est rarement sur un
        // seul modele, et chaque echec bloque tout test qui voudrait employer cette fabrique.
        $casses = [];

        foreach ([
            BookingTip::class,
            ChatThread::class,
            FleetEquipment::class,
            LoyaltyReward::class,
            ProviderBadge::class,
            OrganizationMember::class,
        ] as $model) {
            try {
                if (! $model::factory()->make() instanceof $model) {
                    $casses[] = class_basename($model).' : la fabrique rend autre chose';
                }
            } catch (\Throwable $e) {
                $casses[] = class_basename($model).' : '.Str::limit($e->getMessage(), 70);
            }
        }

        $this->assertSame([], $casses, 'Ces fabriques ne se resolvent pas.');
    }
}
