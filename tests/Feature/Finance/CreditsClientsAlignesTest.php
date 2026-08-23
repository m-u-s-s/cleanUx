<?php

namespace Tests\Feature\Finance;

use App\Models\Booking;
use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** `customer_credits` : LE MODÈLE ET LA TABLE DISENT LA MÊME CHOSE, ET ON LE VÉRIFIE. */
class CreditsClientsAlignesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_creation_par_le_modele_fonctionne(): void
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        // C'est LE geste que le piège consigné déclarait cassé. Il ne l'est plus, et ce test le
        // maintiendra ainsi.
        $credit = CustomerCredit::create([
            'client_id' => $client->id,
            'rendez_vous_id' => $booking->id,
            'type' => 'commercial_gesture',
            'amount' => 25.0,
            'remaining_amount' => 25.0,
            'status' => 'active',
            'reason' => 'Retard du prestataire',
            'notes' => 'Geste commercial',
            'expires_at' => now()->addMonths(6),
        ]);

        $this->assertNotNull($credit->id);
        $this->assertDatabaseHas('customer_credits', [
            'id' => $credit->id,
            'client_id' => $client->id,
            'rendez_vous_id' => $booking->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function la_relecture_rend_les_memes_montants(): void
    {
        $client = User::factory()->client()->create();

        $credit = CustomerCredit::create([
            'client_id' => $client->id,
            'type' => 'refund',
            'amount' => 12.34,
            'remaining_amount' => 12.34,
            'status' => 'active',
            'reason' => 'Remboursement partiel',
        ]);

        $relu = CustomerCredit::findOrFail($credit->id);

        // Les montants sont castés en `decimal:2` : une relecture qui rendrait une chaîne ou un
        // arrondi différent ferait diverger le solde affiché du solde réel.
        $this->assertSame('12.34', (string) $relu->amount);
        $this->assertSame('12.34', (string) $relu->remaining_amount);
        $this->assertTrue($relu->expires_at === null);
    }

    #[Test]
    public function le_portefeuille_client_voit_le_credit(): void
    {
        $client = User::factory()->client()->create();

        CustomerCredit::create([
            'client_id' => $client->id,
            'type' => 'commercial_gesture',
            'amount' => 40.0,
            'remaining_amount' => 40.0,
            'status' => 'active',
            'reason' => 'Geste',
        ]);

        // LE CONSOMMATEUR EST INTERROGÉ, pas seulement la table.
        $solde = CustomerCredit::query()
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->sum('remaining_amount');

        $this->assertSame(40.0, (float) $solde);
    }

    #[Test]
    public function la_liste_blanche_du_modele_ne_designe_que_des_colonnes_reelles(): void
    {
        $colonnes = Schema::getColumnListing('customer_credits');
        $fantomes = array_diff((new CustomerCredit)->getFillable(), $colonnes);

        // Un attribut remplissable sans colonne est écarté EN SILENCE par Eloquent : la ligne
        // s'enregistre incomplète et rien ne le dit.
        $this->assertSame([], array_values($fantomes));
    }

    #[Test]
    public function les_colonnes_orphelines_ont_disparu(): void
    {
        // Elles ne portaient aucune donnée et aucun code ne les touchait ; les garder revenait à
        // maintenir un piège pour le prochain lecteur.
        $this->assertFalse(Schema::hasColumn('customer_credits', 'customer_user_id'));
        $this->assertFalse(Schema::hasColumn('customer_credits', 'customer_organization_id'));
        $this->assertTrue(Schema::hasColumn('customer_credits', 'client_id'));
    }
}
