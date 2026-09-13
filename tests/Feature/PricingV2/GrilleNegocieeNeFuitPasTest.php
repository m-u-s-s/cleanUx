<?php

namespace Tests\Feature\PricingV2;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\PriceQuote;
use App\Models\User;
use App\Services\PricingV2\PricingEngine;
use Database\Seeders\PricingV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/api/v2/pricing/quote` EST UNE ROUTE PUBLIQUE, et `__contract_id` y désignait un contrat par son
 * numéro. Itérer 1..N rendait la grille négociée de chaque grand compte, sans aucun compte.
 */
class GrilleNegocieeNeFuitPasTest extends TestCase
{
    use RefreshDatabase;

    private const REMISE = 30.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingV2Seeder::class);
        Config::set('pricing_v2.enabled', true);
    }

    private function contrat(): OrganizationContract
    {
        return OrganizationContract::factory()->create([
            'organization_account_id' => OrganizationAccount::factory()->create()->id,
            'negotiated_discount_percent' => self::REMISE,
        ]);
    }

    private function membreActif(OrganizationContract $contrat): User
    {
        $user = User::factory()->create();

        OrganizationMember::factory()->create([
            'organization_account_id' => $contrat->organization_account_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return $user;
    }

    private function devis(?User $user, OrganizationContract $contrat): PriceQuote
    {
        return app(PricingEngine::class)->quote('cleaning_standard', [
            '__contract_id' => $contrat->id,
        ], $user);
    }

    /**
     * LE TÉMOIN POSITIF. Sans lui, les trois refus ci-dessous passeraient au vert le jour où la
     * remise cesserait de s'appliquer à qui que ce soit.
     */
    #[Test]
    public function un_membre_actif_de_la_societe_obtient_bien_sa_remise(): void
    {
        $contrat = $this->contrat();

        $devis = $this->devis($this->membreActif($contrat), $contrat);

        $this->assertSame(3500, (int) $devis->computed_price_cents, 'base 5000 − 30 %');
        $this->assertContains('contract:discount', array_column((array) $devis->applied_rules, 'code'));
    }

    #[Test]
    public function un_anonyme_n_obtient_pas_la_grille(): void
    {
        $contrat = $this->contrat();

        $devis = $this->devis(null, $contrat);

        $this->assertSame(5000, (int) $devis->computed_price_cents);
        $this->assertNotContains('contract:discount', array_column((array) $devis->applied_rules, 'code'));
    }

    #[Test]
    public function un_compte_etranger_a_la_societe_n_obtient_pas_la_grille(): void
    {
        $contrat = $this->contrat();

        $devis = $this->devis(User::factory()->create(), $contrat);

        $this->assertSame(5000, (int) $devis->computed_price_cents);
        $this->assertNotContains('contract:discount', array_column((array) $devis->applied_rules, 'code'));
    }

    /** Une adhésion révoquée n'est pas une adhésion : c'est le cas que `status` existe pour porter. */
    #[Test]
    public function un_membre_inactif_n_obtient_plus_la_grille(): void
    {
        $contrat = $this->contrat();
        $user = $this->membreActif($contrat);
        OrganizationMember::query()->where('user_id', $user->id)->update(['status' => 'revoked']);

        $devis = $this->devis($user, $contrat);

        $this->assertSame(5000, (int) $devis->computed_price_cents);
    }
}
