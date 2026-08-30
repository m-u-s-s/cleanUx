<?php

namespace Tests\Feature\Marketing;

use App\Models\Booking;
use App\Models\User;
use App\Services\Conditions\RuleTreeEvaluator;
use App\Services\Marketing\UserSegmentDescriptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Les trois champs derives plantaient : la jointure etait posee sur un constructeur imbrique. */
class UserSegmentDescriptorTest extends TestCase
{
    use RefreshDatabase;

    /** Aligne sur la production (SegmentEngine::compute) : pluck brut, sans dedoublonner. */
    private function compter(array $noeud): int
    {
        $entite = app(UserSegmentDescriptor::class);
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);

        return count($requete->pluck('users.id')->all());
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

    /** L'unite : `final_price` est en euros, `total_spent_cents` promet des centimes. */
    public function test_total_spent_cents_convertit_les_euros_en_centimes(): void
    {
        $payeur = User::factory()->client()->create();
        Booking::factory()->create(['client_id' => $payeur->id])
            ->forceFill(['final_price' => 200])->save();

        $this->assertSame(1, $this->compter(['field' => 'total_spent_cents', 'op' => 'gte', 'value' => 20000]));

        // TEMOIN : sans la conversion *100, la somme vaudrait 200 et satisferait aussi >= 20001 a tort.
        $this->assertSame(0, $this->compter(['field' => 'total_spent_cents', 'op' => 'gte', 'value' => 20001]));
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

    /** `client_id` est la compatibilite legacy ; `customer_user_id` est la vraie cle etrangere. */
    public function test_bookings_count_compte_via_customer_user_id_quand_client_id_est_nul(): void
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => null, 'customer_user_id' => $client->id]);

        // HasLegacyBookingAliases comble client_id a la sauvegarde : on force le NULL en
        // base, hors Eloquent, pour simuler une ligne legacy dont seul customer_user_id est renseigne.
        DB::table('bookings')->where('id', $booking->id)->update(['client_id' => null]);

        User::factory()->client()->create();   // TEMOIN : aucune reservation

        $this->assertSame(1, $this->compter(['field' => 'bookings_count', 'op' => 'gt', 'value' => 0]));
    }

    public function test_les_champs_declares_sont_ceux_de_la_configuration(): void
    {
        $this->assertSame(
            ['role', 'locale', 'email_domain', 'created_at', 'bookings_count', 'last_booking_at', 'total_spent_cents'],
            array_keys(app(UserSegmentDescriptor::class)->fields())
        );
    }

    /** L'etat de dedoublonnage des jointures vit sur la REQUETE, pas sur l'instance du descripteur. */
    public function test_deux_base_query_sur_la_meme_instance_repondent_toutes_les_deux(): void
    {
        $charge = User::factory()->client()->create();
        Booking::factory()->count(3)->create(['client_id' => $charge->id]);

        $entite = app(UserSegmentDescriptor::class);
        $evaluateur = app(RuleTreeEvaluator::class);
        $noeud = ['field' => 'bookings_count', 'op' => 'gt', 'value' => 2];

        $premiere = $entite->baseQuery();
        $seconde = $entite->baseQuery();

        $evaluateur->apply($premiere, $noeud, $entite);
        $evaluateur->apply($seconde, $noeud, $entite);

        $this->assertSame(1, count($premiere->pluck('users.id')->all()));
        $this->assertSame(1, count($seconde->pluck('users.id')->all()));
    }
}
