<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\MissionAftercareSummary;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Models\MissionReport;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MissionAftercareSummaryCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
    }

    public function test_owner_via_rendez_vous_sees_full_summary(): void
    {
        $booking = Booking::factory()->create(['client_id' => $this->client->id]);
        $mission = Mission::factory()->create([
            'booking_id' => $booking->id,
            'lead_employee_id' => User::factory()->employe()->create()->id,
        ]);

        MissionMedia::factory()->create([
            'mission_id' => $mission->id,
            'media_type' => 'before_photo',
            'caption' => 'Avant intervention',
        ]);
        MissionMedia::factory()->create([
            'mission_id' => $mission->id,
            'media_type' => 'after_photo',
            'caption' => null,
        ]);

        $checklist = MissionChecklist::factory()->completed()->create([
            'mission_id' => $mission->id,
        ]);
        MissionChecklistItem::factory()->completed()->create([
            'mission_checklist_id' => $checklist->id,
            'notes' => 'Tout est propre',
        ]);
        MissionChecklistItem::factory()->create([
            'mission_checklist_id' => $checklist->id,
        ]);

        MissionIncident::factory()->create([
            'mission_id' => $mission->id,
            'title' => 'Souci accès',
            'description' => 'Porte fermée',
        ]);

        MissionReport::factory()->validated()->create([
            'mission_id' => $mission->id,
        ]);

        Livewire::actingAs($this->client)
            ->test(MissionAftercareSummary::class, ['mission' => $mission])
            ->assertOk()
            ->assertViewHas('beforePhotos', fn ($p) => $p->count() === 1)
            ->assertViewHas('afterPhotos', fn ($p) => $p->count() === 1)
            ->assertViewHas('checklist', fn ($c) => $c !== null && (int) $c->id === (int) $checklist->id)
            ->assertViewHas('incidents', fn ($i) => $i->count() === 1)
            ->assertViewHas('report', fn ($r) => $r !== null);
    }

    public function test_owner_via_organization_account_can_view(): void
    {
        $account = OrganizationAccount::factory()->create();

        $orgUser = User::factory()->client()->create([
            'organization_account_id' => $account->id,
        ]);

        $booking = Booking::factory()->create();
        $mission = Mission::factory()->create([
            'booking_id' => $booking->id,
            'organization_account_id' => $account->id,
        ]);

        Livewire::actingAs($orgUser)
            ->test(MissionAftercareSummary::class, ['mission' => $mission])
            ->assertOk()
            ->assertViewHas('report', null)
            ->assertViewHas('checklist', null);
    }

    public function test_non_owner_is_forbidden(): void
    {
        $booking = Booking::factory()->create();
        $mission = Mission::factory()->create([
            'booking_id' => $booking->id,
            'organization_account_id' => null,
        ]);

        $stranger = User::factory()->client()->create([
            'organization_account_id' => null,
        ]);

        Livewire::actingAs($stranger)
            ->test(MissionAftercareSummary::class, ['mission' => $mission])
            ->assertForbidden();
    }
}
