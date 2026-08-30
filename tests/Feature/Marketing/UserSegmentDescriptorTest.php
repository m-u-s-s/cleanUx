<?php

namespace Tests\Feature\Marketing;

use App\Models\Booking;
use App\Models\User;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Marketing\UserSegmentDescriptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les trois champs derives plantaient : la jointure etait posee sur un constructeur imbrique. */
class UserSegmentDescriptorTest extends TestCase
{
    use RefreshDatabase;

    private function compter(array $noeud): int
    {
        $entite = app(UserSegmentDescriptor::class);
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);

        return $requete->distinct()->count('users.id');
    }

    public function test_bookings_count_filtre_sur_le_nombre_de_reservations(): void
    {
        $charge = User::factory()->client()->create();
        Booking::factory()->count(3)->create(['client_id' => $charge->id]);
        User::factory()->client()->create();   // TEMOIN : aucune reservation

        $this->assertSame(1, $this->compter(['field' => 'bookings_count', 'op' => 'gt', 'value' => 2]));
    }

    public function test_last_booking_at_repond(): void
    {
        $avec = User::factory()->client()->create();
        Booking::factory()->create(['client_id' => $avec->id]);
        User::factory()->client()->create();   // TEMOIN

        $this->assertSame(1, $this->compter(['field' => 'last_booking_at', 'op' => 'is_not_null', 'value' => null]));
    }

    public function test_total_spent_cents_repond(): void
    {
        $payeur = User::factory()->client()->create();

        // `final_price` n'est PAS `fillable` : passe au factory, il serait ecarte EN SILENCE
        // et le test echouerait sans dire pourquoi.
        Booking::factory()->create(['client_id' => $payeur->id])
            ->forceFill(['final_price' => 200])->save();

        User::factory()->client()->create();   // TEMOIN

        $this->assertSame(1, $this->compter(['field' => 'total_spent_cents', 'op' => 'gte', 'value' => 100]));
    }

    /** DEUX FOIS LE MEME CHAMP DERIVE : deux jointures de meme alias, erreur SQL 1066. */
    public function test_le_meme_champ_derive_deux_fois_ne_double_pas_la_jointure(): void
    {
        $bon = User::factory()->client()->create();
        Booking::factory()->count(5)->create(['client_id' => $bon->id]);

        $trop = User::factory()->client()->create();
        Booking::factory()->count(20)->create(['client_id' => $trop->id]);

        $this->assertSame(1, $this->compter(['and' => [
            ['field' => 'bookings_count', 'op' => 'gt', 'value' => 2],
            ['field' => 'bookings_count', 'op' => 'lt', 'value' => 10],
        ]]));
    }

    public function test_les_champs_declares_sont_ceux_de_la_configuration(): void
    {
        $this->assertSame(
            config('marketing.segment_fields'),
            array_keys(app(UserSegmentDescriptor::class)->fields())
        );
    }
}
