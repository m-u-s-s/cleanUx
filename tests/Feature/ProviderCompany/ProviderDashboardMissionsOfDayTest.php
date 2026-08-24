<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\ProviderDashboard;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LE TABLEAU DE BORD PRESTATAIRE TOMBE DÈS QU'UNE MISSION EST PLANIFIÉE AUJOURD'HUI. */
class ProviderDashboardMissionsOfDayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_tableau_de_bord_supporte_une_mission_planifiee_aujourd_hui(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $patron = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $patron->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        // `ProviderDashboard::mount()` exige `isProviderCompanyWorker()`, donc un ProviderProfile de type `company_worker`.
        ProviderProfile::factory()->create([
            'user_id' => $patron->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        $ouvrier = User::factory()->create();

        Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'lead_provider_user_id' => $ouvrier->id,
            'planned_start_at' => now(),
        ]);

        Livewire::actingAs($patron)
            ->test(ProviderDashboard::class)
            ->assertOk()
            ->assertSee($ouvrier->name);
    }

    /**
     * L'HEURE AFFICHEE EST CELLE DE LA MISSION, PAS CELLE DE L'HORLOGE.
     *
     * La vue lisait `$mission->scheduled_at` — une colonne de `bookings`, absente de `missions`.
     * `Carbon::parse(null)` rend l'instant present : chaque ligne affichait l'heure courante, une
     * heure plausible et fausse. Le test ci-dessus posait `planned_start_at => now()` et ne
     * pouvait donc PAS faire la difference.
     */
    #[Test]
    public function l_heure_affichee_est_celle_de_la_mission(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $patron = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $patron->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $patron->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        // Une heure du jour volontairement ELOIGNEE de l'horloge : sans cet ecart, un gabarit qui
        // afficherait `now()` passerait pour correct.
        $planifiee = today()->setTime(now()->hour === 6 ? 18 : 6, 45);

        Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'lead_provider_user_id' => User::factory()->create()->id,
            'planned_start_at' => $planifiee,
        ]);

        Livewire::actingAs($patron)
            ->test(ProviderDashboard::class)
            ->assertOk()
            ->assertSee($planifiee->format('H:i'))
            ->assertDontSee(now()->format('H:i'));
    }
}
