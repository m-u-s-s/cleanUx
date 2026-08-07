<?php

namespace Tests\Feature\Api;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'ADHÉSION SUFFIT À DÉSIGNER L'ORGANISATION, QUAND ELLE EST SANS AMBIGUÏTÉ.
 *
 * POURQUOI CE FICHIER EXISTE. Relevé sur la base de développement le 2026-08-07, compte
 * `provider@soc.com` :
 *
 *     membre_role='owner'  membre_status='active'
 *     org_account_id=NULL  current_org_id=NULL   →  can_manage_company=false
 *
 * Propriétaire ACTIF d'une société prestataire, et invisible pour tout le code qui résout
 * l'organisation. `organizationContextId()` ne lisait que des colonnes de `users` : deux pointeurs
 * vers une vérité qui vit ailleurs, dans `organization_members`. Quand personne ne les écrit — et
 * aucun seeder ne crée ce compte, il vient du parcours d'inscription — l'utilisateur perd son
 * espace société alors que son appartenance est parfaitement enregistrée.
 *
 * QUATRE COMPTES étaient dans cet état. Corriger la donnée seule aurait laissé le prochain
 * s'ajouter en silence.
 *
 * ON NE DEVINE PAS. Avec PLUSIEURS adhésions actives et aucun choix explicite, le repli rend `null`
 * : placer quelqu'un dans la mauvaise société serait bien pire que lui demander de choisir.
 */
class ContexteOrganisationDepuisAdhesionTest extends TestCase
{
    use RefreshDatabase;

    private function adherer(User $user, OrganizationAccount $org, OrganizationRole $role, string $statut = 'active'): void
    {
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => $statut,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    #[Test]
    public function une_adhesion_unique_suffit_a_resoudre_l_organisation(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        // Les DEUX colonnes vides : la forme exacte de `provider@soc.com`.
        $patron = User::factory()->create([
            'organization_account_id' => null,
            'current_organization_id' => null,
        ]);
        $this->adherer($patron, $org, OrganizationRole::OWNER);

        $this->assertSame($org->id, $patron->fresh()->organizationContextId());
    }

    #[Test]
    public function le_patron_ainsi_retrouve_pilote_bien_sa_societe(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $patron = User::factory()->create([
            'organization_account_id' => null,
            'current_organization_id' => null,
        ]);
        $this->adherer($patron, $org, OrganizationRole::OWNER);

        Sanctum::actingAs($patron, ['*']);

        // Le bout de la chaîne : c'est ce drapeau qui ouvre l'espace société dans l'application.
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('organization_type', 'provider_company')
            ->assertJsonPath('can_manage_company', true);

        $this->getJson('/api/provider/company/overview')->assertOk();
    }

    #[Test]
    public function une_adhesion_suspendue_ne_designe_rien(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $ancien = User::factory()->create([
            'organization_account_id' => null,
            'current_organization_id' => null,
        ]);
        $this->adherer($ancien, $org, OrganizationRole::OWNER, statut: 'suspended');

        $this->assertNull($ancien->fresh()->organizationContextId());
    }

    #[Test]
    public function deux_adhesions_actives_sans_choix_ne_se_devinent_pas(): void
    {
        /*
         * LE REPLI S'ARRÊTE ICI, ET C'EST VOULU.
         *
         * Deux sociétés, aucun choix enregistré : prendre la première par ordre d'identifiant
         * placerait quelqu'un dans la mauvaise entreprise — il y verrait des missions, des membres
         * et une facturation qui ne sont pas les siens. Mieux vaut ne rien rendre : les surfaces
         * répondent alors 403, ce qui se remarque et se corrige, au lieu de servir en silence les
         * données du voisin.
         */
        $premiere = OrganizationAccount::factory()->providerCompany()->create();
        $seconde = OrganizationAccount::factory()->providerCompany()->create();

        $polyvalent = User::factory()->create([
            'organization_account_id' => null,
            'current_organization_id' => null,
        ]);
        $this->adherer($polyvalent, $premiere, OrganizationRole::OWNER);
        $this->adherer($polyvalent, $seconde, OrganizationRole::WORKER);

        $this->assertNull($polyvalent->fresh()->organizationContextId());
    }

    #[Test]
    public function un_choix_explicite_prime_toujours_sur_le_repli(): void
    {
        // Le repli ne doit jamais contredire une décision enregistrée : c'est un filet, pas une
        // autorité.
        $choisie = OrganizationAccount::factory()->providerCompany()->create();
        $autre = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'organization_account_id' => null,
            'current_organization_id' => $choisie->id,
        ]);
        $this->adherer($user, $autre, OrganizationRole::WORKER);

        $this->assertSame($choisie->id, $user->fresh()->organizationContextId());
    }

    #[Test]
    public function un_particulier_sans_adhesion_reste_sans_organisation(): void
    {
        $particulier = User::factory()->create([
            'organization_account_id' => null,
            'current_organization_id' => null,
        ]);

        $this->assertNull($particulier->organizationContextId());
    }
}
