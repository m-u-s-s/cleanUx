<?php

namespace Tests\Feature\Client;

use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Trois ecrans client montraient l'horodatage SQL : « 2026-09-20 00:00:00 à 08:30:00 ». */
class DateLisiblePourLeClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_client_lit_une_date_pas_un_horodatage(): void
    {
        $client = User::factory()->client()->create(['is_active' => true, 'email_verified_at' => now()]);

        Booking::factory()->create([
            'client_id' => $client->id,
            'date' => '2026-09-20',
            'heure' => '08:30:00',
            'status' => 'en_attente',
        ]);

        $reponse = $this->actingAs($client)->get('/dashboard/client/rendez-vous');

        $reponse->assertSuccessful();

        // LA DATE LISIBLE EST CELLE DU FORMATEUR, PAS UN FORMAT ÉCRIT EN DUR : `LocaleFormatter`
        // passe par `IntlDateFormatter` quand l'extension intl est chargée (« 20 sept. 2026 ») et
        // retombe sur `d/m/Y` sinon (« 20/09/2026 »). Coder l'un des deux faisait dépendre le test de
        // la machine : vert sans intl, rouge en CI. Calculée APRÈS la requête, dans la langue qu'elle
        // a posée.
        $dateLisible = locale_date(Carbon::parse('2026-09-20'));
        $this->assertNotSame('2026-09-20', $dateLisible);
        $reponse->assertSee($dateLisible.' à 08:30', escape: false);

        // LE TEMOIN DU DEFAUT : ni la seconde inutile, ni le minuit d'une date sans heure.
        $reponse->assertDontSee('2026-09-20 00:00:00', escape: false);
        $reponse->assertDontSee('08:30:00', escape: false);
    }

    public function test_le_prestataire_lit_lui_aussi_une_date(): void
    {
        $prestataire = User::factory()->employe()->create(['is_active' => true, 'email_verified_at' => now()]);

        Booking::factory()->create([
            'employe_id' => $prestataire->id,
            'date' => now()->addDays(3)->toDateString(),
            'heure' => '14:00:00',
            'status' => 'confirme',
        ]);

        $reponse = $this->actingAs($prestataire)->get('/dashboard/employe/historique');

        $reponse->assertSuccessful();
        $reponse->assertDontSee('14:00:00', escape: false);
    }
}
