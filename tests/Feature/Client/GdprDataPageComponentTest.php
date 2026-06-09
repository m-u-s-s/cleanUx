<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\GdprDataPage;
use App\Models\GdprDataRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage for the client GDPR self-service Livewire component.
 *
 * Exercises mount/render plus every wire action — requestExport (happy +
 * already-in-progress guard), downloadExport (valid + unavailable guard),
 * requestErasure (validation, happy, already-active guard) and cancelErasure
 * (valid + unknown-id no-op) — together with the render-time latestExport /
 * activeErasure derivations.
 */
class GdprDataPageComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        Event::fake();
    }

    #[Test]
    public function it_renders_for_an_authenticated_client(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->assertOk()
            ->assertViewHas('requests')
            ->assertViewHas('latestExport', null)
            ->assertViewHas('activeErasure', null);
    }

    #[Test]
    public function request_export_runs_the_pipeline_and_fulfills_the_request(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->call('requestExport')
            ->assertDispatched('toast');

        $request = GdprDataRequest::where('user_id', $client->id)
            ->ofType(GdprDataRequest::TYPE_EXPORT)
            ->first();

        $this->assertNotNull($request);
        $this->assertSame(GdprDataRequest::STATUS_FULFILLED, $request->status);
        $this->assertNotNull($request->export_file_path);
        Storage::disk('local')->assertExists($request->export_file_path);
    }

    #[Test]
    public function request_export_is_blocked_when_one_is_already_processing(): void
    {
        $client = User::factory()->client()->create();

        GdprDataRequest::create([
            'user_id' => $client->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_PROCESSING,
            'reference' => 'GDPR-INPROGRESS',
            'requested_at' => now(),
        ]);

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->call('requestExport')
            ->assertDispatched('toast');

        // No second export row should have been created.
        $this->assertSame(1, GdprDataRequest::where('user_id', $client->id)
            ->ofType(GdprDataRequest::TYPE_EXPORT)->count());
    }

    #[Test]
    public function download_export_serves_a_fulfilled_export_file(): void
    {
        $client = User::factory()->client()->create();

        $path = 'gdpr-exports/GDPR-DL00001.json';
        Storage::disk('local')->put($path, json_encode(['ok' => true]));

        $request = GdprDataRequest::create([
            'user_id' => $client->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_FULFILLED,
            'reference' => 'GDPR-DL00001',
            'requested_at' => now(),
            'fulfilled_at' => now(),
            'export_file_path' => $path,
            'export_format' => 'json',
            'expires_at' => now()->addDays(10),
        ]);

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->assertViewHas('latestExport', fn ($e) => $e !== null && $e->id === $request->id)
            ->call('downloadExport', $request->id)
            ->assertFileDownloaded('GDPR-DL00001.json');
    }

    #[Test]
    public function download_export_reports_an_error_when_unavailable(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->call('downloadExport', 999999)
            ->assertDispatched('toast');
    }

    #[Test]
    public function request_erasure_requires_the_confirmation_checkbox(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->set('confirmErasure', false)
            ->call('requestErasure')
            ->assertHasErrors(['confirmErasure']);

        $this->assertSame(0, GdprDataRequest::where('user_id', $client->id)
            ->ofType(GdprDataRequest::TYPE_ERASURE)->count());
    }

    #[Test]
    public function request_erasure_schedules_a_grace_period_request(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->set('confirmErasure', true)
            ->set('erasureReason', 'I want to leave')
            ->call('requestErasure')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('confirmErasure', false)
            ->assertSet('erasureReason', '');

        $request = GdprDataRequest::where('user_id', $client->id)
            ->ofType(GdprDataRequest::TYPE_ERASURE)
            ->first();

        $this->assertNotNull($request);
        $this->assertSame(GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD, $request->status);
        $this->assertSame('I want to leave', $request->reason);
    }

    #[Test]
    public function request_erasure_is_blocked_when_one_is_already_active(): void
    {
        $client = User::factory()->client()->create();

        GdprDataRequest::create([
            'user_id' => $client->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => 'GDPR-ERASEACTIVE',
            'requested_at' => now(),
            'grace_period_ends_at' => now()->addDays(30),
        ]);

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->assertViewHas('activeErasure', fn ($e) => $e !== null)
            ->set('confirmErasure', true)
            ->call('requestErasure')
            ->assertDispatched('toast');

        $this->assertSame(1, GdprDataRequest::where('user_id', $client->id)
            ->ofType(GdprDataRequest::TYPE_ERASURE)->count());
    }

    #[Test]
    public function cancel_erasure_cancels_an_active_request(): void
    {
        $client = User::factory()->client()->create();

        $request = GdprDataRequest::create([
            'user_id' => $client->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => 'GDPR-ERASECANCEL',
            'requested_at' => now(),
            'grace_period_ends_at' => now()->addDays(30),
        ]);

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->call('cancelErasure', $request->id)
            ->assertDispatched('toast');

        $this->assertSame(GdprDataRequest::STATUS_CANCELLED, $request->fresh()->status);
    }

    #[Test]
    public function cancel_erasure_is_a_silent_no_op_for_an_unknown_id(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(GdprDataPage::class)
            ->call('cancelErasure', 424242)
            ->assertNotDispatched('toast');
    }
}
