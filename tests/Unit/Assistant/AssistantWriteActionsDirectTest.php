<?php

namespace Tests\Unit\Assistant;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\ProviderPresence;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\Assistant\Actions\AssistantWriteActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[CoversClass(AssistantWriteActions::class)]
#[Group('requires-mysql')]
class AssistantWriteActionsDirectTest extends TestCase
{
    use RefreshDatabase;

    private AssistantWriteActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new AssistantWriteActions;
    }

    private function validBookingParams(int $catalogId): array
    {
        return [
            'service_catalog_id' => $catalogId,
            'address' => '5 avenue Louise',
            'city' => 'Bruxelles',
            'postal_code' => '1050',
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '14:00',
        ];
    }

    // ──────────────────────────────────────────────────────
    // dispatch — routing
    // ──────────────────────────────────────────────────────

    public function test_dispatch_unknown_action_returns_unknown_message(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->dispatch('does_not_exist', $user, []);

        $this->assertStringContainsString("Action d'écriture inconnue", $result);
        $this->assertStringContainsString('does_not_exist', $result);
    }

    public function test_dispatch_create_booking_creates_record(): void
    {
        $user = User::factory()->client()->create();
        $catalog = ServiceCatalog::factory()->create();

        $result = $this->actions->dispatch('create_booking', $user, $this->validBookingParams($catalog->id));

        $this->assertStringContainsString('créée avec succès', $result);
        $this->assertStringContainsString('Bruxelles', $result);
        $this->assertDatabaseHas('bookings', [
            'customer_user_id' => $user->id,
            'service_catalog_id' => $catalog->id,
            'city' => 'Bruxelles',
            'status' => 'en_attente',
        ]);
    }

    // ──────────────────────────────────────────────────────
    // create_booking
    // ──────────────────────────────────────────────────────

    public function test_create_booking_missing_required_param_returns_error(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->dispatch('create_booking', $user, [
            'service_catalog_id' => 1,
            // address, city, postal_code, scheduled_date, scheduled_time missing
        ]);

        $this->assertStringContainsString('paramètre manquant', $result);
        $this->assertStringContainsString('address', $result);
        $this->assertDatabaseMissing('bookings', ['customer_user_id' => $user->id]);
    }

    public function test_create_booking_generates_reference(): void
    {
        $user = User::factory()->client()->create();
        $catalog = ServiceCatalog::factory()->create();

        $this->actions->dispatch('create_booking', $user, $this->validBookingParams($catalog->id));

        $booking = Booking::where('customer_user_id', $user->id)->first();
        $this->assertNotNull($booking);
        $this->assertStringStartsWith('CUX-', (string) $booking->booking_reference);
    }

    // ──────────────────────────────────────────────────────
    // cancel_booking
    // ──────────────────────────────────────────────────────

    public function test_cancel_booking_missing_id_returns_error(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->dispatch('cancel_booking', $user, []);

        $this->assertStringContainsString('identifiant de réservation manquant', $result);
    }

    public function test_cancel_booking_not_found_returns_error(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->dispatch('cancel_booking', $user, ['booking_id' => 999999]);

        $this->assertStringContainsString('Erreur', $result);
        $this->assertStringContainsString('introuvable', $result);
    }

    public function test_cancel_booking_by_id_succeeds(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id' => $user->id,
            'status' => 'confirme',
            'booking_reference' => 'CUX-CAN001',
        ]);

        $result = $this->actions->dispatch('cancel_booking', $user, ['booking_id' => $booking->id]);

        $this->assertStringContainsString('CUX-CAN001', $result);
        $this->assertStringContainsString('annulée avec succès', $result);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'annule']);
    }

    public function test_cancel_booking_by_reference_succeeds(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id' => $user->id,
            'status' => 'confirme',
            'booking_reference' => 'CUX-REF777',
        ]);

        $result = $this->actions->dispatch('cancel_booking', $user, ['booking_id' => 'CUX-REF777']);

        $this->assertStringContainsString('annulée avec succès', $result);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'annule']);
    }

    public function test_cancel_already_cancelled_booking_returns_error(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id' => $user->id,
            'status' => 'annule',
        ]);

        $result = $this->actions->dispatch('cancel_booking', $user, ['booking_id' => $booking->id]);

        $this->assertStringContainsString('introuvable', $result);
    }

    public function test_cancel_booking_of_another_user_returns_error(): void
    {
        $owner = User::factory()->client()->create();
        $intruder = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id' => $owner->id,
            'status' => 'confirme',
        ]);

        $result = $this->actions->dispatch('cancel_booking', $intruder, ['booking_id' => $booking->id]);

        $this->assertStringContainsString('introuvable', $result);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirme']);
    }

    // ──────────────────────────────────────────────────────
    // resolve_dispute
    // ──────────────────────────────────────────────────────

    public function test_resolve_dispute_missing_params_returns_error(): void
    {
        $admin = User::factory()->admin()->create();

        $result = $this->actions->dispatch('resolve_dispute', $admin, ['dispute_id' => 5]);

        $this->assertStringContainsString('texte de résolution requis', $result);
    }

    public function test_resolve_dispute_not_found_returns_error(): void
    {
        $admin = User::factory()->admin()->create();

        $result = $this->actions->dispatch('resolve_dispute', $admin, [
            'dispute_id' => 888888,
            'resolution' => 'Peu importe.',
        ]);

        $this->assertStringContainsString('introuvable', $result);
    }

    public function test_resolve_dispute_already_resolved_returns_error(): void
    {
        $admin = User::factory()->admin()->create();
        $dispute = ComplaintCase::factory()->create(['status' => ComplaintCase::STATUS_RESOLVED]);

        $result = $this->actions->dispatch('resolve_dispute', $admin, [
            'dispute_id' => $dispute->id,
            'resolution' => 'Déjà traité.',
        ]);

        $this->assertStringContainsString('introuvable', $result);
    }

    public function test_resolve_dispute_succeeds(): void
    {
        $admin = User::factory()->admin()->create();
        $dispute = ComplaintCase::factory()->create([
            'status' => ComplaintCase::STATUS_OPEN,
            'reference' => 'DSP-123456',
        ]);

        $result = $this->actions->dispatch('resolve_dispute', $admin, [
            'dispute_id' => $dispute->id,
            'resolution' => 'Remboursement intégral accordé au client.',
        ]);

        $this->assertStringContainsString('résolu avec succès', $result);
        $this->assertStringContainsString('DSP-123456', $result);
        $this->assertDatabaseHas('complaint_cases', [
            'id' => $dispute->id,
            'status' => ComplaintCase::STATUS_RESOLVED,
            'admin_response' => 'Remboursement intégral accordé au client.',
        ]);
        $this->assertDatabaseHas('dispute_resolutions', [
            'complaint_case_id' => $dispute->id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // update_availability
    // ──────────────────────────────────────────────────────

    public function test_update_availability_invalid_status_returns_error(): void
    {
        $provider = User::factory()->employe()->create();

        $result = $this->actions->dispatch('update_availability', $provider, ['status' => 'sleeping']);

        $this->assertStringContainsString('Statut invalide', $result);
        $this->assertDatabaseMissing('provider_presence', ['provider_user_id' => $provider->id]);
    }

    public function test_update_availability_missing_status_returns_error(): void
    {
        $provider = User::factory()->employe()->create();

        $result = $this->actions->dispatch('update_availability', $provider, []);

        $this->assertStringContainsString('Statut invalide', $result);
    }

    public function test_update_availability_online_sets_presence(): void
    {
        $provider = User::factory()->employe()->create();

        $result = $this->actions->dispatch('update_availability', $provider, ['status' => 'online']);

        $this->assertStringContainsString('en ligne', $result);
        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $provider->id,
            'status' => ProviderPresence::STATUS_ONLINE,
        ]);
    }

    public function test_update_availability_break_then_offline_updates_existing_record(): void
    {
        $provider = User::factory()->employe()->create();

        $breakResult = $this->actions->dispatch('update_availability', $provider, ['status' => 'break']);
        $this->assertStringContainsString('en pause', $breakResult);
        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $provider->id,
            'status' => ProviderPresence::STATUS_ON_BREAK,
        ]);

        $offlineResult = $this->actions->dispatch('update_availability', $provider, ['status' => 'offline']);
        $this->assertStringContainsString('hors ligne', $offlineResult);
        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $provider->id,
            'status' => ProviderPresence::STATUS_OFFLINE,
        ]);
        // updateOrCreate must not duplicate the row.
        $this->assertSame(1, ProviderPresence::where('provider_user_id', $provider->id)->count());
    }

    // ──────────────────────────────────────────────────────
    // buildConfirmationSummary
    // ──────────────────────────────────────────────────────

    public function test_summary_unknown_action_returns_generic_prompt(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->buildConfirmationSummary('mystery', $user, []);

        $this->assertStringContainsString("Confirmer l'action : mystery", $result);
    }

    public function test_summary_create_booking_contains_date_time_city(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->buildConfirmationSummary('create_booking', $user, [
            'scheduled_date' => '2026-09-01',
            'scheduled_time' => '08:30',
            'city' => 'Anvers',
        ]);

        $this->assertStringContainsString('2026-09-01', $result);
        $this->assertStringContainsString('08:30', $result);
        $this->assertStringContainsString('Anvers', $result);
    }

    public function test_summary_create_booking_uses_placeholders_when_empty(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->buildConfirmationSummary('create_booking', $user, []);

        $this->assertStringContainsString('—', $result);
    }

    public function test_summary_cancel_booking_without_id_is_generic(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->buildConfirmationSummary('cancel_booking', $user, []);

        $this->assertSame('Annuler cette réservation ?', $result);
    }

    public function test_summary_cancel_booking_not_found_uses_id_placeholder(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->actions->buildConfirmationSummary('cancel_booking', $user, ['booking_id' => 4242]);

        $this->assertStringContainsString('#4242', $result);
    }

    public function test_summary_cancel_booking_found_includes_reference_and_date(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id' => $user->id,
            'booking_reference' => 'CUX-SUM001',
            'scheduled_date' => '2026-10-05',
            'scheduled_time' => '11:00:00',
        ]);

        $result = $this->actions->buildConfirmationSummary('cancel_booking', $user, ['booking_id' => $booking->id]);

        $this->assertStringContainsString('Annuler la réservation', $result);
        $this->assertStringContainsString('CUX-SUM001', $result);
        $this->assertStringContainsString('2026-10-05', $result);
    }

    public function test_summary_resolve_dispute_truncates_long_resolution(): void
    {
        $user = User::factory()->admin()->create();
        $longText = str_repeat('A', 200);

        $result = $this->actions->buildConfirmationSummary('resolve_dispute', $user, [
            'dispute_id' => 77,
            'resolution' => $longText,
        ]);

        $this->assertStringContainsString('#77', $result);
        $this->assertStringContainsString('…', $result);
    }
}
