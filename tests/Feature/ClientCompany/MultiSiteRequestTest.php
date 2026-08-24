<?php

namespace Tests\Feature\ClientCompany;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bookings\MultiSiteRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** UNE DEMANDE POUR PLUSIEURS SITES, EN UN SEUL GESTE. POURQUOI CE FICHIER EXISTE. */
class MultiSiteRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User, 2: Trade} */
    private function societeCliente(): array
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $demandeur = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        return [$org, $demandeur, Trade::factory()->create()];
    }

    private function site(OrganizationAccount $org, string $code): OrganizationSite
    {
        return OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'site_code' => $code,
        ]);
    }

    #[Test]
    public function une_demande_multi_sites_cree_une_reservation_par_site(): void
    {
        [$org, $demandeur, $trade] = $this->societeCliente();

        $sites = [
            $this->site($org, 'PARIS-01'),
            $this->site($org, 'PARIS-02'),
            $this->site($org, 'LYON-01'),
        ];

        $demande = app(MultiSiteRequestService::class)->creer(
            $demandeur,
            $org,
            $trade,
            collect($sites)->pluck('id')->all(),
            now()->addWeek(),
            ['estimated_duration_minutes' => 120],
        );

        $this->assertNotNull($demande, 'Aucune demande mère créée.');

        $filles = Booking::where('parent_booking_id', $demande->id)->get();

        $this->assertCount(3, $filles, 'Il faut une réservation par site.');
        $this->assertEqualsCanonicalizing(
            collect($sites)->pluck('id')->all(),
            $filles->pluck('organization_site_id')->all(),
            'Chaque site doit porter sa propre réservation.',
        );
    }

    #[Test]
    public function la_demande_mere_expose_ses_filles(): void
    {
        [$org, $demandeur, $trade] = $this->societeCliente();

        $sites = [$this->site($org, 'A'), $this->site($org, 'B')];

        $demande = app(MultiSiteRequestService::class)->creer(
            $demandeur,
            $org,
            $trade,
            collect($sites)->pluck('id')->all(),
            now()->addWeek(),
        );

        // La relation manquait sur le modèle : le lien existait en base sans être lisible.
        $this->assertCount(2, $demande->childBookings);
        $this->assertSame($demande->id, $demande->childBookings->first()->parentBooking->id);
    }

    #[Test]
    public function on_ne_regroupe_que_les_sites_de_sa_propre_societe(): void
    {
        [$org, $demandeur, $trade] = $this->societeCliente();
        $sien = $this->site($org, 'MIEN');

        $autreOrg = OrganizationAccount::factory()->clientCompany()->create();
        $etranger = $this->site($autreOrg, 'AUTRUI');

        $demande = app(MultiSiteRequestService::class)->creer(
            $demandeur,
            $org,
            $trade,
            [$sien->id, $etranger->id],
            now()->addWeek(),
        );

        $filles = Booking::where('parent_booking_id', $demande->id)->get();

        $this->assertCount(
            1,
            $filles,
            "Les identifiants de sites viennent du navigateur : un site d'une autre société ne doit pas entrer dans la demande.",
        );
        $this->assertSame($sien->id, $filles->first()->organization_site_id);
    }

    #[Test]
    public function une_demande_sans_site_valide_n_est_pas_creee(): void
    {
        [$org, $demandeur, $trade] = $this->societeCliente();

        $autreOrg = OrganizationAccount::factory()->clientCompany()->create();
        $etranger = $this->site($autreOrg, 'AUTRUI');

        $demande = app(MultiSiteRequestService::class)->creer(
            $demandeur,
            $org,
            $trade,
            [$etranger->id],
            now()->addWeek(),
        );

        $this->assertNull(
            $demande,
            'Une demande mère sans aucune fille serait une coquille vide, invisible et jamais traitée.',
        );
        $this->assertSame(0, Booking::count());
    }
}
