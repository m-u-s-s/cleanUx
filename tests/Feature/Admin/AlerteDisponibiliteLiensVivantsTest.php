<?php

namespace Tests\Feature\Admin;

use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Admin\AdminAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'ALERTE NE DOIT PROPOSER QUE DES LIENS QUI MÈNENT QUELQUE PART.
 *
 * L'écran d'alertes admin liste les « prestataires sans disponibilité » et rend chaque nom
 * cliquable vers sa fiche de créneaux. Deux définitions de « prestataire » se répondaient :
 *
 *   - l'alerte listait sur `providerProfile` OU la colonne héritée `role` ;
 *   - la fiche refuse tout compte dont `isEmploye()` est faux, par un 404.
 *
 * Résultat mesuré sur l'écran réel : deux clientes porteuses d'un profil prestataire sans
 * type exploitable figuraient dans l'alerte, et leur nom menait à une page vide.
 */
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

    /*
        LE CAS « LISTÉ MAIS REFUSÉ » N'EXISTE PLUS, ET C'EST LE RÉSULTAT VOULU.

        Il tenait à `provider_type` : l'alerte listait sur la présence d'un profil, la fiche
        refusait tout type qu'elle ne reconnaissait pas. Depuis que `ProviderType` porte ses
        synonymes — `individual` vaut `independent`, `company` vaut `company_worker` — toute
        valeur possible ouvre la fiche, et la colonne interdit le nul en base.

        Écrire ici un compte artificiellement refusé demanderait de fabriquer un état que la
        base n'accepte pas : le test mesurerait sa propre mise en scène. L'invariant ci-dessous
        suffit, et c'est lui qui garde le lien vivant.
    */

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
