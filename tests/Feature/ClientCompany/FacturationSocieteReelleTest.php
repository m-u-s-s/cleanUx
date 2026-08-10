<?php

namespace Tests\Feature\ClientCompany;

use App\Livewire\ClientCompany\BillingCenter;
use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LA FACTURATION DE LA SOCIÉTÉ CLIENTE AFFICHE CE QUE LA SOCIÉTÉ DOIT VRAIMENT.
 *
 * L'écran web annonçait quatre zéros écrits en dur, avec un commentaire « données simulées — à
 * connecter à Invoice model ». L'application mobile, elle, lisait les VRAIES factures du même
 * compte par `ClientFinanceDocumentScope`. Une entreprise consultait donc son solde sur son
 * téléphone et voyait 0,00 € sur son ordinateur.
 *
 * CE TEST MESURE L'ÉGALITÉ ENTRE LES DEUX SURFACES, pas seulement la présence de chiffres : c'est
 * la seule assertion qu'un écran re-débranché ferait tomber. Vérifier « le total est supérieur à
 * zéro » laisserait passer un web qui recalculerait le montant à sa façon.
 *
 * L'ISOLATION EST VÉRIFIÉE AUSSI, parce qu'un écran de facturation qui fuit est pire qu'un écran
 * vide : la facture d'une autre société ne doit apparaître ni dans le total, ni dans la liste.
 */
class FacturationSocieteReelleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: OrganizationAccount} */
    private function responsableFinance(): array
    {
        $organisation = OrganizationAccount::factory()->create();

        $user = User::factory()->create([
            'organization_account_id' => $organisation->id,
            'current_organization_id' => $organisation->id,
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $organisation];
    }

    private function facture(OrganizationAccount $organisation, User $client, array $attributs = []): FinanceInvoice
    {
        $site = OrganizationSite::factory()->create(['organization_account_id' => $organisation->id]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'organization_account_id' => $organisation->id,
            'organization_site_id' => $site->id,
        ]);

        return FinanceInvoice::factory()->create(array_merge([
            'organization_account_id' => $organisation->id,
            'client_id' => $client->id,
            'rendez_vous_id' => $booking->id,
            'issued_at' => now()->startOfMonth()->addDay(),
            'total_amount' => 120.00,
            'balance_due' => 120.00,
            'status' => 'pending',
        ], $attributs));
    }

    #[Test]
    public function le_total_du_mois_vient_des_vraies_factures(): void
    {
        [$user, $organisation] = $this->responsableFinance();

        $this->facture($organisation, $user, ['total_amount' => 100.00, 'balance_due' => 100.00]);
        $this->facture($organisation, $user, ['total_amount' => 50.50, 'balance_due' => 0.00, 'status' => 'paid']);

        Livewire::actingAs($user)
            ->test(BillingCenter::class)
            ->assertSet('filterPeriod', 'month')
            // `number_format` sans séparateurs personnalisés : la vue écrit « 150.50 € ». On mesure
            // ce qui s'affiche, pas ce qu'on aimerait voir s'afficher.
            ->assertSee('150.50');
    }

    #[Test]
    public function l_impaye_du_web_egale_celui_de_l_api_mobile(): void
    {
        [$user, $organisation] = $this->responsableFinance();

        $this->facture($organisation, $user, ['total_amount' => 100.00, 'balance_due' => 40.00, 'status' => 'partial']);
        $this->facture($organisation, $user, ['total_amount' => 80.00, 'balance_due' => 80.00, 'status' => 'overdue']);

        $reponse = $this->actingAs($user, 'sanctum')->getJson('/api/client/invoices/summary');
        $reponse->assertOk();

        $impayeMobile = (float) $reponse->json('summary.outstanding_total');

        $composant = Livewire::actingAs($user)->test(BillingCenter::class);
        $impayeWeb = (float) $composant->instance()->summary['unpaid'];

        // C'est l'assertion qui compte : les deux surfaces lisent la même source, donc elles ne
        // peuvent pas diverger. Une seule des deux rebranchée ailleurs ferait tomber ce test.
        $this->assertSame($impayeMobile, $impayeWeb);
        $this->assertSame(120.0, $impayeWeb);
    }

    #[Test]
    public function la_facture_d_une_autre_societe_reste_invisible(): void
    {
        [$user, $organisation] = $this->responsableFinance();
        [$voisin, $autreOrganisation] = $this->responsableFinance();

        $this->facture($organisation, $user, ['total_amount' => 10.00, 'balance_due' => 10.00]);
        $this->facture($autreOrganisation, $voisin, ['total_amount' => 999.00, 'balance_due' => 999.00]);

        $composant = Livewire::actingAs($user)->test(BillingCenter::class);

        // Un écran de facturation qui fuit est pire qu'un écran vide : il expose le chiffre
        // d'affaires d'un concurrent.
        $this->assertSame(10.0, (float) $composant->instance()->summary['unpaid']);
        $this->assertSame(1, $composant->instance()->summary['count_month']);
    }

    #[Test]
    public function la_liste_montre_le_numero_et_le_site_reels(): void
    {
        [$user, $organisation] = $this->responsableFinance();

        $facture = $this->facture($organisation, $user, ['invoice_number' => 'FAC-2026-0042']);
        $nomDuSite = $facture->rendezVous?->organizationSite?->name;

        // La vue lisait `$invoice->reference` et `$invoice->site` — deux champs qui n'ont jamais
        // existé sur ce modèle, donc deux colonnes vides même une fois les données branchées.
        Livewire::actingAs($user)
            ->test(BillingCenter::class)
            ->assertSee('FAC-2026-0042')
            ->assertSee($nomDuSite);
    }

    #[Test]
    public function le_bouton_pdf_ne_leve_plus(): void
    {
        [$user, $organisation] = $this->responsableFinance();
        $facture = $this->facture($organisation, $user);

        // `downloadInvoice()` était appelée par la vue sans exister sur le composant : le bouton
        // levait une erreur Livewire à chaque clic.
        Livewire::actingAs($user)
            ->test(BillingCenter::class)
            ->call('downloadInvoice', $facture->id)
            ->assertHasNoErrors();
    }

    #[Test]
    public function on_ne_telecharge_pas_la_facture_d_autrui(): void
    {
        [$user] = $this->responsableFinance();
        [$voisin, $autreOrganisation] = $this->responsableFinance();

        $facture = $this->facture($autreOrganisation, $voisin);

        Livewire::actingAs($user)
            ->test(BillingCenter::class)
            ->call('downloadInvoice', $facture->id)
            ->assertDispatched('toast');
    }
}
