<?php

namespace Tests\Feature\Api\Auth;

use App\Jobs\Kyb\VerifyBusinessEntity;
use App\Models\BusinessEntity;
use App\Models\User;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** L'inscription d'une société ouvre sa vérification d'entreprise. */
class CompanyRegistrationOpensKybTest extends TestCase
{
    use RefreshDatabase;

    /** Numéro belge réel (Proximus) : la clé de contrôle est vérifiée à l'inscription. */
    private const BE_NUMBER = 'BE0202239951';

    /** SIRET réel (Google France). */
    private const FR_SIRET = '44306184100047';

    public function test_registering_a_company_creates_its_business_entity(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Proximus SA',
            'vat_number' => self::BE_NUMBER,
        ]))->assertCreated();

        $entity = BusinessEntity::query()->firstOrFail();
        $this->assertSame('Proximus SA', $entity->legal_name);
        $this->assertSame(User::firstOrFail()->id, $entity->owner_user_id);
        $this->assertSame(BusinessEntity::STATUS_PENDING, $entity->status);
    }

    /** Chaque pays a son vocabulaire d'identifiant, et le module refuse un type qu'il ne déclare pas pour ce pays. */
    public function test_a_belgian_number_is_registered_as_a_kbo(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Proximus SA',
            'vat_number' => self::BE_NUMBER,
        ]))->assertCreated();

        $entity = BusinessEntity::query()->firstOrFail();
        $this->assertSame('BE', $entity->country_code);
        $this->assertSame('kbo', $entity->identifier_type);
        // Le numéro est transmis sans son préfixe pays : c'est la forme qu'attendent les registres.
        $this->assertSame('0202239951', $entity->identifier_value);
    }

    public function test_a_french_siret_is_registered_as_such(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Google France',
            'vat_number' => self::FR_SIRET,
        ]))->assertCreated();

        $entity = BusinessEntity::query()->firstOrFail();
        $this->assertSame('FR', $entity->country_code);
        $this->assertSame('siret', $entity->identifier_type);
    }

    /** Les contrôles sortants ne doivent pas s'exécuter pendant l'inscription. */
    public function test_the_registry_checks_are_queued_not_inline(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Proximus SA',
            'vat_number' => self::BE_NUMBER,
        ]))->assertCreated();

        Queue::assertPushed(VerifyBusinessEntity::class);
    }

    /** Un indépendant n'a pas d'entreprise à vérifier. */
    public function test_an_independent_opens_no_business_verification(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', $this->payload(['provider_kind' => 'independent']))
            ->assertCreated();

        $this->assertSame(0, BusinessEntity::query()->count());
        Queue::assertNotPushed(VerifyBusinessEntity::class);
    }

    /** Une société sans numéro reste possible : elle suivra la voie manuelle. */
    public function test_a_company_without_a_number_still_registers(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Société Sans Numéro',
        ]))->assertCreated();

        $this->assertSame(0, BusinessEntity::query()->count());
    }

    /** Garantie principale : la vérification d'entreprise est un effet de bord. */
    public function test_a_failing_verification_never_breaks_the_registration(): void
    {
        Queue::fake();
        $this->app->bind(BusinessOnboardingService::class, function () {
            throw new \RuntimeException('registre injoignable');
        });

        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Proximus SA',
            'vat_number' => self::BE_NUMBER,
        ]))->assertCreated();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, BusinessEntity::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jean Dupont',
            'email' => 'jean@societe.test',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'accept_terms' => true,
            'account_type' => 'provider',
        ], $overrides);
    }
}
