<?php

namespace Tests\Feature\Admin;

use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Admin\AdminAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L'ALERTE NE DOIT PROPOSER QUE DES LIENS QUI MÈNENT QUELQUE PART. */
class AlerteDisponibiliteLiensVivantsTest extends TestCase
{
    use RefreshDatabase;

    /** TÉMOIN POSITIF — un vrai prestataire sans créneau reste signalé. */
    public function test_temoin_un_prestataire_sans_creneau_est_signale(): void
    {
        $presta = User::factory()->employe()->create(['is_active' => true]);
        ProviderProfile::create([
            'user_id' => $presta->id,
            'provider_type' => 'independent',
            'status' => 'active',
        ]);

        $alertes = app(AdminAlertService::class)->alerts();

        $this->assertTrue(
            $alertes['providers_without_availability']->contains('id', $presta->id),
            "Un prestataire réel sans créneau doit rester dans l'alerte"
        );
    }

    // LE CAS « LISTÉ MAIS REFUSÉ » N'EXISTE PLUS, ET C'EST LE RÉSULTAT VOULU.

    /** L'INVARIANT — chaque nom listé mène à une fiche qui s'ouvre. */
    public function test_chaque_compte_liste_ouvre_bien_sa_fiche(): void
    {
        $admin = User::factory()->adminComplet()->create();

        $presta = User::factory()->employe()->create(['is_active' => true]);
        ProviderProfile::create([
            'user_id' => $presta->id,
            'provider_type' => 'independent',
            'status' => 'active',
        ]);

        $listes = app(AdminAlertService::class)->alerts()['providers_without_availability'];

        $this->assertGreaterThan(0, $listes->count(), 'Le contrôle a besoin d’au moins un compte listé');

        foreach ($listes as $compte) {
            $this->actingAs($admin)
                ->get(route('admin.availability.provider', $compte))
                ->assertOk();
        }
    }
}
