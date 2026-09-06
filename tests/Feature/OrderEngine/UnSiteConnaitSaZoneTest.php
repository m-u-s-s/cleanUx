<?php

namespace Tests\Feature\OrderEngine;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UN SITE SANS ZONE EST UN SITE QU'ON NE PEUT PAS SERVIR.
 *
 * Trois des cinq chemins de creation ecrivaient l'adresse sans `service_zone_id` — dont celui
 * de l'espace societe, le seul qu'un client atteint depuis son telephone. Le moteur de commande
 * repondait alors « Aucun professionnel ne couvre encore cette zone » tous les jours, aucun
 * creneau n'etait selectionnable, et le rendez-vous retombait en « Des que possible ».
 *
 * Mesure du 2026-09-06 : local cree a Bruxelles 1000 depuis l'application, `service_zone_id`
 * a NULL, alors que « Zone Bruxelles » existe et que 190 tarifs metier x zone sont en base.
 */
class UnSiteConnaitSaZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_site_cree_sans_zone_la_retrouve_depuis_son_code_postal(): void
    {
        $zone = $this->zoneBruxelloise();

        $site = OrganizationSite::create([
            'organization_account_id' => $this->compte()->id,
            'name' => 'Siege Bruxelles',
            'address' => 'Rue de la Loi 16',
            'postal_code' => '1000',
            'city' => 'Bruxelles',
            'status' => 'active',
        ]);

        $this->assertSame($zone->id, $site->fresh()->service_zone_id);
    }

    public function test_temoin_une_zone_choisie_a_la_main_n_est_pas_ecrasee(): void
    {
        // Sans ce controle, le rattachement automatique deferait le choix d'un administrateur.
        $this->zoneBruxelloise();
        $autre = ServiceZone::factory()->create(['name' => 'Zone choisie', 'status' => 'active']);

        $site = OrganizationSite::create([
            'organization_account_id' => $this->compte()->id,
            'name' => 'Siege',
            'address' => 'Rue de la Loi 16',
            'postal_code' => '1000',
            'city' => 'Bruxelles',
            'service_zone_id' => $autre->id,
            'status' => 'active',
        ]);

        $this->assertSame($autre->id, $site->fresh()->service_zone_id);
    }

    public function test_temoin_un_code_postal_sans_zone_s_enregistre_quand_meme(): void
    {
        // Le rattachement ne doit jamais empecher d'enregistrer : il informe, il ne bloque pas.
        $site = OrganizationSite::create([
            'organization_account_id' => $this->compte()->id,
            'name' => 'Code postal sans zone',
            'address' => 'Quelque part',
            'postal_code' => '99999',
            'city' => 'Nulle part',
            'status' => 'active',
        ]);

        $this->assertNotNull($site->id);
        $this->assertNull($site->fresh()->service_zone_id);
    }

    private function compte(): OrganizationAccount
    {
        return OrganizationAccount::factory()->create();
    }

    /** Une zone RESERVABLE, reliee a son code postal par la table de liaison. */
    private function zoneBruxelloise(): ServiceZone
    {
        $zone = ServiceZone::factory()->create([
            'name' => 'Zone Bruxelles',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
        ]);

        $zone->postalCodes()->attach(
            PostalCode::factory()->create(['code' => '1000'])->id,
            ['is_primary' => true],
        );

        return $zone;
    }
}
