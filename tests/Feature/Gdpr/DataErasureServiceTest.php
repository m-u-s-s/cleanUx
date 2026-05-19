<?php

namespace Tests\Feature\Gdpr;

use App\Models\Booking;
use App\Models\GdprDataRequest;
use App\Models\User;
use App\Services\Gdpr\DataErasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataErasureServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_creates_pending_request_with_grace_period(): void
    {
        $user = User::factory()->client()->create();

        $request = app(DataErasureService::class)->schedule($user, 'Mes raisons');

        $this->assertSame(GdprDataRequest::TYPE_ERASURE, $request->type);
        $this->assertSame(GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD, $request->status);
        $this->assertStringStartsWith('GDPR-', $request->reference);
        $this->assertNotNull($request->grace_period_ends_at);
        $this->assertTrue($request->grace_period_ends_at->isFuture());

        $user->refresh();
        $this->assertNotNull($user->deletion_scheduled_at);
    }

    public function test_cancel_reverts_user_deletion_scheduled(): void
    {
        $user = User::factory()->client()->create();
        $request = app(DataErasureService::class)->schedule($user);

        $this->assertNotNull($user->fresh()->deletion_scheduled_at);

        app(DataErasureService::class)->cancel($request);

        $this->assertSame(GdprDataRequest::STATUS_CANCELLED, $request->fresh()->status);
        $this->assertNull($user->fresh()->deletion_scheduled_at);
    }

    public function test_execute_anonymizes_user_and_preserves_bookings(): void
    {
        $user = User::factory()->client()->create([
            'name' => 'Bob Original',
            'email' => 'bob@example.com',
            'phone' => '+32411111111',
        ]);

        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->subMonth(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 80,
        ]);

        $request = app(DataErasureService::class)->schedule($user);

        // Forcer grace period passé
        $request->forceFill(['grace_period_ends_at' => now()->subDay()])->save();

        app(DataErasureService::class)->execute($request->fresh());

        $user->refresh();
        $this->assertNotNull($user->anonymized_at);
        $this->assertSame('Utilisateur supprimé', $user->name);
        $this->assertStringContainsString('@anonymized.cleanux', $user->email);
        $this->assertNull($user->phone);
        $this->assertFalse((bool) $user->is_active);

        // Booking préservé (FK client_id intact)
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'client_id' => $user->id,
            'status' => 'termine',
        ]);
    }

    public function test_execute_before_grace_period_throws(): void
    {
        $user = User::factory()->client()->create();
        $request = app(DataErasureService::class)->schedule($user);

        $this->expectException(\RuntimeException::class);
        app(DataErasureService::class)->execute($request);
    }

    public function test_restrict_processing_marks_user(): void
    {
        $user = User::factory()->client()->create();

        $request = app(DataErasureService::class)->restrictProcessing($user, 'Plainte CNIL');

        $this->assertSame(GdprDataRequest::TYPE_RESTRICTION, $request->type);
        $this->assertSame(GdprDataRequest::STATUS_FULFILLED, $request->status);

        $user->refresh();
        $this->assertNotNull($user->processing_restricted_at);
    }

    public function test_lift_restriction_clears_flag(): void
    {
        $user = User::factory()->client()->create();
        app(DataErasureService::class)->restrictProcessing($user);

        app(DataErasureService::class)->liftRestriction($user->fresh());

        $this->assertNull($user->fresh()->processing_restricted_at);
    }
}
