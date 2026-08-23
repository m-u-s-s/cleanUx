<?php

namespace Tests\Feature\Console;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** DES MEMBRES ACTIFS QUI NE POUVAIENT PAS ENTRER DANS LEUR PROPRE SOCIÉTÉ. */
class BackfillCurrentOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function rattacher(User $user, OrganizationAccount $org, string $statut = 'active'): void
    {
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => $statut,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    #[Test]
    public function le_mode_simulation_ne_modifie_rien(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $orphelin = User::factory()->create(['current_organization_id' => null]);
        $this->rattacher($orphelin, $org);

        $this->artisan('organizations:backfill-current')
            ->expectsOutputToContain('SIMULATION')
            ->assertSuccessful();

        $this->assertNull(
            $orphelin->fresh()->current_organization_id,
            'Sans --apply, la commande doit se contenter de rendre compte.',
        );
    }

    #[Test]
    public function un_membre_d_une_seule_societe_est_rattache(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $orphelin = User::factory()->create(['current_organization_id' => null]);
        $this->rattacher($orphelin, $org);

        $this->artisan('organizations:backfill-current', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame($org->id, $orphelin->fresh()->current_organization_id);
    }

    #[Test]
    public function un_membre_de_plusieurs_societes_est_laisse_intact(): void
    {
        $premiere = OrganizationAccount::factory()->providerCompany()->create();
        $seconde = OrganizationAccount::factory()->clientCompany()->create();

        $ambigu = User::factory()->create(['current_organization_id' => null]);
        $this->rattacher($ambigu, $premiere);
        $this->rattacher($ambigu, $seconde);

        $this->artisan('organizations:backfill-current', ['--apply' => true])
            ->assertSuccessful();

        $this->assertNull(
            $ambigu->fresh()->current_organization_id,
            'Choisir à sa place le placerait dans une société au hasard.',
        );
    }

    #[Test]
    public function une_appartenance_non_active_ne_compte_pas(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $parti = User::factory()->create(['current_organization_id' => null]);
        $this->rattacher($parti, $org, 'left');

        $this->artisan('organizations:backfill-current', ['--apply' => true])
            ->assertSuccessful();

        $this->assertNull(
            $parti->fresh()->current_organization_id,
            "Quelqu'un qui a quitté la société ne doit pas y être réintroduit.",
        );
    }

    #[Test]
    public function une_organisation_deja_definie_n_est_jamais_ecrasee(): void
    {
        $choisie = OrganizationAccount::factory()->providerCompany()->create();
        $autre = OrganizationAccount::factory()->clientCompany()->create();

        $installe = User::factory()->create(['current_organization_id' => $choisie->id]);
        $this->rattacher($installe, $autre);

        $this->artisan('organizations:backfill-current', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame(
            $choisie->id,
            $installe->fresh()->current_organization_id,
            'Le rattrapage ne doit jamais déplacer quelqu\'un qui a déjà une organisation.',
        );
    }

    #[Test]
    public function la_commande_est_rejouable_sans_effet_supplementaire(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $orphelin = User::factory()->create(['current_organization_id' => null]);
        $this->rattacher($orphelin, $org);

        $this->artisan('organizations:backfill-current', ['--apply' => true])->assertSuccessful();
        $this->artisan('organizations:backfill-current', ['--apply' => true])
            ->expectsOutputToContain('0')
            ->assertSuccessful();

        $this->assertSame($org->id, $orphelin->fresh()->current_organization_id);
    }
}
