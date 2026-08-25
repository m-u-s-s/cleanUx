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
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA COURBE D'ACTIVITÉ DE LA SOCIÉTÉ PRESTATAIRE.
 *
 * Ce tableau de bord n'avait que des compteurs — « 3 aujourd'hui », « 12 actives ». Aucun ne
 * dit si l'activité monte ou descend, et c'est pourtant la seule question qu'un patron se
 * pose devant cet écran.
 *
 * CE QUI SE VÉRIFIE ICI EST D'ABORD UNE QUESTION DE PÉRIMÈTRE. La série passe par
 * `missionsVisibles()`, qui filtre déjà sur l'organisation et sur le droit `missions.view_all`.
 * Reconstruire la requête à la main aurait rouvert le périmètre — c'est exactement ainsi
 * qu'une fuite entre sociétés s'installe, et ce dépôt en a déjà connu une.
 */
class ActiviteParJourTest extends TestCase
{
    use RefreshDatabase;

    private function patron(OrganizationAccount $org): User
    {
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

        return $patron;
    }

    private function missionTerminee(OrganizationAccount $org, Carbon $fin): Mission
    {
        return Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'completed',
            'provider_organization_id' => $org->id,
            'lead_provider_user_id' => User::factory()->create()->id,
            'planned_start_at' => $fin->copy()->subHours(2),
            'actual_end_at' => $fin,
        ]);
    }

    public function test_la_serie_couvre_quatorze_jours(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->patron($org);
        $this->missionTerminee($org, now()->copy()->subHours(3));

        $serie = Livewire::actingAs($patron)->test(ProviderDashboard::class)->instance()->activiteParJour;

        $this->assertCount(14, $serie);
    }

    /**
     * LES JOURS SANS MISSION VALENT ZÉRO.
     *
     * Une série qui saute les jours vides trace une pente continue entre deux points et ment
     * sur ce qui s'est passé entre les deux.
     */
    public function test_un_jour_sans_mission_vaut_zero_et_ne_disparait_pas(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->patron($org);

        $this->missionTerminee($org, now()->copy()->subDays(13)->setTime(10, 0));

        $composant = Livewire::actingAs($patron)->test(ProviderDashboard::class)->instance();
        $totaux = array_column($composant->activiteParJour, 'total');

        $this->assertCount(14, $totaux);
        $this->assertSame(1, $totaux[0]);
        $this->assertSame(0, array_sum(array_slice($totaux, 1)));
    }

    /** TÉMOIN — l'activité d'une AUTRE société ne fuite pas dans la série. */
    public function test_l_activite_d_une_autre_societe_ne_fuite_pas(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $autre = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->patron($org);

        $this->missionTerminee($autre, now()->copy()->subHours(3));

        $composant = Livewire::actingAs($patron)->test(ProviderDashboard::class)->instance();

        $this->assertSame(0, $composant->totalActivite);
    }

    /** TÉMOIN — une mission NON terminée ne compte pas comme activité réalisée. */
    public function test_une_mission_en_cours_ne_compte_pas(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->patron($org);

        Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'in_progress',
            'provider_organization_id' => $org->id,
            'lead_provider_user_id' => User::factory()->create()->id,
            'planned_start_at' => now(),
            'actual_end_at' => null,
        ]);

        $composant = Livewire::actingAs($patron)->test(ProviderDashboard::class)->instance();

        $this->assertSame(0, $composant->totalActivite);
    }

    /** Le total suit la série : c'est lui que la vue lit pour décider d'afficher le bloc. */
    public function test_le_total_suit_la_serie(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->patron($org);

        $this->missionTerminee($org, now()->copy()->subDays(2)->setTime(9, 0));
        $this->missionTerminee($org, now()->copy()->subDays(2)->setTime(14, 0));

        $composant = Livewire::actingAs($patron)->test(ProviderDashboard::class)->instance();

        $this->assertSame(2, $composant->totalActivite);
        $this->assertSame(2, array_sum(array_column($composant->activiteParJour, 'total')));
    }
}
