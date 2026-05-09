<?php

namespace Tests\Feature\Phase12;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase12Test extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────
    // AUTH
    // ──────────────────────────────────────────────────────

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'jane@test.local',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'       => 'jane@test.local',
            'password'    => 'secret123',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['ok', 'token', 'user' => ['id', 'name', 'email']]);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        User::factory()->create([
            'email'    => 'jane@test.local',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jane@test.local',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'New Client',
            'email'                 => 'new@test.local',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
            'phone'                 => '+32475123456',
            'locale'                => 'fr',
            'accept_terms'          => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'new@test.local']);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@test.local']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Dupe',
            'email'                 => 'taken@test.local',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
            'accept_terms'          => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                         ->postJson('/api/auth/logout');

        $response->assertOk();
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    // ──────────────────────────────────────────────────────
    // PROFILE
    // ──────────────────────────────────────────────────────

    public function test_can_get_own_profile(): void
    {
        $user = User::factory()->create([
            'name'  => 'Alice',
            'email' => 'alice@test.local',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

        $response->assertOk();
        $response->assertJsonPath('user.name', 'Alice');
        $response->assertJsonPath('user.email', 'alice@test.local');
    }

    public function test_can_update_profile_name_and_phone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/profile', [
            'name'  => 'Updated Name',
            'phone' => '+32400000000',
        ]);

        $response->assertOk();
        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('+32400000000', $user->fresh()->phone);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpass123'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/profile', [
            'current_password'      => 'wrong',
            'password'              => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }

    // ──────────────────────────────────────────────────────
    // NOTIFICATIONS
    // ──────────────────────────────────────────────────────

    public function test_can_list_notifications(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create([
            'id'         => Str::uuid()->toString(),
            'type'       => 'TestNotif',
            'data'       => ['title' => 'Test'],
            'read_at'    => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notif = $user->notifications()->create([
            'id'      => Str::uuid()->toString(),
            'type'    => 'TestNotif',
            'data'    => ['x' => 1],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson("/api/notifications/{$notif->id}/read");

        $response->assertOk();
        $this->assertNotNull($notif->fresh()->read_at);
    }

    // ──────────────────────────────────────────────────────
    // CLIENT BOOKINGS
    // ──────────────────────────────────────────────────────

    public function test_can_list_own_bookings(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->makeBooking($user);
        $this->makeBooking($other);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/client/bookings');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_show_own_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson("/api/client/bookings/{$booking->id}");

        $response->assertOk();
        $response->assertJsonPath('data.reference', $booking->booking_reference);
    }

    public function test_cannot_show_others_booking(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $booking = $this->makeBooking($owner);

        $response = $this->actingAs($stranger, 'sanctum')
                         ->getJson("/api/client/bookings/{$booking->id}");

        $response->assertStatus(403);
    }

    public function test_cancel_booking_works(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user, ['status' => 'confirme']);

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson("/api/client/bookings/{$booking->id}/cancel", [
                             'reason' => 'Changement de plan',
                         ]);

        $response->assertOk();
        $this->assertSame('annule', $booking->fresh()->status);
        $this->assertSame('Changement de plan', $booking->fresh()->cancellation_reason);
    }

    public function test_cannot_cancel_completed_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user, ['status' => 'termine']);

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson("/api/client/bookings/{$booking->id}/cancel");

        $response->assertStatus(409);
    }

    public function test_eta_returns_no_mission_when_none(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson("/api/client/bookings/{$booking->id}/eta");

        $response->assertOk();
        $response->assertJsonPath('state', 'no_mission');
    }

    // ──────────────────────────────────────────────────────
    // PROVIDER MISSIONS
    // ──────────────────────────────────────────────────────

    public function test_provider_can_list_active_missions(): void
    {
        $provider = $this->makeProvider();
        $client = User::factory()->create();
        $booking = $this->makeBooking($client);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $provider->id,
            'status'     => 'assigned',
            'planned_start_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($provider, 'sanctum')
                         ->getJson('/api/provider/missions/active');

        $response->assertOk();
        $this->assertSame(1, $response->json('count'));
    }

    public function test_provider_cannot_act_on_unassigned_mission(): void
    {
        $stranger = $this->makeProvider();
        $client = User::factory()->create();
        $booking = $this->makeBooking($client);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status'     => 'assigned',
        ]);

        $response = $this->actingAs($stranger, 'sanctum')
                         ->postJson("/api/provider/missions/{$mission->id}/start");

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    protected function makeBooking(User $user, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_reference' => 'CUX-' . strtoupper(Str::random(6)),
            'customer_user_id'  => $user->id,
            'client_id'         => $user->id,
            'scheduled_date'    => now()->addDay()->toDateString(),
            'scheduled_time'    => '10:00:00',
            'status'            => 'en_attente',
            'currency'          => 'EUR',
            'priority'          => 'normal',
            'booking_mode'      => 'scheduled',
        ], $overrides));
    }

    protected function makeProvider(array $overrides = []): User
    {
        $user = User::factory()->create();
        ProviderProfile::create(array_merge([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ], $overrides));
        return $user->fresh();
    }
}
