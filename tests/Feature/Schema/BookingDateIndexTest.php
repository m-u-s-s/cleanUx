<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M20 — bookings.date access paths must be indexed.
 */
class BookingDateIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookings_has_date_composite_indexes(): void
    {
        $names = collect(Schema::getIndexes('bookings'))->pluck('name')->all();

        $this->assertContains('bookings_employe_id_date_index', $names);
        $this->assertContains('bookings_client_id_date_index', $names);
    }
}
