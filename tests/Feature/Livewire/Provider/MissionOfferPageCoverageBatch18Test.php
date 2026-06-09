<?php

namespace Tests\Feature\Livewire\Provider;

use App\Livewire\Provider\MissionOfferPage;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MissionOfferPageCoverageBatch18Test extends TestCase
{
    use RefreshDatabase;

    private function pendingOfferFor(User $owner): MissionAssignment
    {
        $mission = Mission::factory()->planned()->create();

        return MissionAssignment::factory()->lead()->create([
            'mission_id' => $mission->id,
            'user_id' => $owner->id,
            'assignment_status' => 'assigned',
            'notification_sent_at' => now()->subSeconds(3),
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function test_mount_and_render_expose_the_assignment_property(): void
    {
        $owner = User::factory()->employe()->create();
        $assignment = $this->pendingOfferFor($owner);
        $this->actingAs($owner);

        $component = Livewire::test(MissionOfferPage::class, ['assignment' => $assignment->id])
            ->assertOk()
            ->assertSet('assignmentId', $assignment->id);

        $this->assertNotNull($component->instance()->assignment);
        $this->assertSame($assignment->id, $component->instance()->assignment->id);
    }

    public function test_accept_marks_offer_accepted_and_redirects(): void
    {
        $owner = User::factory()->employe()->create();
        $assignment = $this->pendingOfferFor($owner);
        $this->actingAs($owner);

        Livewire::test(MissionOfferPage::class, ['assignment' => $assignment->id])
            ->call('accept')
            ->assertSet('messageType', 'success')
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('mission_assignments', [
            'id' => $assignment->id,
            'assignment_status' => 'accepted',
        ]);
    }

    public function test_accept_on_foreign_offer_flashes_ownership_error(): void
    {
        $owner = User::factory()->employe()->create();
        $intruder = User::factory()->employe()->create();
        $assignment = $this->pendingOfferFor($owner);
        $this->actingAs($intruder);

        Livewire::test(MissionOfferPage::class, ['assignment' => $assignment->id])
            ->call('accept')
            ->assertSet('messageType', 'error')
            ->assertNoRedirect();

        $this->assertDatabaseHas('mission_assignments', [
            'id' => $assignment->id,
            'assignment_status' => 'assigned',
        ]);
    }

    public function test_accept_on_missing_offer_flashes_not_found_error(): void
    {
        $this->actingAs(User::factory()->employe()->create());

        Livewire::test(MissionOfferPage::class, ['assignment' => 999999])
            ->call('accept')
            ->assertSet('messageType', 'error')
            ->assertSet('message', 'Cette offre n\'existe plus.')
            ->assertNoRedirect();
    }

    public function test_accept_already_accepted_offer_flashes_domain_message(): void
    {
        $owner = User::factory()->employe()->create();
        $mission = Mission::factory()->planned()->create();
        $assignment = MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $owner->id,
        ]);
        $this->actingAs($owner);

        Livewire::test(MissionOfferPage::class, ['assignment' => $assignment->id])
            ->call('accept')
            ->assertSet('messageType', 'error')
            ->assertNoRedirect();
    }

    public function test_accept_generic_failure_flashes_fallback_error(): void
    {
        $owner = User::factory()->employe()->create();
        $assignment = $this->pendingOfferFor($owner);
        $this->actingAs($owner);

        $this->mock(MissionDispatchService::class, function ($mock) {
            $mock->shouldReceive('accept')->andThrow(new \RuntimeException('boom'));
        });

        Livewire::test(MissionOfferPage::class, ['assignment' => $assignment->id])
            ->call('accept')
            ->assertSet('messageType', 'error')
            ->assertSet('message', 'Erreur lors de l\'acceptation.')
            ->assertNoRedirect();
    }

    public function test_decline_marks_offer_declined_and_redirects(): void
    {
        $owner = User::factory()->employe()->create();
        $assignment = $this->pendingOfferFor($owner);
        $this->actingAs($owner);

        Livewire::test(MissionOfferPage::class, ['assignment' => $assignment->id])
            ->set('declineReason', 'Trop loin')
            ->call('decline')
            ->assertSet('messageType', 'success')
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('mission_assignments', [
            'id' => $assignment->id,
            'assignment_status' => 'declined',
            'decline_reason' => 'Trop loin',
        ]);
    }

    public function test_decline_already_declined_offer_flashes_domain_message(): void
    {
        $owner = User::factory()->employe()->create();
        $mission = Mission::factory()->planned()->create();
        $assignment = MissionAssignment::factory()->declined()->create([
            'mission_id' => $mission->id,
            'user_id' => $owner->id,
        ]);
        $this->actingAs($owner);

        Livewire::test(MissionOfferPage::class, ['assignment' => $assignment->id])
            ->call('decline')
            ->assertSet('messageType', 'error')
            ->assertNoRedirect();
    }

    public function test_decline_generic_failure_flashes_fallback_error(): void
    {
        $owner = User::factory()->employe()->create();
        $assignment = $this->pendingOfferFor($owner);
        $this->actingAs($owner);

        $this->mock(MissionDispatchService::class, function ($mock) {
            $mock->shouldReceive('decline')->andThrow(new \RuntimeException('boom'));
        });

        Livewire::test(MissionOfferPage::class, ['assignment' => $assignment->id])
            ->call('decline')
            ->assertSet('messageType', 'error')
            ->assertSet('message', 'Erreur lors du refus.')
            ->assertNoRedirect();
    }

    public function test_clear_message_resets_flash_state(): void
    {
        $this->actingAs(User::factory()->employe()->create());

        Livewire::test(MissionOfferPage::class, ['assignment' => 999999])
            ->call('accept')
            ->assertSet('messageType', 'error')
            ->call('clearMessage')
            ->assertSet('message', null)
            ->assertSet('messageType', null);
    }
}
