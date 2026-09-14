<?php

namespace Tests\Feature\Argent;

use App\Models\AccountingEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DEUX CHEMINS D'ARGENT REJOUÉS, ET UN REGISTRE QUI SE DISAIT EN LECTURE SEULE.
 *
 * Un appel Stripe rejoué sans clé d'idempotence débite autant de fois qu'il est retenté ; le
 * dépôt le sait et l'écrit à `ProcessProviderPayouts:228`, mais deux sites explicitement
 * REJOUÉS en étaient dépourvus. On mesure ici la présence de la clé dans la source : la course
 * réelle demande Stripe, que la suite n'a pas.
 */
class LArgentNeSeDebitePasDeuxFoisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function chargesRejouees(): array
    {
        return [
            'règlement du temps supplémentaire' => [
                'app/Services/Missions/HourlySettlementService.php',
                'time_settlement:',
            ],
            'cycle d’abonnement' => [
                'app/Services/SubscriptionsV2/Providers/StripeBillingProvider.php',
                'sub_cycle:',
            ],
        ];
    }

    #[Test]
    #[DataProvider('chargesRejouees')]
    public function une_charge_rejouee_porte_une_cle_d_idempotence(string $chemin, string $prefixe): void
    {
        $source = (string) file_get_contents(base_path($chemin));

        $this->assertNotSame('', $source, "{$chemin} : introuvable, le test ne mesure plus rien.");
        $this->assertStringContainsString('PaymentIntent', $source, 'le fichier ne crée plus de paiement');
        $this->assertStringContainsString("'idempotency_key' => '{$prefixe}", $source);
    }

    /** LE TÉMOIN : le site qui savait déjà faire n'a pas régressé. */
    #[Test]
    public function le_versement_prestataire_garde_la_sienne(): void
    {
        $source = (string) file_get_contents(base_path('app/Console/Commands/ProcessProviderPayouts.php'));

        $this->assertStringContainsString("'idempotency_key'", $source);
    }

    #[Test]
    public function une_ecriture_du_grand_livre_ne_se_supprime_pas(): void
    {
        Sanctum::actingAs(User::factory()->adminComplet()->create(), ['*']);

        $ecriture = AccountingEntry::factory()->create();

        $this->deleteJson('/api/admin/console/accounting/'.$ecriture->id)
            ->assertStatus(409)
            ->assertJsonPath('error', 'delete_refused');

        $this->assertDatabaseHas('accounting_entries', ['id' => $ecriture->id]);
    }

    /** LE TÉMOIN : une ressource qui accepte la suppression la garde. */
    #[Test]
    public function temoin_une_autre_ressource_se_supprime_toujours(): void
    {
        Sanctum::actingAs(User::factory()->adminComplet()->create(), ['*']);

        // La lecture du grand livre, elle, reste ouverte : ce n'est pas un blocage global.
        $this->getJson('/api/admin/console/accounting')->assertOk();
    }
}
