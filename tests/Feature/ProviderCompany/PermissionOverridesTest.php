<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\TeamManagement;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES DÉROGATIONS DE PERMISSION : POUVOIR LES RETIRER, ET SAVOIR QUI LES A POSÉES.
 *
 * `togglePermission()` écrit une dérogation NOMINATIVE dans `organization_members.permissions`
 * (étage 1 de la résolution, prioritaire sur la matrice de la société puis sur le défaut du dépôt).
 * Deux manques :
 *
 *   1. AUCUN RETOUR EN ARRIÈRE. Le seul geste disponible était d'inverser le booléen, ce qui écrit
 *      une SECONDE dérogation — l'inverse — au lieu d'effacer la première. Un membre remis « comme
 *      les autres » gardait donc une ligne figée : changer le rôle de la société ne le suivait
 *      plus, et personne ne pouvait le deviner en lisant la matrice.
 *
 *   2. AUCUNE TRACE. Distribuer des droits est l'action la plus sensible de cet écran, et rien ne
 *      l'enregistrait. Le module Audit v2 et son trait `AuditsEloquentEvents` existent depuis
 *      2026-05-19 ; `OrganizationMember` ne l'utilisait pas.
 */
class PermissionOverridesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User, 2: OrganizationMember} */
    private function societeAvecPatronEtEmploye(): array
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

        $employe = User::factory()->create();
        $membre = OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $employe->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$org, $patron, $membre];
    }

    #[Test]
    public function le_patron_efface_une_derogation_au_lieu_d_en_ecrire_l_inverse(): void
    {
        [, $patron, $membre] = $this->societeAvecPatronEtEmploye();

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('openPermissions', $membre->id)
            ->call('togglePermission', 'missions.assign', true)
            ->call('resetPermissions');

        $membre->refresh();

        // VIDE, et non `['missions.assign' => false]`. La nuance est tout le sujet : une clé à
        // `false` reste une dérogation, qui continue d'ignorer la matrice de la société.
        $this->assertSame([], $membre->permissions ?? []);
    }

    #[Test]
    public function effacer_les_derogations_exige_le_droit_de_les_distribuer(): void
    {
        [$org, , $membre] = $this->societeAvecPatronEtEmploye();

        // Un gestionnaire peut inviter, pas distribuer les droits : `members.manage_permissions`
        // est réservée au propriétaire, précisément pour qu'inviter ne devienne pas tout pouvoir.
        $gestionnaire = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $gestionnaire->id,
            'role' => OrganizationRole::OPERATIONS_MANAGER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
        ProviderProfile::factory()->create([
            'user_id' => $gestionnaire->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        $membre->update(['permissions' => ['missions.assign' => true]]);

        Livewire::actingAs($gestionnaire)
            ->test(TeamManagement::class)
            ->call('openPermissions', $membre->id)
            ->call('resetPermissions');

        $membre->refresh();

        $this->assertSame(['missions.assign' => true], $membre->permissions);
    }

    #[Test]
    public function une_derogation_d_une_autre_societe_ne_s_efface_pas(): void
    {
        [, $patron] = $this->societeAvecPatronEtEmploye();

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $etranger = User::factory()->create();
        $membreEtranger = OrganizationMember::create([
            'organization_account_id' => $autreOrg->id,
            'user_id' => $etranger->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'permissions' => ['missions.assign' => true],
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            // `editingMemberId` est une propriété PUBLIQUE : la poser ne demande pas de cliquer.
            ->set('editingMemberId', $membreEtranger->id)
            ->call('resetPermissions');

        $membreEtranger->refresh();

        $this->assertSame(['missions.assign' => true], $membreEtranger->permissions);
    }

    #[Test]
    public function distribuer_un_droit_laisse_une_trace_auditable(): void
    {
        [, $patron, $membre] = $this->societeAvecPatronEtEmploye();

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('openPermissions', $membre->id)
            ->call('togglePermission', 'missions.assign', true);

        // Le geste le plus sensible de l'écran ne laissait rien derrière lui. Le module Audit v2
        // existait pourtant depuis 2026-05-19.
        //
        // Le domaine est `security` et non le nom du modèle : le rôle et les dérogations relèvent
        // du contrôle d'accès, ce qui gouverne leur rétention et qui peut les relire.
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'security.updated',
            'domain' => 'security',
            'subject_id' => $membre->id,
        ]);
    }

    #[Test]
    public function changer_un_role_laisse_aussi_une_trace(): void
    {
        [, $patron, $membre] = $this->societeAvecPatronEtEmploye();

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('changeRole', $membre->id, OrganizationRole::TEAM_LEAD->value);

        $evenement = DB::table('audit_events')
            ->where('event_type', 'security.updated')
            ->where('subject_id', $membre->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($evenement, 'Un changement de rôle doit laisser une trace.');

        // Le CONTENU compte autant que l'existence : une trace qui ne dit pas ce qui a changé ne
        // permet pas de répondre à « qui a promu cette personne, et depuis quel rôle ».
        $contexte = json_decode((string) ($evenement->context_redacted ?? $evenement->context), true);
        $this->assertArrayHasKey('role', $contexte['changes'] ?? []);
    }
}
