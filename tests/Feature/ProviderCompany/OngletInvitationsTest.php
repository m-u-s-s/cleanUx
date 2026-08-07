<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\TeamManagement;
use App\Models\OrganizationAccount;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES INVITATIONS EN ATTENTE, QUE PERSONNE NE POUVAIT VOIR.
 *
 * `TeamManagement::$activeTab` déclarait trois onglets — `members | invitations | performance` —
 * depuis l'origine, et la vue n'en rendait qu'un. Une invitation partait donc dans le vide : aucun
 * écran ne disait si elle avait été envoyée, à qui, ni si elle avait expiré. Le seul recours était
 * de réinviter, ce qui n'apprenait rien de plus.
 *
 * C'est le motif dominant de ce dépôt sous une forme de plus : un nom qui ne désigne rien.
 */
class OngletInvitationsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeAvecPatron(): array
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

        return [$org, $patron];
    }

    #[Test]
    public function les_invitations_en_attente_de_la_societe_sont_visibles(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        OrganizationInvitation::create([
            'organization_account_id' => $org->id,
            'email' => 'nouvelle.recrue@example.test',
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => $patron->id,
            'token' => 'jeton-de-test',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->set('activeTab', 'invitations')
            ->assertSee('nouvelle.recrue@example.test');
    }

    #[Test]
    public function l_invitation_d_une_autre_societe_n_apparait_jamais(): void
    {
        [, $patron] = $this->societeAvecPatron();

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        OrganizationInvitation::create([
            'organization_account_id' => $concurrente->id,
            'email' => 'recrue.adverse@example.test',
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => $patron->id,
            'token' => 'jeton-adverse',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->set('activeTab', 'invitations')
            ->assertDontSee('recrue.adverse@example.test');
    }

    #[Test]
    public function une_invitation_se_revoque(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $invitation = OrganizationInvitation::create([
            'organization_account_id' => $org->id,
            'email' => 'erreur.de.frappe@example.test',
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => $patron->id,
            'token' => 'jeton-a-revoquer',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('revoquerInvitation', $invitation->id);

        // Le statut change, la ligne SURVIT : le jeton doit rester connu pour être refusé si
        // quelqu'un l'ouvre après coup. Supprimer rouvrirait le lien à l'inconnu.
        $this->assertSame('revoked', $invitation->fresh()->status);
    }

    #[Test]
    public function on_ne_revoque_pas_l_invitation_d_une_autre_societe(): void
    {
        [, $patron] = $this->societeAvecPatron();

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        $invitation = OrganizationInvitation::create([
            'organization_account_id' => $concurrente->id,
            'email' => 'recrue.adverse@example.test',
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => $patron->id,
            'token' => 'jeton-adverse',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('revoquerInvitation', $invitation->id);

        $this->assertSame('pending', $invitation->fresh()->status);
    }

    #[Test]
    public function revoquer_revoit_la_permission_au_moment_d_agir(): void
    {
        /*
         * LE SCÉNARIO RÉEL, ET LA RAISON DE LA GARDE D'ACTION.
         *
         * `mount()` exige déjà `members.invite` : quelqu'un qui ne l'a pas n'ouvre même pas
         * l'écran. On pourrait en conclure que re-vérifier est superflu — c'est faux, et c'est le
         * défaut corrigé au lot 2A sur l'assignation : LIVEWIRE NE REJOUE PAS `mount()` entre deux
         * actions. Le composant reste vivant dans le navigateur, la permission peut être retirée
         * entre-temps, et sans cette seconde vérification l'écran continuerait d'obéir.
         *
         * On reproduit exactement cela : l'écran s'ouvre avec le droit, la société le retire, et
         * l'action suivante doit refuser.
         */
        [$org, $patron] = $this->societeAvecPatron();

        $responsable = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $responsable->id,
            'role' => OrganizationRole::OPERATIONS_MANAGER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
        ProviderProfile::factory()->create([
            'user_id' => $responsable->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        $invitation = OrganizationInvitation::create([
            'organization_account_id' => $org->id,
            'email' => 'recrue@example.test',
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => $patron->id,
            'token' => 'jeton',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        // L'écran s'ouvre : le responsable d'exploitation a `members.invite` par défaut.
        $composant = Livewire::actingAs($responsable)->test(TeamManagement::class)->assertOk();

        // La société lui retire le droit pendant que l'écran est ouvert.
        OrganizationRolePermission::create([
            'organization_account_id' => $org->id,
            'role' => OrganizationRole::OPERATIONS_MANAGER->value,
            'permission' => 'members.invite',
            'granted' => false,
        ]);
        app(PermissionService::class)->invalidateCache($responsable->id, $org->id);

        $composant->call('revoquerInvitation', $invitation->id)->assertForbidden();

        $this->assertSame('pending', $invitation->fresh()->status);
    }
}
