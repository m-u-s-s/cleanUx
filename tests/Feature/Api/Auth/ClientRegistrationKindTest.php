<?php

namespace Tests\Feature\Api\Auth;

use App\Enums\CustomerType;
use App\Enums\OrganizationType;
use App\Models\CustomerProfile;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Particulier ou société : deux inscriptions clientes distinctes.
 *
 * L'inscription mobile ne créait AUCUN CustomerProfile, là où la voie web en crée un
 * systématiquement. Ce profil est pourtant lu par le moteur de sélection de prestataire, la prise
 * de rendez-vous et l'identité renvoyée par /auth/me : un client inscrit depuis l'application en
 * était dépourvu, sans que rien ne le signale.
 *
 * Et une société cliente n'avait aucun moyen de s'inscrire depuis le mobile, alors que
 * `client_company` existe, est employé en base, et porte le multi-sites comme les contrats B2B.
 */
class ClientRegistrationKindTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_individual_client_gets_a_personal_profile(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['client_kind' => 'individual']))
            ->assertCreated();

        $user = User::firstOrFail();
        $profile = CustomerProfile::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(CustomerType::PERSONAL, $profile->customer_type);
        $this->assertNull($user->organization_account_id);
    }

    /** L'app cliente actuelle n'envoie pas encore ce champ : le défaut reste le particulier. */
    public function test_omitting_the_kind_falls_back_to_an_individual(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $profile = CustomerProfile::where('user_id', User::firstOrFail()->id)->firstOrFail();
        $this->assertSame(CustomerType::PERSONAL, $profile->customer_type);
    }

    public function test_a_company_client_gets_its_organization(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'client_kind' => 'company',
            'company_name' => 'Bureau Dupont SPRL',
            'vat_number' => 'BE0202239951',
        ]))->assertCreated();

        $user = User::firstOrFail();
        $organization = OrganizationAccount::firstOrFail();

        $this->assertSame(OrganizationType::CLIENT_COMPANY->value, $organization->type);
        $this->assertSame('Bureau Dupont SPRL', $organization->name);
        $this->assertSame('BE0202239951', $organization->tva_number);
        $this->assertSame($organization->id, $user->organization_account_id);
    }

    /** Le signataire doit être propriétaire, sans quoi il ne peut rien piloter de sa société. */
    public function test_the_signatory_owns_the_company(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'client_kind' => 'company',
            'company_name' => 'Bureau Dupont SPRL',
        ]))->assertCreated();

        $this->assertDatabaseHas('organization_members', [
            'user_id' => User::firstOrFail()->id,
            'organization_account_id' => OrganizationAccount::firstOrFail()->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    /**
     * Une société cliente est active d'emblée, contrairement à une société prestataire : rien
     * n'est à vérifier avant qu'elle commande un service, et l'y contraindre bloquerait une
     * cliente légitime.
     */
    public function test_a_client_company_is_active_immediately(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'client_kind' => 'company',
            'company_name' => 'Bureau Dupont SPRL',
        ]))->assertCreated();

        $this->assertSame('active', OrganizationAccount::firstOrFail()->status);
    }

    public function test_a_company_without_a_name_is_rejected(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['client_kind' => 'company']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_name');

        $this->assertSame(0, User::count());
    }

    /** Le numéro d'entreprise reste contrôlé, comme pour une société prestataire. */
    public function test_a_bogus_business_number_is_rejected(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'client_kind' => 'company',
            'company_name' => 'Bureau Dupont SPRL',
            'vat_number' => 'BE0000000000',
        ]))->assertStatus(422)->assertJsonValidationErrors('vat_number');
    }

    /** Un prestataire ne doit surtout pas repartir avec une identité cliente. */
    public function test_a_provider_registration_creates_no_customer_profile(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'account_type' => 'provider',
            'provider_kind' => 'independent',
        ]))->assertCreated();

        $this->assertSame(0, CustomerProfile::count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jean Client',
            'email' => 'jean@client.test',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'accept_terms' => true,
        ], $overrides);
    }
}
