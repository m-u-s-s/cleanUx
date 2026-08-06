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

/**
 * LE TABLEAU DE BORD PRESTATAIRE TOMBE DÈS QU'UNE MISSION EST PLANIFIÉE AUJOURD'HUI.
 *
 * POURQUOI CE FICHIER EXISTE. `getMissionsOfDayProperty()` chargeait `assignedWorker`, une
 * relation qui N'EXISTE PAS sur `Mission` — le modèle n'expose que `leadProvider()` (le
 * travailleur assigné, via `lead_provider_user_id`) et `assignments()`. La vue la lisait
 * également. Résultat : `RelationNotFoundException` à chaque rendu comportant au moins une
 * mission du jour, donc une page blanche pour toute société prestataire en activité.
 *
 * Aucun test ne montait ce composant : la couverture existante se contentait de requêtes
 * Eloquent directes, qui ne touchent jamais la relation fautive.
 */
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

        /*
         * `ProviderDashboard::mount()` exige `isProviderCompanyWorker()`, donc un ProviderProfile
         * de type `company_worker`. C'est précisément ce que l'invitation d'un employé NE crée
         * pas aujourd'hui : un membre invité ne peut même pas ouvrir cet écran.
         */
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
}
