<?php

namespace Tests\Feature\Gdpr;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\Feedback;
use App\Models\GdprDataRequest;
use App\Models\User;
use App\Services\Gdpr\DataErasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $this->assertStringContainsString('@anonymized.brio', $user->email);
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

    /**
     * D2: anonymization must also scrub the PII the original implementation missed —
     * booking addresses + notes, free-text feedback / complaints, notification payloads,
     * analytics rows, and audit_events readable labels.
     */
    public function test_execute_scrubs_previously_missed_pii(): void
    {
        $user = User::factory()->client()->create([
            'name' => 'Bob Original',
            'email' => 'bob@example.com',
        ]);

        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->subMonth(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 80,
            'adresse' => '12 rue Secrète',
            'ville' => 'Bruxelles',
            'code_postal' => '1000',
            'notes' => 'Code portail 1234',
        ]);

        $feedback = Feedback::factory()->create([
            'rendez_vous_id' => $booking->id,
            'client_id' => $user->id,
            'commentaire' => 'Mon nom est Bob et mon adresse est 12 rue Secrète',
        ]);
        // The factory may realign client_id from the booking; force the link explicitly.
        $feedback->forceFill(['client_id' => $user->id])->save();

        $complaint = ComplaintCase::factory()->create([
            'client_id' => $user->id,
            'subject' => 'Bob Original problème',
            'description' => 'Contactez-moi au +32411111111',
        ]);

        $analyticsId = DB::table('analytics_events')->insertGetId([
            'event_name' => 'page.viewed',
            'user_id' => $user->id,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditId = DB::table('audit_events')->insertGetId([
            'event_type' => 'user.updated',
            'domain' => 'security',
            'severity' => 'info',
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'actor_label' => 'bob@example.com',
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'subject_label' => 'bob@example.com',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\Dummy',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['name' => 'Bob Original', 'email' => 'bob@example.com']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DataErasureService::class)->anonymizeUser($user);

        // Booking PII scrubbed, accounting row preserved.
        $freshBooking = $booking->fresh();
        $this->assertNull($freshBooking->adresse);
        $this->assertNull($freshBooking->ville);
        $this->assertNull($freshBooking->code_postal);
        $this->assertNull($freshBooking->notes);
        $this->assertSame('termine', $freshBooking->status);
        $this->assertEquals(80, (float) $freshBooking->devis_estime);

        $this->assertNull($feedback->fresh()->commentaire);
        $this->assertSame('[anonymisé]', $complaint->fresh()->subject);
        $this->assertSame('[anonymisé]', $complaint->fresh()->description);
        $this->assertNull(DB::table('analytics_events')->where('id', $analyticsId)->value('user_id'));

        $freshAudit = DB::table('audit_events')->where('id', $auditId)->first();
        $this->assertSame('Utilisateur supprimé', $freshAudit->actor_label);
        $this->assertSame('Utilisateur supprimé', $freshAudit->subject_label);

        $notifData = DB::table('notifications')->where('id', $notificationId)->value('data');
        $this->assertStringNotContainsString('bob@example.com', (string) $notifData);
        $this->assertStringNotContainsString('Bob Original', (string) $notifData);
    }
}
