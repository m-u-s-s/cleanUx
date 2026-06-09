<?php

namespace Tests\Feature\Assistant;

use App\Enums\OrganizationRole;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Assistant\Tools\Implementations\CancelBookingTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelBookingToolCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    private function tool(): CancelBookingTool
    {
        return new CancelBookingTool;
    }

    public function test_metadata_schema_and_flags(): void
    {
        $tool = $this->tool();

        $this->assertSame('cancel_booking', $tool->name());
        $this->assertStringContainsString('Annule', $tool->description());
        $this->assertFalse($tool->executesImmediately());
        $this->assertTrue($tool->authorize(User::factory()->client()->create()));

        $schema = $tool->inputSchema();
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('booking_id', $schema['properties']);
        $this->assertArrayHasKey('reason', $schema['properties']);
        $this->assertSame(['booking_id', 'reason'], $schema['required']);
    }

    public function test_unknown_booking_returns_error(): void
    {
        $result = $this->tool()->execute(
            User::factory()->client()->create(),
            ['booking_id' => 987654, 'reason' => 'plus besoin']
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('introuvable', $result['error']);
    }

    public function test_owner_via_client_id_can_cancel(): void
    {
        $owner = User::factory()->client()->create();
        $booking = Booking::factory()->confirme()->create(['client_id' => $owner->id]);

        $result = $this->tool()->execute($owner, [
            'booking_id' => $booking->id,
            'reason' => 'changement de planning',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame($booking->id, $result['booking_id']);
        $this->assertSame($booking->booking_reference, $result['booking_reference']);

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame($owner->id, $booking->cancelled_by);
        $this->assertSame('changement de planning', $booking->cancellation_reason);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_owner_via_customer_user_id_can_cancel(): void
    {
        $owner = User::factory()->client()->create();
        $booking = Booking::factory()->confirme()->create([
            'customer_user_id' => $owner->id,
        ]);

        $result = $this->tool()->execute($owner, [
            'booking_id' => $booking->id,
            'reason' => 'erreur de saisie',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_missing_reason_defaults_to_empty_string(): void
    {
        $owner = User::factory()->client()->create();
        $booking = Booking::factory()->confirme()->create(['client_id' => $owner->id]);

        $result = $this->tool()->execute($owner, ['booking_id' => $booking->id]);

        $this->assertTrue($result['ok']);
        $this->assertSame('', $booking->fresh()->cancellation_reason);
    }

    public function test_stranger_is_not_authorized(): void
    {
        $owner = User::factory()->client()->create();
        $stranger = User::factory()->client()->create();
        $booking = Booking::factory()->confirme()->create(['client_id' => $owner->id]);

        $result = $this->tool()->execute($stranger, [
            'booking_id' => $booking->id,
            'reason' => 'pas la mienne',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('pas autorisé', $result['error']);
        $this->assertSame('confirme', $booking->fresh()->status);
    }

    public function test_already_cancelled_returns_idempotent_ok(): void
    {
        $owner = User::factory()->client()->create();
        $booking = Booking::factory()->refuse()->create(['client_id' => $owner->id]);

        $result = $this->tool()->execute($owner, [
            'booking_id' => $booking->id,
            'reason' => 'doublon',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('déjà annulée', $result['message']);
    }

    public function test_completed_booking_cannot_be_cancelled(): void
    {
        $owner = User::factory()->client()->create();
        $booking = Booking::factory()->termine()->create(['client_id' => $owner->id]);

        $result = $this->tool()->execute($owner, [
            'booking_id' => $booking->id,
            'reason' => 'trop tard',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('déjà terminée', $result['error']);
        $this->assertSame('termine', $booking->fresh()->status);
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->confirme()->create();

        $result = $this->tool()->execute($admin, [
            'booking_id' => $booking->id,
            'reason' => 'décision admin',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame($admin->id, $booking->fresh()->cancelled_by);
    }

    public function test_org_member_with_permission_can_cancel(): void
    {
        $org = OrganizationAccount::factory()->create();
        $member = User::factory()->entreprise()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $member->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
        ]);

        $booking = Booking::factory()->confirme()->create([
            'customer_organization_id' => $org->id,
        ]);

        $result = $this->tool()->execute($member, [
            'booking_id' => $booking->id,
            'reason' => 'annulation société',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}
