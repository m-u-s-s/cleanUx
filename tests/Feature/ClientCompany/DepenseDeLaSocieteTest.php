<?php

namespace Tests\Feature\ClientCompany;

use App\Livewire\ClientCompany\ClientCompanyDashboard;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * « DEPENSES MOIS » ANNONCAIT ZERO A UNE SOCIETE QUI FACTURE.
 *
 * Le chiffre valait `0`, avec un commentaire promettant de le brancher un jour. Un zero se
 * lit comme une information — « nous n'avons rien depense » — pas comme un trou. La donnee
 * existait pourtant : le centre de facturation la calcule sur les memes factures.
 *
 * La portee reste celle du centre de facturation, a l'identique : la refaire a la main ici
 * ouvrirait une deuxieme regle d'acces, qui divergerait au premier changement.
 */
class DepenseDeLaSocieteTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $societe;

    private User $responsable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->societe = OrganizationAccount::factory()->create(['status' => 'active']);

        $this->responsable = User::factory()->create([
            'organization_account_id' => $this->societe->id,
            'current_organization_id' => $this->societe->id,
            'role' => User::ROLE_ENTREPRISE,
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $this->societe->id,
            'user_id' => $this->responsable->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    private function facture(float $montant, CarbonInterface $emiseLe): FinanceInvoice
    {
        return FinanceInvoice::factory()->create([
            'client_id' => $this->responsable->id,
            'organization_account_id' => $this->societe->id,
            'total_amount' => $montant,
            'issued_at' => $emiseLe,
        ]);
    }

    public function test_la_depense_du_mois_totalise_les_factures_du_mois(): void
    {
        $this->facture(120.50, now()->startOfMonth()->addDays(2));
        $this->facture(79.50, now()->startOfMonth()->addDays(5));

        $this->actingAs($this->responsable);

        $kpis = Livewire::test(ClientCompanyDashboard::class)->get('kpis');

        $this->assertSame(200.0, $kpis['spend_period']);
    }

    /**
     * TEMOIN — le total n'est pas la somme de TOUTES les factures.
     *
     * Sans ce controle, un calcul qui ignorerait la periode passerait le test precedent : il
     * faut qu'une facture d'un autre mois reste dehors.
     */
    public function test_temoin_une_facture_d_un_autre_mois_reste_dehors(): void
    {
        $this->facture(120.50, now()->startOfMonth()->addDays(2));
        $this->facture(999.0, now()->subMonths(2));

        $this->actingAs($this->responsable);

        $this->assertSame(120.50, Livewire::test(ClientCompanyDashboard::class)->get('kpis')['spend_period']);
    }

    /**
     * TEMOIN POSITIF — sans facture, le chiffre vaut bien zero.
     *
     * C'est le seul cas ou « 0 » dit la verite. Le distinguer du zero code en dur est
     * exactement ce que ce fichier existe pour faire.
     */
    public function test_temoin_sans_facture_la_depense_vaut_zero(): void
    {
        $this->actingAs($this->responsable);

        $this->assertSame(0.0, Livewire::test(ClientCompanyDashboard::class)->get('kpis')['spend_period']);
    }

    /** La facture d'une AUTRE societe ne doit pas entrer dans ce total. */
    public function test_la_facture_d_une_autre_societe_reste_dehors(): void
    {
        $this->facture(120.50, now()->startOfMonth()->addDays(2));

        $autre = OrganizationAccount::factory()->create(['status' => 'active']);
        FinanceInvoice::factory()->create([
            'client_id' => User::factory()->create()->id,
            'organization_account_id' => $autre->id,
            'total_amount' => 5000.0,
            'issued_at' => now(),
        ]);

        $this->actingAs($this->responsable);

        $this->assertSame(120.50, Livewire::test(ClientCompanyDashboard::class)->get('kpis')['spend_period']);
    }
}
