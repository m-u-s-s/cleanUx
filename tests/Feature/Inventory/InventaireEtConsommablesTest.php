<?php

namespace Tests\Feature\Inventory;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Support\Domain\MissionStatus;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'INVENTAIRE DES CONSOMMABLES (E23) ET LA SAISIE SUR PLACE (F7).
 *
 * Une société de nettoyage achète des produits et les distribue à ses équipes. Ce suivi se fait
 * aujourd'hui sur un tableur — quand il se fait : personne ne sait ce qui reste dans quelle agence,
 * et on découvre la rupture le matin où une équipe part sans produit.
 *
 * CE QUE CE FICHIER PROTÈGE AVANT TOUT : le compteur est le RÉSULTAT des mouvements, jamais une
 * valeur qu'on écrit. Dès qu'on peut ajuster le stock à la main sans laisser de trace, le registre
 * et le compteur divergent — et plus personne ne sait lequel croire. Corriger un écart reste
 * légitime, mais c'est un mouvement déclaré, avec son motif.
 *
 * ET LE STOCK NE DESCEND PAS SOUS ZÉRO. Une consommation supérieure au stock signale soit une
 * erreur de saisie, soit un stock déjà faux : l'accepter en silence produirait un compteur négatif
 * que personne ne saurait expliquer.
 */
class InventaireEtConsommablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @return array{0: OrganizationAccount, 1: InventoryItem, 2: User} */
    private function stock(int $quantite = 10, int $seuil = 2): array
    {
        $organisation = OrganizationAccount::factory()->create();

        $item = InventoryItem::factory()->create([
            'organization_account_id' => $organisation->id,
            'quantity' => $quantite,
            'reorder_threshold' => $seuil,
        ]);

        $gestionnaire = User::factory()->create(['organization_account_id' => $organisation->id]);

        return [$organisation, $item, $gestionnaire];
    }

    // ── E23 : le stock ───────────────────────────────────────────────────────

    #[Test]
    public function chaque_mouvement_laisse_une_trace(): void
    {
        [, $item, $gestionnaire] = $this->stock();

        app(InventoryService::class)->receptionner($item, 5, $gestionnaire);

        $this->assertSame(15, $item->fresh()->quantity);

        /*
         * LE COMPTEUR DÉCOULE DU REGISTRE. Sans le mouvement, on saurait qu'il reste quinze sans
         * jamais pouvoir répondre « pourquoi » — or c'est la question qui se pose vraiment quand un
         * stock fond plus vite que prévu.
         */
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'type' => InventoryMovement::TYPE_RECEPTION,
            'quantity' => 5,
        ]);
    }

    #[Test]
    public function le_stock_ne_descend_pas_sous_zero(): void
    {
        [, $item, $gestionnaire] = $this->stock(quantite: 3);

        $this->expectException(DomainException::class);

        app(InventoryService::class)->consommer($item, 5, $gestionnaire);
    }

    #[Test]
    public function le_refus_ne_laisse_pas_de_mouvement_orphelin(): void
    {
        [, $item, $gestionnaire] = $this->stock(quantite: 3);

        try {
            app(InventoryService::class)->consommer($item, 5, $gestionnaire);
        } catch (DomainException) {
            // Attendu.
        }

        // La transaction protège les deux écritures ensemble : un mouvement sans variation de stock
        // ferait diverger le registre du compteur, exactement ce qu'on veut éviter.
        $this->assertSame(0, InventoryMovement::query()->where('inventory_item_id', $item->id)->count());
        $this->assertSame(3, $item->fresh()->quantity);
    }

    #[Test]
    public function un_ajustement_sans_motif_est_refuse(): void
    {
        [, $item, $gestionnaire] = $this->stock();

        // Une réception et une consommation s'expliquent d'elles-mêmes ; un ajustement, non — c'est
        // précisément le mouvement qu'on relira dans six mois en se demandant ce qui s'est passé.
        $this->expectException(DomainException::class);

        app(InventoryService::class)->ajuster($item, -2, $gestionnaire, '   ');
    }

    #[Test]
    public function les_articles_sous_le_seuil_remontent(): void
    {
        [$organisation, $item, $gestionnaire] = $this->stock(quantite: 3, seuil: 2);

        app(InventoryService::class)->consommer($item, 1, $gestionnaire);

        $aCommander = app(InventoryService::class)->aReapprovisionner($organisation->id);

        $this->assertCount(1, $aCommander);
        $this->assertSame($item->id, $aCommander->first()->id);
    }

    // ── F7 : la saisie sur place ─────────────────────────────────────────────

    /** @return array{0: User, 1: Mission, 2: InventoryItem} */
    private function missionAvecStock(): array
    {
        [$organisation, $item] = $this->stock();

        $prestataire = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);
        $client = User::factory()->create();

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
        ]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'lead_employee_id' => $prestataire->id,
            'lead_provider_user_id' => $prestataire->id,
            'provider_organization_id' => $organisation->id,
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subHour(),
        ]);

        return [$prestataire, $mission->fresh(), $item];
    }

    #[Test]
    public function le_terrain_declare_ce_qu_il_a_consomme(): void
    {
        [$prestataire, $mission, $item] = $this->missionAvecStock();

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/consumables", [
                'inventory_item_id' => $item->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.remaining', 8);

        /*
         * LE MOUVEMENT PORTE LA MISSION. C'est ce lien qui permettra d'en calculer le coût réel
         * (E22) et de facturer les consommables qui le sont — sans lui, on saurait qu'un bidon est
         * parti sans savoir chez qui.
         */
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'mission_id' => $mission->id,
            'type' => InventoryMovement::TYPE_CONSUMPTION,
            'quantity' => -2,
        ]);
    }

    #[Test]
    public function on_ne_ponctionne_pas_le_magasin_d_une_autre_societe(): void
    {
        [$prestataire, $mission] = $this->missionAvecStock();
        [, $itemVoisin] = $this->stock();

        // L'identifiant d'article est un entier : sans ce contrôle, en essayer d'autres viderait le
        // magasin d'une société concurrente.
        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/consumables", [
                'inventory_item_id' => $itemVoisin->id,
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(10, $itemVoisin->fresh()->quantity);
    }

    #[Test]
    public function un_prestataire_etranger_ne_voit_pas_le_stock(): void
    {
        [, $mission] = $this->missionAvecStock();
        $intrus = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $this->actingAs($intrus, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/consumables")
            ->assertForbidden();
    }

    #[Test]
    public function un_independant_sans_societe_voit_une_liste_vide(): void
    {
        [$prestataire, $mission] = $this->missionAvecStock();

        $mission->forceFill(['provider_organization_id' => null])->save();

        // Lui montrer le stock d'une société à laquelle il n'appartient pas serait pire qu'une
        // liste vide.
        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/consumables")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
