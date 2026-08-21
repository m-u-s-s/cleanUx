<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHAQUE MODULE ANNONCÉ EST UNE PORTE QUI S'OUVRE.
 *
 * L'application prestataire ouvre son répertoire de modules en WebView : `ModulesRoute` navigue
 * vers `EmbeddedModule` avec le `path` que le serveur a lui-même annoncé dans `/api/modules`. Une
 * entrée dont le chemin répond 403, 404 ou 500 devient donc, dans l'application, un écran vide ou
 * un message d'erreur — sans que rien côté natif ne puisse le prévoir.
 *
 * Ce test prend le catalogue à la source et pousse chaque porte. Il ne juge pas le contenu : il
 * vérifie qu'aucune entrée du menu ne mène nulle part.
 */
class ModulesAnnoncesSontJoignablesTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): User
    {
        $org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_chaque_module_du_catalogue_prestataire_repond(): void
    {
        $patron = $this->patron();

        $catalogue = $this->actingAs($patron)->getJson('/api/modules');
        $catalogue->assertOk();

        $chemins = [];

        foreach ($catalogue->json('groups') as $groupe) {
            foreach ($groupe['modules'] as $module) {
                $chemins[$module['label']] = $module['path'];
            }
        }

        // Un catalogue vide ferait passer ce test sans rien vérifier.
        $this->assertGreaterThan(10, count($chemins), 'Le catalogue prestataire est anormalement court.');

        $casses = [];

        foreach ($chemins as $libelle => $chemin) {
            $statut = $this->actingAs($patron)->get($chemin.'?embed=1')->getStatusCode();

            // 200 : la page s'ouvre. 302 : elle redirige (onboarding, choix d'espace) — c'est une
            // destination, pas une impasse. Tout le reste est un lien mort dans un menu.
            if (! in_array($statut, [200, 302], true)) {
                $casses[] = $libelle.' ('.$chemin.') → '.$statut;
            }
        }

        $this->assertSame([], $casses, "Des modules annoncés ne s'ouvrent pas :\n".implode("\n", $casses));
    }
}
