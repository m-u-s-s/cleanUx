<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\MissionsAdmin;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE SCORING NE SE REGARDE PLUS, IL SE DÉCIDE.
 *
 * L'écran classait les candidats et s'arrêtait là : pour affecter, il fallait fermer la modale et
 * lancer le dispatch automatique, qui reprend le PREMIER. Voir un second mieux placé ce jour-là et
 * ne pas pouvoir le choisir, c'était donner une information sans le geste qu'elle appelle.
 */
class LAdminChoisitSonPrestataireTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_admin_affecte_le_prestataire_qu_il_designe(): void
    {
        [$rdv, $candidats] = $this->rendezVousAvecCandidats();
        $choisi = $candidats->last();

        Livewire::actingAs($this->admin())
            ->test(MissionsAdmin::class)
            ->call('previewDispatch', $rdv->id)
            ->call('choisirPrestataire', $rdv->id, $choisi->id)
            // La modale se referme quand l'affectation a eu lieu.
            ->assertSet('dispatchPreviewRdvId', null);

        $this->assertSame($choisi->id, (int) $rdv->fresh()->employe_id);
    }

    /**
     * LE CHOIX NE S'AFFRANCHIT PAS DU CLASSEMENT. `choisirPrestataire` est appelable depuis le
     * navigateur avec n'importe quel identifiant : seuls les candidats que le scoring a proposés
     * pour CE rendez-vous sont retenus.
     */
    public function test_un_prestataire_hors_classement_est_refuse(): void
    {
        [$rdv] = $this->rendezVousAvecCandidats();

        $etranger = User::factory()->create(['role' => User::ROLE_EMPLOYE, 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(MissionsAdmin::class)
            ->call('previewDispatch', $rdv->id)
            ->call('choisirPrestataire', $rdv->id, $etranger->id);

        $this->assertNotSame($etranger->id, (int) $rdv->fresh()->employe_id);
    }

    /** TÉMOIN — le même geste sur un candidat DU classement aboutit bien. */
    public function test_temoin_un_candidat_du_classement_est_accepte(): void
    {
        [$rdv, $candidats] = $this->rendezVousAvecCandidats();

        Livewire::actingAs($this->admin())
            ->test(MissionsAdmin::class)
            ->call('previewDispatch', $rdv->id)
            ->call('choisirPrestataire', $rdv->id, $candidats->first()->id);

        $this->assertSame($candidats->first()->id, (int) $rdv->fresh()->employe_id);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
        ]);
    }

    /** @return array{0: Booking, 1: Collection<int, User>} */
    private function rendezVousAvecCandidats(): array
    {
        $rdv = Booking::factory()->create([
            'employe_id' => null,
            'assigned_employee_id' => null,
        ]);

        $candidats = collect(range(1, 2))->map(function () use ($rdv) {
            $user = User::factory()->create([
                'role' => User::ROLE_EMPLOYE,
                'is_active' => true,
                'primary_service_zone_id' => $rdv->service_zone_id,
            ]);

            ProviderProfile::factory()->create([
                'user_id' => $user->id,
                'status' => 'active',
                'verification_status' => 'verified',
            ]);

            return $user;
        });

        return [$rdv, $candidats];
    }
}
