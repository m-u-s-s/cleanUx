<?php

namespace Tests\Feature\Orphans;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Récupération d'orphelins — les pages auparavant non routées sont désormais
 * accessibles et s'affichent (smoke), avec la garde de rôle attendue.
 */
class OrphanPageRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        /*
         * LES CAPACITES SONT ACCORDEES, et c'est ce que `EnforceModuleGate` exige desormais.
         *
         * Les quatre-vingt-quatre modules d'administration declarent la leur, et la porte les fait
         * appliquer. Un compte `platform_role = 'admin'` sans capacite recevait donc un 403 sur ces
         * ecrans -- le garde faisait son travail.
         */
        return User::factory()->adminComplet()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_admin_zones_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('admin.zones'))->assertOk();
    }

    public function test_admin_entreprises_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('admin.entreprises'))->assertOk();
    }

    public function test_non_admin_cannot_open_zones(): void
    {
        $this->actingAs(User::factory()->client()->create(['email_verified_at' => now(), 'is_active' => true]))
            ->get(route('admin.zones'))
            ->assertForbidden();
    }

    public function test_client_recurring_series_page_renders(): void
    {
        $client = User::factory()->client()->create(['email_verified_at' => now(), 'is_active' => true]);
        $booking = Booking::factory()->recurringSeries()->create(['client_id' => $client->id]);

        $this->actingAs($client)->get(route('client.rendezvous.series', $booking))->assertOk();
    }

    public function test_admin_recurring_series_page_renders(): void
    {
        $booking = Booking::factory()->recurringSeries()->create();

        $this->actingAs($this->admin())->get(route('admin.recurrence.edit', $booking))->assertOk();
    }
}
