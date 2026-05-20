<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Models\User;
use App\Services\Bookings\BookingFavoriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingFavoriteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_booking_captures_snapshot(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'duree_estimee' => 120,
            'devis_estime' => 80.00,
        ]);

        $favorite = app(BookingFavoriteService::class)->createFromBooking($client, $booking, 'Mon nettoyage hebdo');

        $this->assertInstanceOf(BookingFavorite::class, $favorite);
        $this->assertSame('Mon nettoyage hebdo', $favorite->label);
        $this->assertSame($provider->id, (int) $favorite->preferred_provider_user_id);
        $this->assertNotEmpty($favorite->snapshot);
        $this->assertSame(120, (int) ($favorite->snapshot['duree_estimee'] ?? 0));
    }

    public function test_create_rejects_other_users_booking(): void
    {
        $client = User::factory()->client()->create();
        $otherClient = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $otherClient->id]);

        $this->expectException(ValidationException::class);
        app(BookingFavoriteService::class)->createFromBooking($client, $booking);
    }

    public function test_create_idempotent_returns_existing(): void
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        $fav1 = app(BookingFavoriteService::class)->createFromBooking($client, $booking);
        $fav2 = app(BookingFavoriteService::class)->createFromBooking($client, $booking, 'New label');

        $this->assertSame($fav1->id, $fav2->id);
    }

    public function test_mark_used_increments_counter(): void
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);
        $service = app(BookingFavoriteService::class);

        $favorite = $service->createFromBooking($client, $booking);
        $this->assertSame(0, (int) $favorite->use_count);

        $service->markUsed($favorite);
        $this->assertSame(1, (int) $favorite->fresh()->use_count);
        $this->assertNotNull($favorite->fresh()->last_used_at);
    }
}
