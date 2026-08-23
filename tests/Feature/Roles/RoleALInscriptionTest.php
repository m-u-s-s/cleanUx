<?php

namespace Tests\Feature\Roles;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\OrganizationRole;
use App\Enums\Role;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LE RÔLE EST ATTRIBUÉ À L'INSCRIPTION, DES DEUX CÔTÉS. */
class RoleALInscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function inscrireSurLeWeb(array $entrees): User
    {
        return app(CreateNewUser::class)->create([
            'name' => 'Personne test',
            'email' => 'test'.uniqid().'@exemple.be',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
            'terms' => true,
            ...$entrees,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Web
    // ──────────────────────────────────────────────────────

    public function test_web_un_particulier_devient_client_individuelle(): void
    {
        $user = $this->inscrireSurLeWeb(['account_type' => 'client_personal']);

        $this->assertSame(Role::CLIENT_INDIVIDUELLE, $user->fresh()->roleCanonique());
    }

    public function test_web_une_societe_cliente_devient_client_societe_et_proprietaire(): void
    {
        $user = $this->inscrireSurLeWeb([
            'account_type' => 'client_company',
            'company_name' => 'Immeubles Test',
        ]);

        $this->assertSame(Role::CLIENT_SOCIETE, $user->fresh()->roleCanonique());
        // `role` est casté en énumération sur le modèle : on compare des cas, pas des chaînes.
        $this->assertSame(
            OrganizationRole::OWNER,
            OrganizationMember::where('user_id', $user->id)->value('role'),
        );
    }

    public function test_web_un_independant_devient_provider_individuelle(): void
    {
        $user = $this->inscrireSurLeWeb(['account_type' => 'provider_independent']);

        $this->assertSame(Role::PROVIDER_INDIVIDUELLE, $user->fresh()->roleCanonique());
    }

    public function test_web_une_societe_prestataire_devient_provider_societe_et_proprietaire(): void
    {
        $user = $this->inscrireSurLeWeb([
            'account_type' => 'provider_company',
            'provider_company_name' => 'Nettoyage Test',
        ]);

        $this->assertSame(Role::PROVIDER_SOCIETE, $user->fresh()->roleCanonique());
        // `role` est casté en énumération sur le modèle : on compare des cas, pas des chaînes.
        $this->assertSame(
            OrganizationRole::OWNER,
            OrganizationMember::where('user_id', $user->id)->value('role'),
        );
    }

    public function test_web_le_role_plateforme_est_une_valeur_declaree(): void
    {
        // `platform_role` n'a que TROIS valeurs déclarées : `user`, `admin`, `super_admin`.
        $user = $this->inscrireSurLeWeb(['account_type' => 'client_personal']);

        $this->assertContains($user->fresh()->platform_role, ['user', 'admin', 'super_admin']);
    }

    // ──────────────────────────────────────────────────────
    // Mobile
    // ──────────────────────────────────────────────────────

    private function inscrireSurMobile(array $entrees): User
    {
        $email = 'mobile'.uniqid().'@exemple.be';

        $reponse = $this->postJson('/api/auth/register', [
            'name' => 'Personne mobile',
            'email' => $email,
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
            'accept_terms' => true,
            ...$entrees,
        ]);

        $reponse->assertSuccessful();

        return User::where('email', $email)->sole();
    }

    public function test_mobile_un_particulier_devient_client_individuelle(): void
    {
        $user = $this->inscrireSurMobile(['account_type' => 'client']);

        $this->assertSame(Role::CLIENT_INDIVIDUELLE, $user->roleCanonique());
    }

    public function test_mobile_un_independant_devient_provider_individuelle(): void
    {
        $user = $this->inscrireSurMobile([
            'account_type' => 'provider',
            'provider_kind' => 'independent',
        ]);

        $this->assertSame(Role::PROVIDER_INDIVIDUELLE, $user->roleCanonique());
    }

    public function test_mobile_une_societe_prestataire_devient_provider_societe_et_proprietaire(): void
    {
        // LE DÉFAUT QUE CE TEST RÉVÈLE.
        $user = $this->inscrireSurMobile([
            'account_type' => 'provider',
            'provider_kind' => 'company',
            'company_name' => 'Nettoyage Mobile',
        ]);

        $this->assertSame(Role::PROVIDER_SOCIETE, $user->roleCanonique());
        // `role` est casté en énumération sur le modèle : on compare des cas, pas des chaînes.
        $this->assertSame(
            OrganizationRole::OWNER,
            OrganizationMember::where('user_id', $user->id)->value('role'),
        );
    }

    public function test_les_deux_parcours_donnent_le_meme_role_pour_le_meme_choix(): void
    {
        // La garde qui empêche les deux chemins de redivergerr : ils écrivent la même identité.
        $web = $this->inscrireSurLeWeb([
            'account_type' => 'provider_company',
            'provider_company_name' => 'Comparaison Web',
        ]);

        $mobile = $this->inscrireSurMobile([
            'account_type' => 'provider',
            'provider_kind' => 'company',
            'company_name' => 'Comparaison Mobile',
        ]);

        $this->assertSame($web->fresh()->roleCanonique(), $mobile->roleCanonique());
    }
}
