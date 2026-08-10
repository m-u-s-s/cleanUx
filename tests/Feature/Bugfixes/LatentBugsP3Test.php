<?php

namespace Tests\Feature\Bugfixes;

use App\Livewire\AdminFeedbacks;
use App\Livewire\Client\Templates\RecurringTemplatesGallery;
use App\Livewire\Employe\MesRendezVous;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\Mission;
use App\Models\RecurringTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P3 — latent source bugs logged during the coverage campaign, now fixed.
 */
class LatentBugsP3Test extends TestCase
{
    use RefreshDatabase;

    /** Bug #1: Gate::authorize('respond', Feedback::class) → ArgumentCountError on response edit. */
    public function test_admin_can_save_a_feedback_response_without_argument_count_error(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);
        $feedback = Feedback::factory()->create(['rendez_vous_id' => $booking->id]);

        Livewire::actingAs($admin)
            ->test(AdminFeedbacks::class)
            ->set("reponse.{$feedback->id}", 'Merci pour votre retour !')
            ->assertOk();

        $this->assertSame('Merci pour votre retour !', $feedback->fresh()->reponse_admin);
    }

    /** Bug #2: $this->selectedRdv (non-existent computed prop) → mission never resolved. */
    public function test_selected_mission_resolves_via_the_selected_rendez_vous(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
        ]);
        $mission = Mission::query()->create([
            'booking_id' => $booking->id,
            'status' => 'planned',
        ]);

        Livewire::actingAs($provider)
            ->test(MesRendezVous::class)
            ->set('selectedRdvId', $booking->id)
            ->assertSet('selectedMission', fn ($m) => $m !== null && (int) $m->id === (int) $mission->id);
    }

    /** Bug #3: $template->default_time->format() on an uncast `time` string → TypeError. */
    public function test_opening_apply_modal_formats_the_default_time_without_type_error(): void
    {
        $client = User::factory()->client()->create();
        $template = RecurringTemplate::query()->create([
            'name' => 'Hebdo matin',
            'slug' => 'hebdo-matin',
            'frequency' => 'weekly',
            'default_time' => '08:00:00',
            'is_active' => true,
        ]);

        Livewire::actingAs($client)
            ->test(RecurringTemplatesGallery::class)
            ->call('openApplyModal', $template->id)
            ->assertSet('applyCustomTime', '08:00');
    }
}
