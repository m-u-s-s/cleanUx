<?php

namespace Tests\Feature\Schema;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** M8 — the tranche column is renamed surface -> surface_range, but the legacy ->surface accessor/mutator keeps working transparently (so views/forms/estimator are unaffected). */
class BookingSurfaceRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_column_is_renamed_but_surface_alias_still_works(): void
    {
        $this->assertTrue(Schema::hasColumn('bookings', 'surface_range'));
        $this->assertFalse(Schema::hasColumn('bookings', 'surface'));

        $client = User::factory()->client()->create();

        // Write via the legacy ->surface alias (mutator -> surface_range column).
        $booking = Booking::create([
            'client_id' => $client->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'confirme',
            'surface' => '50_100',
        ]);

        // Read back via the alias.
        $this->assertSame('50_100', $booking->fresh()->surface);
        // And via the real column name.
        $this->assertSame('50_100', $booking->fresh()->surface_range);
        // Stored in the renamed column.
        $this->assertSame('50_100', DB::table('bookings')->where('id', $booking->id)->value('surface_range'));
    }
}
