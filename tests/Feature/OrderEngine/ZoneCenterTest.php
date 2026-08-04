<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\ZoneCenter;
use App\Models\Booking;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le deuxième niveau : les zones d'UN pays.
 *
 * LE TEST LE PLUS IMPORTANT EST CELUI DU CLOISONNEMENT. Un écran qui laisserait fuir les zones d'un
 * autre pays n'aurait l'air de rien tant qu'il n'y a qu'un seul pays en base — c'est-à-dire
 * exactement aujourd'hui. Le défaut n'apparaîtrait qu'au deuxième marché ouvert, quand plus
 * personne ne se souviendra de cet écran.
 *
 * À NOTER : la CRÉATION de zone n'existait nulle part avant ce chantier. `saveZone()`, hérité de
 * l'écran des zones, n'édite qu'une zone déjà sélectionnée — il exige un `selectedZoneId` et fait
 * `findOrFail`. Ce composant apporte donc `creerZone()`.
 */
class ZoneCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->adminComplet());
    }

    /**
     * Un administrateur qui a le droit d'AGIR, et pas seulement d'entrer.
     *
     * Les actions héritées de l'écran des zones passent par le portail
     * `perform-critical-admin-actions`, qui refuse un administrateur en lecture seule. Un
     * `User::factory()->create(['role' => 'admin'])` franchit la porte du composant et échoue
     * ensuite en silence sur chaque action — le test échouait sans rien dire de la cause.
     */
    private function adminComplet(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
        ]);
    }

    public function test_il_ne_montre_que_les_zones_du_pays(): void
    {
        $belgique = Country::factory()->create(['name' => 'Belgique']);
        $france = Country::factory()->create(['name' => 'France']);
        ServiceZone::factory()->create(['country_id' => $belgique->id, 'name' => 'Bruxelles']);
        ServiceZone::factory()->create(['country_id' => $france->id, 'name' => 'Paris']);

        Livewire::test(ZoneCenter::class, ['country' => $belgique])
            ->assertOk()
            ->assertSee('Bruxelles')
            ->assertDontSee('Paris');
    }

    public function test_il_cree_la_zone_dans_le_bon_pays(): void
    {
        $pays = Country::factory()->create();

        Livewire::test(ZoneCenter::class, ['country' => $pays])
            ->set('nouvelleZone.name', 'Anvers')
            ->set('nouvelleZone.code', 'ANV')
            ->call('creerZone')
            ->assertHasNoErrors();

        // Le pays n'est PAS un champ du formulaire : il vient du contexte. Le laisser saisissable
        // permettrait de créer, depuis l'écran Belgique, une zone française — une erreur qui ne se
        // verrait qu'en cherchant une zone disparue.
        $this->assertDatabaseHas('service_zones', ['name' => 'Anvers', 'country_id' => $pays->id]);
    }

    public function test_une_zone_neuve_nait_fermee(): void
    {
        $pays = Country::factory()->create();

        Livewire::test(ZoneCenter::class, ['country' => $pays])
            ->set('nouvelleZone.name', 'Anvers')
            ->set('nouvelleZone.code', 'ANV')
            ->call('creerZone');

        // Même raison que pour un pays neuf : créer une zone ne doit pas la rendre commandable
        // avant qu'on ait réglé son catalogue et ses prix.
        $zone = ServiceZone::where('name', 'Anvers')->firstOrFail();
        $this->assertFalse((bool) $zone->is_bookable);
        $this->assertSame('draft', $zone->status);
    }

    public function test_il_refuse_deux_zones_au_meme_code(): void
    {
        $pays = Country::factory()->create();
        ServiceZone::factory()->create(['country_id' => $pays->id, 'code' => 'ANV']);

        Livewire::test(ZoneCenter::class, ['country' => $pays])
            ->set('nouvelleZone.name', 'Anvers bis')
            ->set('nouvelleZone.code', 'ANV')
            ->call('creerZone')
            ->assertHasErrors(['nouvelleZone.code']);
    }

    public function test_desactiver_une_zone_ne_touche_pas_au_pays(): void
    {
        $pays = Country::factory()->create(['is_active' => true]);
        $zone = ServiceZone::factory()->create(['country_id' => $pays->id, 'is_bookable' => true]);

        Livewire::test(ZoneCenter::class, ['country' => $pays])
            ->call('selectZone', $zone->id)
            ->call('toggleZoneBookability');

        $this->assertFalse((bool) $zone->fresh()->is_bookable);
        $this->assertTrue((bool) $pays->fresh()->is_active);
    }

    public function test_il_refuse_de_supprimer_une_zone_qui_porte_des_reservations(): void
    {
        $pays = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $pays->id]);
        Booking::factory()->create(['service_zone_id' => $zone->id]);

        Livewire::test(ZoneCenter::class, ['country' => $pays])
            ->call('supprimerZone', $zone->id)
            ->assertSee('réservation(s) rattachée(s)');

        $this->assertDatabaseHas('service_zones', ['id' => $zone->id]);
    }

    public function test_il_supprime_une_zone_vide(): void
    {
        $pays = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $pays->id]);

        Livewire::test(ZoneCenter::class, ['country' => $pays])->call('supprimerZone', $zone->id);

        $this->assertDatabaseMissing('service_zones', ['id' => $zone->id]);
    }

    public function test_il_refuse_de_supprimer_une_zone_d_un_autre_pays(): void
    {
        $belgique = Country::factory()->create();
        $france = Country::factory()->create();
        $parisienne = ServiceZone::factory()->create(['country_id' => $france->id]);

        /*
         * Le cloisonnement doit tenir sur les ACTIONS et pas seulement sur l'affichage : un
         * identifiant forgé dans la requête ne doit pas atteindre une zone d'un autre marché.
         *
         * On attrape l'exception plutôt que d'attendre un statut 404 : dans un test Livewire, une
         * `ModelNotFoundException` remonte telle quelle au lieu d'être convertie en réponse.
         */
        try {
            Livewire::test(ZoneCenter::class, ['country' => $belgique])
                ->call('supprimerZone', $parisienne->id);

            $this->fail('la zone d’un autre pays a été atteinte');
        } catch (ModelNotFoundException) {
            // Attendu.
        }

        $this->assertDatabaseHas('service_zones', ['id' => $parisienne->id]);
    }

    public function test_un_non_admin_n_entre_pas(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client']));
        $pays = Country::factory()->create();

        Livewire::test(ZoneCenter::class, ['country' => $pays])->assertForbidden();
    }
}
