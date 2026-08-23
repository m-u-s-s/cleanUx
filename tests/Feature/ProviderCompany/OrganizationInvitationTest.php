<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\TeamManagement;
use App\Mail\OrganizationInvitationMail;
use App\Models\OrganizationAccount;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** INVITER UN EMPLOYÉ QUI N'A PAS ENCORE DE COMPTE NE FAISAIT RIEN DU TOUT. */
class OrganizationInvitationTest extends TestCase
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

        return [$org, $patron];
    }

    #[Test]
    public function inviter_un_inconnu_cree_une_invitation_et_envoie_un_email(): void
    {
        Mail::fake();
        [$org, $patron] = $this->societeAvecPatron();

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->set('inviteEmail', 'nouvelle.recrue@example.com')
            ->set('inviteRole', OrganizationRole::WORKER->value)
            ->call('invite')
            ->assertHasNoErrors();

        $invitation = OrganizationInvitation::where('email', 'nouvelle.recrue@example.com')->first();

        $this->assertNotNull($invitation, 'La branche « utilisateur inconnu » ne créait aucune invitation.');
        $this->assertSame($org->id, $invitation->organization_account_id);
        $this->assertSame('pending', $invitation->status);
        $this->assertNotEmpty($invitation->token);

        Mail::assertSent(OrganizationInvitationMail::class);
    }

    #[Test]
    public function accepter_une_invitation_cree_le_membre_et_son_profil_prestataire(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $invitation = OrganizationInvitation::create([
            'organization_account_id' => $org->id,
            'email' => 'recrue@example.com',
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => $patron->id,
            'token' => 'jeton-de-test-123',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $recrue = User::factory()->create(['email' => 'recrue@example.com']);

        $this->actingAs($recrue)
            ->get(route('organization.invitations.accept', $invitation->token))
            ->assertRedirect();

        $this->assertDatabaseHas('organization_members', [
            'organization_account_id' => $org->id,
            'user_id' => $recrue->id,
            'status' => 'active',
        ]);

        // Sans ce profil, la recrue reçoit un 403 sur son propre tableau de bord.
        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $recrue->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        $this->assertSame('accepted', $invitation->fresh()->status);
    }

    #[Test]
    public function inviter_un_utilisateur_existant_lui_cree_un_profil_prestataire(): void
    {
        [$org, $patron] = $this->societeAvecPatron();
        $recrue = User::factory()->create(['email' => 'deja.inscrit@example.com']);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->set('inviteEmail', 'deja.inscrit@example.com')
            ->set('inviteRole', OrganizationRole::WORKER->value)
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $recrue->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);
    }

    #[Test]
    public function on_n_accepte_pas_l_invitation_adressee_a_quelqu_un_d_autre(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $invitation = OrganizationInvitation::create([
            'organization_account_id' => $org->id,
            'email' => 'destinataire@example.com',
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => $patron->id,
            'token' => 'jeton-de-test-456',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $intrus = User::factory()->create(['email' => 'intrus@example.com']);

        $this->actingAs($intrus)
            ->get(route('organization.invitations.accept', $invitation->token))
            ->assertForbidden();

        $this->assertDatabaseMissing('organization_members', [
            'organization_account_id' => $org->id,
            'user_id' => $intrus->id,
        ]);
    }

    #[Test]
    public function une_invitation_expiree_ne_donne_plus_acces(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $invitation = OrganizationInvitation::create([
            'organization_account_id' => $org->id,
            'email' => 'tardif@example.com',
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => $patron->id,
            'token' => 'jeton-perime-789',
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);

        $tardif = User::factory()->create(['email' => 'tardif@example.com']);

        $this->actingAs($tardif)
            ->get(route('organization.invitations.accept', $invitation->token))
            ->assertForbidden();

        $this->assertDatabaseMissing('organization_members', [
            'organization_account_id' => $org->id,
            'user_id' => $tardif->id,
        ]);
    }
}
