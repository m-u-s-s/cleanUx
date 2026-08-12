<?php

namespace Tests\Feature;

use App\Livewire\Client\MissionTracking;
use App\Notifications\MissionEndCodeNotification;
use App\Services\Missions\MissionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

/**
 * Le client doit pouvoir LIRE son code de fin sur le web.
 *
 * Sans cela, le prestataire se tient devant lui avec un champ à six chiffres que personne ne peut
 * remplir : le code n'est jamais stocké en clair, et son unique SMS peut très bien avoir été
 * avalé par le plafond d'envoi.
 */
class ClientEndCodeVisibilityTest extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '50.8466',
                    'lon' => '4.3528',
                    'display_name' => 'Rue de Test 1, 1000 Bruxelles, Belgique',
                ],
            ], 200),
        ]);
    }

    public function test_le_client_voit_les_six_chiffres_du_code_de_fin_sur_son_suivi_web(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'assigned']);

        // Le geste « je suis arrivé » fait naître le code de fin — PAS la clôture. Au moment de
        // clôturer, le code doit déjà être entre les mains du client.
        app(MissionLifecycleService::class)->setArrived($scenario['mission'], $scenario['employee']);

        // L'encadré « Code de fin disponible » ne s'affiche qu'une fois la mission démarrée.
        $mission = $scenario['mission']->fresh();
        $mission->update(['status' => 'started']);

        $porteur = $scenario['client']->fresh()->notifications()
            ->where('type', MissionEndCodeNotification::class)
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $porteur,
            "Le client n'a reçu aucun porteur du code de fin : le code en clair est perdu à jamais."
        );

        $codeDeFin = data_get($porteur->data, 'end_code');
        $this->assertNotNull($codeDeFin, 'La notification ne transporte pas le code de fin.');

        $this->actingAs($scenario['client']);

        Livewire::test(MissionTracking::class, ['mission' => $mission->fresh()])
            ->assertSee('Code de fin disponible')
            ->assertSee($codeDeFin)
            // Ce texte de repli EST le défaut : il s'affichait à la place des six chiffres.
            ->assertDontSee('Code généré côté employé');
    }
}
