<?php

namespace Tests\Feature\Security;

use App\Models\RecurringBookingSeries;
use App\Models\RendezVous;
use Tests\TestCase;

/**
 * L1/L3 — central booking entities must not be wide-open to mass assignment.
 */
class MassAssignmentGuardTest extends TestCase
{
    public function test_rendezvous_blocks_sensitive_mass_assignment(): void
    {
        $rv = (new RendezVous)->fill([
            'status' => 'termine',
            'client_id' => 999,
            'devis_estime' => 9999,
            'payment_status' => 'captured',
            'heure' => '10:00', // harmless field stays assignable
        ]);

        $this->assertNull($rv->status, 'status must not be mass-assignable');
        $this->assertNull($rv->client_id, 'client_id must not be mass-assignable');
        $this->assertNull($rv->devis_estime, 'amounts must not be mass-assignable');
        $this->assertNull($rv->payment_status, 'payment flags must not be mass-assignable');
        $this->assertSame('10:00', $rv->heure, 'non-sensitive fields stay assignable');
    }

    public function test_recurring_series_allows_its_columns_but_not_id(): void
    {
        $series = (new RecurringBookingSeries)->fill([
            'id' => 999,
            'frequency' => 'weekly',
            'customer_user_id' => 5,
            'status' => 'active',
        ]);

        $this->assertNull($series->id, 'id must not be mass-assignable');
        $this->assertSame('weekly', $series->frequency);
        $this->assertSame(5, $series->customer_user_id);
        $this->assertSame('active', $series->status);
    }
}
