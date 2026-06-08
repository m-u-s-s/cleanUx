<?php

namespace Tests\Feature\Security;

use App\Models\RecurringBookingSeries;
use App\Models\RendezVous;
use App\Models\User;
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

    public function test_user_tenant_id_is_not_mass_assignable(): void
    {
        // M7 — dead tenant_id column must no longer be mass-assignable.
        $user = (new User)->fill(['name' => 'X', 'tenant_id' => 99]);

        $this->assertNull($user->tenant_id);
        $this->assertSame('X', $user->name);
    }
}
