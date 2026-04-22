<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_helper_methods_return_expected_values(): void
    {
        $admin = User::factory()->admin()->create();
        $employe = User::factory()->employe()->create();
        $client = User::factory()->client()->create();
        $entreprise = User::factory()->entreprise()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_admin);
        $this->assertTrue($employe->isEmploye());
        $this->assertTrue($client->isClient());
        $this->assertTrue($entreprise->isClient());
        $this->assertTrue($entreprise->isEntreprise());
    }

    public function test_premium_and_standard_helpers_return_expected_values(): void
    {
        $premium = User::factory()->premiumClient()->create();
        $standard = User::factory()->client()->create();
        $pastDue = User::factory()->client()->create([
            'plan_type' => 'premium',
            'plan_status' => 'past_due',
        ]);

        $this->assertTrue($premium->isPremium());
        $this->assertTrue($premium->canChooseEmployee());
        $this->assertTrue($premium->canViewEmployeeAvailability());

        $this->assertTrue($standard->isStandard());
        $this->assertFalse($standard->isPremium());
        $this->assertFalse($standard->canChooseEmployee());
        $this->assertFalse($standard->canViewEmployeeAvailability());

        $this->assertTrue($pastDue->hasBillingIssue());
    }
}
