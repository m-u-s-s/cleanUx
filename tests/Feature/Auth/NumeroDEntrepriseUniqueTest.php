<?php

namespace Tests\Feature\Auth;

use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** UN NUMÉRO D'ENTREPRISE DÉSIGNE UNE SEULE SOCIÉTÉ — et il est PUBLIC. */
class NumeroDEntrepriseUniqueTest extends TestCase
{
    use RefreshDatabase;

    private const NUMERO = 'BE0202239951';

    public function test_deux_societes_prestataires_ne_partagent_pas_un_numero_sur_l_api(): void
    {
        $this->inscrireSocietePrestataireParLApi('premiere@presta.test', 'Première SRL')->assertCreated();

        $this->inscrireSocietePrestataireParLApi('seconde@presta.test', 'Seconde SRL')
            ->assertStatus(422)
            ->assertJsonValidationErrors('vat_number');

        $this->assertSame(1, OrganizationAccount::where('type', 'provider_company')->count());
    }

    public function test_le_web_refuse_le_meme_numero_deja_pris(): void
    {
        $this->inscrireSocietePrestataireParLApi('premiere@presta.test', 'Première SRL')->assertCreated();

        $this->post('/register', [
            'name' => 'Sosie Web',
            'email' => 'sosie@presta.test',
            'password' => 'MotDePasse1!',
            'password_confirmation' => 'MotDePasse1!',
            'account_type' => 'provider_company',
            'provider_company_name' => 'Sosie Web SRL',
            'tva_number' => self::NUMERO,
        ])->assertSessionHasErrors('tva_number');

        $this->assertNull(User::where('email', 'sosie@presta.test')->first());
    }

    /** La forme n'est pas un contournement : `BE 0202.239.951` est le même numéro. */
    public function test_la_ponctuation_ne_contourne_pas_la_regle(): void
    {
        $this->inscrireSocietePrestataireParLApi('premiere@presta.test', 'Première SRL')->assertCreated();

        $this->postJson('/api/auth/register', $this->corps([
            'email' => 'ponctuation@presta.test',
            'company_name' => 'Ponctuation SRL',
            'vat_number' => 'BE 0202.239.951',
        ]))->assertStatus(422)->assertJsonValidationErrors('vat_number');
    }

    /** LE TÉMOIN QUI DÉLIMITE LA RÈGLE : une même entreprise peut être cliente ET prestataire — une société de nettoyage qui commande aussi du jardinage. */
    public function test_la_meme_entreprise_peut_etre_cliente_et_prestataire(): void
    {
        $this->inscrireSocietePrestataireParLApi('presta@double.test', 'Double Casquette SRL')->assertCreated();

        $this->postJson('/api/auth/register', [
            'name' => 'Double Client',
            'email' => 'client@double.test',
            'password' => 'MotDePasse1!',
            'password_confirmation' => 'MotDePasse1!',
            'accept_terms' => true,
            'client_kind' => 'company',
            'company_name' => 'Double Casquette SRL',
            'vat_number' => self::NUMERO,
        ])->assertCreated();
    }

    /** Un autre numéro passe : la règle ne bloque pas l'inscription d'une société de plus. */
    public function test_un_autre_numero_s_inscrit_normalement(): void
    {
        $this->inscrireSocietePrestataireParLApi('premiere@presta.test', 'Première SRL')->assertCreated();

        $this->postJson('/api/auth/register', $this->corps([
            'email' => 'autre@presta.test',
            'company_name' => 'Autre SRL',
            'vat_number' => 'BE0403170701',
        ]))->assertCreated();
    }

    private function inscrireSocietePrestataireParLApi(string $email, string $societe): TestResponse
    {
        return $this->postJson('/api/auth/register', $this->corps([
            'email' => $email,
            'company_name' => $societe,
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function corps(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Patron',
            'password' => 'MotDePasse1!',
            'password_confirmation' => 'MotDePasse1!',
            'accept_terms' => true,
            'account_type' => 'provider',
            'provider_kind' => 'company',
            'vat_number' => self::NUMERO,
        ], $overrides);
    }
}
