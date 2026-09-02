<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\B2BMonthlyInvoicesCenter;
use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA CLOTURE MENSUELLE B2B SE CONDUIT, ELLE NE SE DEVINE PAS.
 *
 * L'ecran offrait un bouton et une liste. On cliquait, on lisait « Aucun rendez-vous facturable »,
 * et rien ne disait POURQUOI : combien la periode contient, lesquels sont bloques par leur statut,
 * lesquels sont deja factures. L'apercu repond a la question AVANT le clic.
 */
class LaClotureMensuelleB2BTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_apercu_annonce_ce_qui_sera_facture(): void
    {
        $societe = OrganizationAccount::factory()->create(['status' => 'active']);
        $this->rendezVous($societe, BookingStatus::TERMINE, 120);
        $this->rendezVous($societe, BookingStatus::CONFIRME, 80);

        Livewire::actingAs($this->admin())->test(B2BMonthlyInvoicesCenter::class)
            ->set('organization_account_id', $societe->id)
            ->set('period_start', now()->startOfMonth()->toDateString())
            ->set('period_end', now()->endOfMonth()->toDateString())
            ->assertSee('2 rendez-vous facturable(s)');
    }

    /**
     * L'APERCU DIT POURQUOI IL N'Y A RIEN — c'est tout l'apport de l'ecran.
     *
     * Un rendez-vous en attente d'approbation n'est pas facturable. L'ecran doit nommer ce statut,
     * pas se contenter d'un « aucun » qui laisse l'administrateur sans recours.
     */
    public function test_l_apercu_nomme_le_statut_qui_bloque(): void
    {
        $societe = OrganizationAccount::factory()->create(['status' => 'active']);
        $this->rendezVous($societe, 'pending_approval', 180);

        Livewire::actingAs($this->admin())->test(B2BMonthlyInvoicesCenter::class)
            ->set('organization_account_id', $societe->id)
            ->set('period_start', now()->startOfMonth()->toDateString())
            ->set('period_end', now()->endOfMonth()->toDateString())
            ->assertSee('Rien à facturer sur cette période.')
            ->assertSee('pending_approval');
    }

    /** TEMOIN — le meme ecran, avec un rendez-vous facturable, n'affiche PAS le message de blocage. */
    public function test_temoin_un_rendez_vous_facturable_efface_le_message_de_blocage(): void
    {
        $societe = OrganizationAccount::factory()->create(['status' => 'active']);
        $this->rendezVous($societe, BookingStatus::TERMINE, 120);

        Livewire::actingAs($this->admin())->test(B2BMonthlyInvoicesCenter::class)
            ->set('organization_account_id', $societe->id)
            ->set('period_start', now()->startOfMonth()->toDateString())
            ->set('period_end', now()->endOfMonth()->toDateString())
            ->assertDontSee('Rien à facturer sur cette période.');
    }

    public function test_la_generation_cree_la_facture_et_la_liste_la_montre(): void
    {
        $societe = OrganizationAccount::factory()->create(['status' => 'active', 'name' => 'Omega SA']);
        $this->rendezVous($societe, BookingStatus::TERMINE, 200);

        Livewire::actingAs($this->admin())->test(B2BMonthlyInvoicesCenter::class)
            ->set('organization_account_id', $societe->id)
            ->set('period_start', now()->startOfMonth()->toDateString())
            ->set('period_end', now()->endOfMonth()->toDateString())
            ->call('generate')
            ->assertHasNoErrors()
            ->assertSee('Omega SA');

        $this->assertDatabaseHas('finance_invoices', [
            'organization_account_id' => $societe->id,
            'invoice_type' => 'b2b_monthly',
            'status' => 'issued',
        ]);
    }

    /** LA CLOTURE EN UN GESTE : chaque societe eligible obtient sa facture. */
    public function test_la_cloture_groupee_facture_chaque_societe_eligible(): void
    {
        $a = OrganizationAccount::factory()->create(['status' => 'active']);
        $b = OrganizationAccount::factory()->create(['status' => 'active']);
        $this->rendezVous($a, BookingStatus::TERMINE, 100);
        $this->rendezVous($b, BookingStatus::TERMINE, 150);

        Livewire::actingAs($this->admin())->test(B2BMonthlyInvoicesCenter::class)
            ->set('period_start', now()->startOfMonth()->toDateString())
            ->set('period_end', now()->endOfMonth()->toDateString())
            ->call('genererPourToutesLesSocietes');

        $this->assertSame(2, FinanceInvoice::query()->where('invoice_type', 'b2b_monthly')->count());
    }

    /**
     * LE DETAIL MONTRE LE CENTRE DE COUT.
     *
     * Le sous-titre de la page le promettait depuis toujours, le service l'ecrivait sur chaque
     * ligne de l'instantane, et aucun ecran ne l'affichait.
     */
    public function test_le_detail_affiche_les_lignes_et_leur_centre_de_cout(): void
    {
        $societe = OrganizationAccount::factory()->create(['status' => 'active']);
        $rdv = $this->rendezVous($societe, BookingStatus::TERMINE, 300);
        // `bookings.cost_center` N'EXISTE PAS : le centre de cout vit dans l'instantane de prix,
        // comme `CreateBookingFromApiAction` le documente deja.
        $rdv->forceFill(['pricing_snapshot' => ['cost_center' => 'CC-MARKETING']])->save();

        $composant = Livewire::actingAs($this->admin())->test(B2BMonthlyInvoicesCenter::class)
            ->set('organization_account_id', $societe->id)
            ->set('period_start', now()->startOfMonth()->toDateString())
            ->set('period_end', now()->endOfMonth()->toDateString())
            ->call('generate');

        $facture = FinanceInvoice::query()->where('invoice_type', 'b2b_monthly')->firstOrFail();

        $composant->call('ouvrirLaFacture', $facture->id)
            ->assertSee('Lignes détaillées')
            ->assertSee('CC-MARKETING');
    }

    public function test_un_paiement_solde_la_facture_et_change_son_statut(): void
    {
        $societe = OrganizationAccount::factory()->create(['status' => 'active']);
        $this->rendezVous($societe, BookingStatus::TERMINE, 100);

        $composant = Livewire::actingAs($this->admin())->test(B2BMonthlyInvoicesCenter::class)
            ->set('organization_account_id', $societe->id)
            ->set('period_start', now()->startOfMonth()->toDateString())
            ->set('period_end', now()->endOfMonth()->toDateString())
            ->call('generate');

        $facture = FinanceInvoice::query()->where('invoice_type', 'b2b_monthly')->firstOrFail();

        $composant->call('ouvrirLePaiement', $facture->id)
            ->set('montantDuPaiement', (string) $facture->total_amount)
            ->call('enregistrerLePaiement')
            ->assertHasNoErrors();

        $facture->refresh();
        $this->assertSame('paid', $facture->status);
        $this->assertSame(0.0, (float) $facture->balance_due);
    }

    /**
     * LA CAPACITE GARDE AUSSI LE COMPOSANT : `module_gate` pose `manage-entreprises` sur la route,
     * mais `/livewire/update` ne rejoue aucun middleware.
     */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $sansCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-calendar'],
        ]);

        Livewire::actingAs($sansCapacite)->test(B2BMonthlyInvoicesCenter::class)
            ->assertForbidden();
    }

    /** TEMOIN — la meme visite avec la capacite aboutit ; sans lui le refus mesurerait une panne. */
    public function test_temoin_un_administrateur_avec_la_capacite_entre(): void
    {
        $avecCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-entreprises'],
        ]);

        Livewire::actingAs($avecCapacite)->test(B2BMonthlyInvoicesCenter::class)
            ->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }

    private function rendezVous(OrganizationAccount $societe, string $statut, float $montant): Booking
    {
        return Booking::factory()->create([
            'organization_account_id' => $societe->id,
            'status' => $statut,
            'date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'devis_estime' => $montant,
        ]);
    }
}
