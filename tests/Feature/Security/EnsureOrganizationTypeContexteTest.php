<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsureOrganizationType;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** L'organisation active se lit à DEUX endroits dans ce dépôt : `organization_account_id` et `current_organization_id`. */
class EnsureOrganizationTypeContexteTest extends TestCase
{
    use RefreshDatabase;

    private function passe(User $user, string $attendu): bool
    {
        $requete = Request::create('/peu-importe', 'GET');
        $requete->setUserResolver(fn () => $user);

        try {
            (new EnsureOrganizationType)->handle($requete, fn () => response('ok'), $attendu);

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    private function organisation(string $type): OrganizationAccount
    {
        return OrganizationAccount::factory()->create(['type' => $type]);
    }

    /** TÉMOIN POSITIF — le cas déjà couvert doit continuer de passer. */
    public function test_temoin_colonne_current_organization_id(): void
    {
        $org = $this->organisation('provider_company');
        $user = User::factory()->create(['current_organization_id' => $org->id]);

        $this->assertTrue($this->passe($user, 'provider'));
    }

    /** L'autre colonne doit ouvrir la même porte. */
    public function test_la_colonne_organization_account_id_ouvre_aussi(): void
    {
        $org = $this->organisation('provider_company');
        $user = User::factory()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => null,
        ]);

        $this->assertTrue($this->passe($user, 'provider'));
    }

    /** REFUS — le type doit toujours trancher. */
    public function test_une_organisation_cliente_n_ouvre_pas_l_espace_prestataire(): void
    {
        $org = $this->organisation('client_company');
        $user = User::factory()->create(['organization_account_id' => $org->id]);

        $this->assertFalse($this->passe($user, 'provider'));
        $this->assertTrue($this->passe($user, 'client'));
    }

    /** REFUS — sans aucune organisation, rien ne s'ouvre. */
    public function test_sans_organisation_rien_ne_s_ouvre(): void
    {
        $user = User::factory()->create([
            'organization_account_id' => null,
            'current_organization_id' => null,
        ]);

        $this->assertFalse($this->passe($user, 'provider'));
        $this->assertFalse($this->passe($user, 'client'));
    }
}
