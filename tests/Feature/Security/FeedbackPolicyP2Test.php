<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use App\Policies\FeedbackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2 — FeedbackPolicy authorization (dead code removed; the real first-return logic is asserted). */
class FeedbackPolicyP2Test extends TestCase
{
    use RefreshDatabase;

    private FeedbackPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FeedbackPolicy;
    }

    public function test_client_can_view_update_delete_only_their_own_feedback(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        // The Feedback factory syncs client_id from the booking's client, so seed a booking owned
        // by $owner rather than overriding client_id directly.
        $booking = Booking::factory()->create(['client_id' => $owner->id]);
        $fb = Feedback::factory()->create(['rendez_vous_id' => $booking->id])->fresh();

        $this->assertTrue($this->policy->view($owner, $fb));
        $this->assertTrue($this->policy->update($owner, $fb));
        $this->assertTrue($this->policy->delete($owner, $fb));

        $this->assertFalse($this->policy->view($other, $fb));
        $this->assertFalse($this->policy->update($other, $fb));
        $this->assertFalse($this->policy->delete($other, $fb));
    }

    public function test_employe_can_view_feedback_for_their_own_mission_only(): void
    {
        $provider = User::factory()->employe()->create();
        $stranger = User::factory()->employe()->create();
        $booking = Booking::factory()->create(['employe_id' => $provider->id]);
        $fb = Feedback::factory()->create(['rendez_vous_id' => $booking->id]);

        $this->assertTrue($this->policy->view($provider, $fb->fresh()));
        $this->assertFalse($this->policy->view($stranger, $fb->fresh()));
    }

    public function test_only_active_admin_can_respond_readonly_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $fb = Feedback::factory()->create(['client_id' => $client->id]);

        $this->assertTrue($this->policy->respond($admin, $fb));
        $this->assertFalse($this->policy->respond($client, $fb));
    }
}
