<?php

namespace Tests\Feature\Api\Auth;

use App\Services\KybV2\Contracts\BusinessVerificationProviderContract;
use App\Services\KybV2\VerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Recherche d'entreprise pendant l'inscription. */
class CompanyLookupTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/auth/company-lookup';

    /** Numéro de Proximus : clé de contrôle réelle. */
    private const VALID_NUMBER = 'BE0202239951';

    public function test_it_returns_the_company_found_in_the_registry(): void
    {
        $this->fakeProvider(new VerificationResult(
            success: true,
            provider: 'mock',
            checkType: 'identity',
            matchedValue: '0202239951',
            payload: [
                'legal_name' => 'Proximus SA',
                'legal_form' => 'SA',
                'registered_address' => 'Boulevard du Roi Albert II 27, 1030 Bruxelles',
            ],
        ));

        $this->postJson(self::ROUTE, ['number' => self::VALID_NUMBER])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('company.legal_name', 'Proximus SA')
            ->assertJsonPath('company.address', 'Boulevard du Roi Albert II 27, 1030 Bruxelles');
    }

    /** L'INSEE rend sa réponse brute, là où le simulateur rend des clés déjà normalisées. */
    public function test_it_understands_the_raw_insee_shape(): void
    {
        $this->fakeProvider(new VerificationResult(
            success: true,
            provider: 'insee',
            checkType: 'identity',
            matchedValue: '44306184100047',
            payload: [
                'uniteLegale' => ['denominationUniteLegale' => 'GOOGLE FRANCE'],
                'adresseEtablissement' => [
                    'numeroVoieEtablissement' => '8',
                    'typeVoieEtablissement' => 'RUE',
                    'libelleVoieEtablissement' => 'DE LONDRES',
                    'codePostalEtablissement' => '75009',
                    'libelleCommuneEtablissement' => 'PARIS',
                ],
            ],
        ));

        $this->postJson(self::ROUTE, ['number' => '44306184100047'])
            ->assertOk()
            ->assertJsonPath('company.legal_name', 'GOOGLE FRANCE')
            ->assertJsonPath('company.address', '8 RUE DE LONDRES 75009 PARIS');
    }

    /** Le garde-fou principal : la clé du numéro est contrôlée AVANT tout appel sortant. */
    public function test_an_invalid_number_never_reaches_the_registry(): void
    {
        $called = false;
        $this->fakeProvider(
            new VerificationResult(true, 'mock', 'identity'),
            function () use (&$called) {
                $called = true;
            },
        );

        $this->postJson(self::ROUTE, ['number' => 'BE0000000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('number');

        $this->assertFalse($called, 'aucun appel ne doit partir vers le registre');
    }

    /** Ne pas trouver n'est pas une erreur : l'inscription se poursuit en saisie manuelle. */
    public function test_an_unknown_company_answers_not_found(): void
    {
        $this->fakeProvider(new VerificationResult(false, 'mock', 'identity', error: 'not_found'));

        $this->postJson(self::ROUTE, ['number' => self::VALID_NUMBER])
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('company', null);
    }

    /** Un registre injoignable ne doit pas bloquer une inscription par ailleurs valide. */
    public function test_a_registry_outage_does_not_break_the_request(): void
    {
        $this->fakeProvider(new VerificationResult(false, 'insee', 'identity', error: 'http_503'));

        $this->postJson(self::ROUTE, ['number' => self::VALID_NUMBER])
            ->assertOk()
            ->assertJsonPath('found', false);
    }

    private function fakeProvider(VerificationResult $result, ?\Closure $onCall = null): void
    {
        $this->app->bind(BusinessVerificationProviderContract::class, fn () => new class($result, $onCall) implements BusinessVerificationProviderContract
        {
            public function __construct(private VerificationResult $result, private ?\Closure $onCall) {}

            public function name(): string
            {
                return 'fake';
            }

            public function verifyIdentifier(string $identifierType, string $identifierValue, ?string $countryCode = null): VerificationResult
            {
                ($this->onCall ?? static fn () => null)();

                return $this->result;
            }

            public function verifyVatId(string $vatId, ?string $countryCode = null): VerificationResult
            {
                return $this->result;
            }
        });
    }
}
