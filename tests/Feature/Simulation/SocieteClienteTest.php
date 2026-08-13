<?php

namespace Tests\Feature\Simulation;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Livewire\Client\MesRendezVousClient;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * LA SOCIÉTÉ CLIENTE, SUR SES DEUX SURFACES.
 *
 * Une réservation d'entreprise doit être visible par ses membres — et par eux seuls — aussi bien
 * dans l'application mobile que sur le web. Or les deux surfaces ne lisent PAS la même colonne :
 * l'API filtre sur `customer_organization_id`, le périmètre web sur `organization_account_id`.
 * Les deux existent sur `bookings`, et rien dans le schéma n'oblige à les remplir ensemble.
 *
 * C'est la forme même du défaut qu'on a vu trois fois cette semaine : deux notions voisines, une
 * seule renseignée selon le chemin de création, et une moitié de l'application qui devient aveugle
 * sans que personne s'en aperçoive — parce que chaque moitié, testée seule, a raison.
 *
 * Ce parcours interroge donc les DEUX, avec la même réservation et le même utilisateur.
 */
class SocieteClienteTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $contexte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contexte = $this->createCoverageContext();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'lat' => '50.8466', 'lon' => '4.3528',
                'display_name' => 'Rue de Test 1, 1000 Bruxelles, Belgique',
            ]], 200),
        ]);
    }

    /**
     * @return array{0: OrganizationAccount, 1: User}
     */
    private function societeCliente(OrganizationRole $role = OrganizationRole::MANAGER): array
    {
        $societe = OrganizationAccount::factory()->create([
            'type' => OrganizationType::CLIENT_COMPANY->value,
            'status' => 'active',
        ]);

        $membre = User::factory()->client()->create();

        OrganizationMember::create([
            'organization_account_id' => $societe->id,
            'user_id' => $membre->id,
            'role' => $role->value,
            'status' => 'active',
        ]);

        /*
         * LES DEUX COLONNES D'ORGANISATION ACTIVE SONT RENSEIGNÉES.
         *
         * `organization_account_id` et `current_organization_id` désignent la même chose et le code
         * lit tantôt l'une, tantôt l'autre. N'en remplir qu'une donne un 403 sur la moitié des
         * écrans — c'est un piège déjà rencontré sur ce dépôt.
         */
        $membre->forceFill([
            'organization_account_id' => $societe->id,
            'current_organization_id' => $societe->id,
        ])->save();

        return [$societe, $membre->fresh()];
    }

    /**
     * Une réservation passée POUR la société, par le chemin web : seule
     * `organization_account_id` est renseignée, comme le fait le portail client.
     */
    private function reservationDeSociete(OrganizationAccount $societe, User $auteur): Booking
    {
        return Booking::factory()
            ->forStructuredContext(
                $this->contexte['service'],
                $this->contexte['zone'],
                $this->contexte['postalCode'],
            )
            ->create([
                'client_id' => $auteur->id,
                'customer_user_id' => $auteur->id,
                'organization_account_id' => $societe->id,
                'status' => 'confirme',
                'devis_estime' => 250.00,
            ]);
    }

    /** Les identifiants que l'application MOBILE montre à cet utilisateur. */
    private function surMobile(User $user): array
    {
        $reponse = $this->actingAs($user, 'sanctum')->getJson('/api/client/bookings');
        $reponse->assertOk();

        return collect($reponse->json('data.data') ?? $reponse->json('data'))
            ->pluck('id')->sort()->values()->all();
    }

    /**
     * LA MÊME RÉSERVATION DOIT SE VOIR SUR LES DEUX SURFACES.
     *
     * C'est le test qui compte : il oppose la lecture mobile à la lecture web sur une seule et
     * même donnée. Si les deux colonnes d'organisation divergent, une moitié devient aveugle.
     */
    public function test_une_reservation_de_societe_se_voit_sur_mobile_et_sur_web(): void
    {
        [$societe, $membre] = $this->societeCliente();
        $reservation = $this->reservationDeSociete($societe, $membre);

        $this->assertContains(
            $reservation->id,
            $this->surMobile($membre),
            'La réservation de la société est invisible dans l’application mobile.',
        );

        $this->actingAs($membre);
        Livewire::test(MesRendezVousClient::class)
            ->assertSee($reservation->booking_reference);
    }

    /**
     * UN COLLÈGUE DE LA SOCIÉTÉ LA VOIT AUSSI — c'est tout l'intérêt d'un compte d'entreprise.
     */
    public function test_un_autre_membre_de_la_societe_voit_la_reservation(): void
    {
        [$societe, $auteur] = $this->societeCliente();

        $collegue = User::factory()->client()->create();
        OrganizationMember::create([
            'organization_account_id' => $societe->id,
            'user_id' => $collegue->id,
            'role' => OrganizationRole::SITE_MANAGER->value,
            'status' => 'active',
        ]);
        $collegue->forceFill([
            'organization_account_id' => $societe->id,
            'current_organization_id' => $societe->id,
        ])->save();

        $reservation = $this->reservationDeSociete($societe, $auteur);

        $this->assertContains(
            $reservation->id,
            $this->surMobile($collegue->fresh()),
            'Un collègue de la même société ne voit pas la réservation.',
        );
    }

    /**
     * ET UNE AUTRE SOCIÉTÉ NE LA VOIT JAMAIS — sur aucune des deux surfaces.
     */
    public function test_une_autre_societe_ne_voit_rien(): void
    {
        [$societeA, $membreA] = $this->societeCliente();
        [, $membreB] = $this->societeCliente();

        $reservation = $this->reservationDeSociete($societeA, $membreA);

        $this->assertNotContains(
            $reservation->id,
            $this->surMobile($membreB),
            'Une société voit les réservations d’une autre dans l’application mobile.',
        );

        $this->actingAs($membreB);
        Livewire::test(MesRendezVousClient::class)
            ->assertDontSee($reservation->booking_reference);

        // Et l'accès direct est refusé, pas seulement masqué de la liste.
        $this->actingAs($membreB, 'sanctum')
            ->getJson("/api/client/bookings/{$reservation->id}")
            ->assertForbidden();
    }

    /**
     * UN PARTICULIER SANS SOCIÉTÉ garde sa propre liste, sans fuite d'entreprise.
     */
    public function test_un_particulier_ne_recupere_pas_les_reservations_d_entreprise(): void
    {
        [$societe, $membre] = $this->societeCliente();
        $reservation = $this->reservationDeSociete($societe, $membre);

        $particulier = User::factory()->client()->create();

        $this->assertNotContains($reservation->id, $this->surMobile($particulier));
    }
}
