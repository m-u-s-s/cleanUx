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
            ->assertSee('Code de fin')
            ->assertSee($codeDeFin)
            // Ce texte de repli EST le défaut : il s'affichait à la place des six chiffres.
            ->assertDontSee('Code généré côté employé');
    }

    /**
     * UN CODE EXPIRÉ NE DOIT PAS S'AFFICHER COMME S'IL ÉTAIT BON.
     *
     * Le TTL est de vingt minutes. Passé ce délai, le client dictait six chiffres que le
     * prestataire se voyait refuser par « Le code a expiré », sans qu'aucun des deux comprenne.
     */
    public function test_un_code_expire_n_est_pas_affiche(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'assigned']);

        app(MissionLifecycleService::class)->setArrived($scenario['mission'], $scenario['employee']);

        $mission = $scenario['mission']->fresh();
        $mission->update(['status' => 'started']);

        $code = $mission->verificationCodes()
            ->where('code_type', 'end')->where('is_consumed', false)->latest('id')->first();
        $chiffres = data_get(
            $scenario['client']->fresh()->notifications()->latest('id')->first()?->data,
            'end_code',
        );

        $code->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($scenario['client']);

        $composant = Livewire::test(MissionTracking::class, ['mission' => $mission->fresh()]);

        $composant->assertDontSee($chiffres);
        // Et le client garde un moyen d'en obtenir un neuf, sinon il est coincé.
        $composant->assertSee('Afficher mon code de fin');
    }

    /** Le client obtient son code lui-même, sans dépendre d'un SMS ni du prestataire. */
    public function test_le_client_peut_generer_son_code_de_fin(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'started']);

        $this->actingAs($scenario['client']);

        $composant = Livewire::test(MissionTracking::class, ['mission' => $scenario['mission']])
            ->assertSee('Afficher mon code de fin')
            ->call('genererCodeDeFin');

        $code = $scenario['mission']->verificationCodes()
            ->where('code_type', 'end')->where('is_consumed', false)->latest('id')->first();

        $this->assertNotNull($code, 'Le geste doit émettre un code de fin.');

        $chiffres = data_get(
            $scenario['client']->fresh()->notifications()->latest('id')->first()?->data,
            'end_code',
        );

        $this->assertNotNull($chiffres);
        $composant->assertSee($chiffres);
    }
}
