<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\PlatformSettlement;
use App\Models\PlatformSettlementAccount;
use App\Models\User;
use App\Services\Payments\PlatformSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Le registre de règlement — où part la commission Brio, par devise. */
class PlatformSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pas d'appel réseau vers Stripe dans les tests : le service retombe proprement sur
        // « lecture indisponible », ce qui est le comportement voulu hors production.
        config(['cashier.secret' => null]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'super_admin']);
    }

    /** Une commission encaissée dans une devise, sans secours : la page doit le crier. */
    public function test_alerte_sur_une_devise_sans_compte_de_secours_verifie(): void
    {
        $this->actingAs($this->superAdmin());

        DB::table('bookings')->insert([
            'booking_reference' => 'CUX-TEST01',
            'status' => 'termine',
            'currency' => 'EUR',
            'platform_fee_cents' => 3770,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(PlatformSettlement::class)
            ->assertSee('Aucun compte de secours vérifié')
            ->assertSee('eur');
    }

    public function test_enregistrer_cree_un_compte_declare(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(PlatformSettlement::class)
            ->set('label', 'BNP Fortis — secours')
            ->set('currency', 'eur')
            ->set('country', 'BE')
            ->set('bank_name', 'BNP Paribas Fortis')
            ->set('iban_last4', '4321')
            ->set('role', PlatformSettlementAccount::ROLE_BACKUP)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $compte = PlatformSettlementAccount::firstOrFail();

        $this->assertSame('eur', $compte->currency);
        $this->assertSame('BE', $compte->country);
        $this->assertSame('4321', $compte->iban_last4);
        // Déclaré, PAS vérifié : la vérification a lieu chez Stripe, on ne fait qu'en prendre acte.
        $this->assertSame(PlatformSettlementAccount::STATUS_DRAFT, $compte->status);
    }

    /** Le champ est trop court pour un IBAN complet, et c'est le but. */
    public function test_un_iban_complet_est_refuse(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(PlatformSettlement::class)
            ->set('label', 'Tentative')
            ->set('currency', 'eur')
            ->set('iban_last4', 'BE68539007547034')
            ->call('enregistrer')
            ->assertHasErrors('iban_last4');

        $this->assertSame(0, PlatformSettlementAccount::query()->count());
    }

    public function test_marquer_verifie_leve_l_alerte_de_la_devise(): void
    {
        $this->actingAs($this->superAdmin());

        $compte = PlatformSettlementAccount::create([
            'label' => 'Secours',
            'currency' => 'eur',
            'role' => PlatformSettlementAccount::ROLE_BACKUP,
            'status' => PlatformSettlementAccount::STATUS_DRAFT,
        ]);

        $this->assertContains('eur', app(PlatformSettlementService::class)->devisesSansSecours());

        Livewire::test(PlatformSettlement::class)
            ->call('marquerVerifie', $compte->id);

        $this->assertNotContains('eur', app(PlatformSettlementService::class)->devisesSansSecours());
        $this->assertNotNull($compte->fresh()->verified_at);
    }

    /** MULTI-PAYS : un secours en euro ne dépanne pas un versement en livre. */
    public function test_un_secours_en_euro_ne_couvre_pas_la_livre(): void
    {
        PlatformSettlementAccount::create([
            'label' => 'Secours euro',
            'currency' => 'eur',
            'role' => PlatformSettlementAccount::ROLE_BACKUP,
            'status' => PlatformSettlementAccount::STATUS_VERIFIED,
        ]);

        DB::table('bookings')->insert([
            'booking_reference' => 'CUX-TEST02',
            'status' => 'termine',
            'currency' => 'GBP',
            'platform_fee_cents' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manquantes = app(PlatformSettlementService::class)->devisesSansSecours();

        $this->assertContains('gbp', $manquantes);
        $this->assertNotContains('eur', $manquantes);
    }

    /** Promouvoir un secours retire l'ancien principal — sans jamais le supprimer. */
    public function test_promouvoir_retire_l_ancien_principal(): void
    {
        $this->actingAs($this->superAdmin());

        $ancien = PlatformSettlementAccount::create([
            'label' => 'Ancienne banque',
            'currency' => 'eur',
            'role' => PlatformSettlementAccount::ROLE_PRIMARY,
            'status' => PlatformSettlementAccount::STATUS_VERIFIED,
        ]);

        $nouveau = PlatformSettlementAccount::create([
            'label' => 'Nouvelle banque',
            'currency' => 'eur',
            'role' => PlatformSettlementAccount::ROLE_BACKUP,
            'status' => PlatformSettlementAccount::STATUS_VERIFIED,
        ]);

        Livewire::test(PlatformSettlement::class)
            ->call('promouvoir', $nouveau->id)
            // Le registre ne doit jamais laisser croire que l'argent a changé de route.
            ->assertSee('Dashboard Stripe');

        $this->assertSame(PlatformSettlementAccount::ROLE_PRIMARY, $nouveau->fresh()->role);
        $this->assertNotNull($nouveau->fresh()->activated_at);

        $this->assertSame(PlatformSettlementAccount::STATUS_RETIRED, $ancien->fresh()->status);
        $this->assertNotNull($ancien->fresh(), 'La ligne retirée doit être conservée pour la traçabilité.');
    }

    public function test_la_commission_est_totalisee_par_devise(): void
    {
        DB::table('bookings')->insert([
            [
                'booking_reference' => 'CUX-A',
                'status' => 'termine',
                'currency' => 'EUR',
                'platform_fee_cents' => 3770,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'booking_reference' => 'CUX-B',
                'status' => 'termine',
                'currency' => 'eur',
                'platform_fee_cents' => 1230,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $commission = app(PlatformSettlementService::class)->commissionEncaissee();

        // 'EUR' et 'eur' sont la MÊME devise : les compter séparément afficherait deux colonnes
        // pour un seul compte bancaire.
        $this->assertSame(50.0, $commission['eur']['montant']);
        $this->assertSame(2, $commission['eur']['missions']);
    }
}
