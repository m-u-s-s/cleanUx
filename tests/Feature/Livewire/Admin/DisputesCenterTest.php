<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Disputes\DisputesCenter;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\DisputeResolution;
use App\Models\User;
use App\Services\Disputes\DisputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DisputesCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected User $provider;

    protected User $admin;

    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
        $this->provider = User::factory()->employe()->create();
        $this->admin = User::factory()->admin()->create();

        $this->booking = Booking::create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 100,
        ]);
    }

    protected function openCase(string $priority = 'normal'): ComplaintCase
    {
        return app(DisputeService::class)->open($this->client, [
            'subject' => 'Service incomplet',
            'description' => 'Description suffisante pour passer la validation interne.',
            'category' => 'quality',
            'priority' => $priority,
            'severity' => 'medium',
            'booking_id' => $this->booking->id,
        ]);
    }

    public function test_mount_renders_with_kpis_and_list(): void
    {
        $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->assertOk()
            ->assertViewHas('kpis')
            ->assertViewHas('list')
            ->assertViewHas('admins');
    }

    public function test_select_and_close_detail(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->assertSet('selectedId', $case->id)
            ->set('messageBody', 'draft')
            ->call('closeDetail')
            ->assertSet('selectedId', null)
            ->assertSet('messageBody', '');
    }

    public function test_assign_to_me_assigns_case_to_admin(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->call('assignToMe')
            ->assertDispatched('toast');

        $this->assertSame((int) $this->admin->id, (int) $case->fresh()->assigned_to);
    }

    public function test_transition_to_updates_status(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->call('transitionTo', ComplaintCase::STATUS_INVESTIGATING)
            ->assertDispatched('toast');

        $this->assertSame(ComplaintCase::STATUS_INVESTIGATING, $case->fresh()->status);
    }

    public function test_escalate_increments_level(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->call('escalate')
            ->assertDispatched('toast');

        $this->assertSame(1, $case->fresh()->escalation_level);
        $this->assertSame(ComplaintCase::STATUS_ESCALATED, $case->fresh()->status);
    }

    public function test_post_message_requires_body(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->set('messageBody', '')
            ->call('postMessage')
            ->assertHasErrors(['messageBody' => 'required']);
    }

    public function test_post_message_adds_admin_event(): void
    {
        $case = $this->openCase();
        $before = $case->events()->count();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->set('messageBody', 'Nous traitons votre dossier.')
            ->set('messageVisibility', DisputeEvent::VISIBILITY_ALL)
            ->call('postMessage')
            ->assertHasNoErrors()
            ->assertSet('messageBody', '')
            ->assertDispatched('toast');

        $this->assertSame($before + 1, $case->fresh()->events()->count());
    }

    public function test_apply_resolution_no_action_resolves_case(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->set('resolutionType', DisputeResolution::TYPE_NO_ACTION)
            ->set('resolutionExplanation', 'Litige clos sans action financière.')
            ->call('applyResolution')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame(ComplaintCase::STATUS_RESOLVED, $case->fresh()->status);
    }

    public function test_apply_resolution_partial_refund_requires_amount(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->set('resolutionType', DisputeResolution::TYPE_REFUND_PARTIAL)
            ->set('resolutionExplanation', 'Remboursement partiel justifié.')
            ->set('resolutionAmount', null)
            ->call('applyResolution')
            ->assertHasErrors(['resolutionAmount']);

        $this->assertSame(ComplaintCase::STATUS_OPEN, $case->fresh()->status);
    }

    public function test_apply_resolution_credit_creates_resolution(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->call('select', $case->id)
            ->set('resolutionType', DisputeResolution::TYPE_CREDIT)
            ->set('resolutionAmount', 25.0)
            ->set('resolutionExplanation', 'Avoir octroyé pour le désagrément.')
            ->call('applyResolution')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame(ComplaintCase::STATUS_RESOLVED, $case->fresh()->status);
        $this->assertSame(1, $case->fresh()->resolutions()->count());
    }

    public function test_render_applies_filters_and_search(): void
    {
        $case = $this->openCase();

        Livewire::actingAs($this->admin)
            ->test(DisputesCenter::class)
            ->set('filterStatus', ComplaintCase::STATUS_OPEN)
            ->set('filterPriority', ComplaintCase::PRIORITY_NORMAL)
            ->set('filterCategory', ComplaintCase::CATEGORY_QUALITY)
            ->set('showOverdueOnly', false)
            ->set('search', $case->reference)
            ->assertOk()
            ->assertSee($case->reference);
    }
}
