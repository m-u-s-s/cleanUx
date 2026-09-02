<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\EnterpriseApprovalsCenter;
use App\Models\Booking;
use App\Models\EnterpriseBookingApproval;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA DOUBLE VALIDATION B2B DIT CE QU'ELLE A FAIT.
 *
 * Deux defauts rendaient l'ecran trompeur. Le service SORT EN SILENCE quand le statut n'autorise
 * plus le geste, et le composant annoncait un succes quoi qu'il arrive : deux administrateurs sur
 * la meme demande, et le second lisait « Validation effectuee » alors que rien n'avait bouge.
 *
 * Et la note interne etait UNE SEULE propriete partagee par toutes les cartes : saisie sur l'une,
 * elle partait avec le clic donne sur une autre — l'historique enregistrait la mauvaise phrase.
 */
class LaDoubleValidationB2BTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_validation_manager_fait_avancer_la_demande(): void
    {
        $demande = $this->demande('pending_manager');

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->call('approveManager', $demande->id);

        $this->assertSame('pending_finance', $demande->fresh()->status);
    }

    /**
     * UN GESTE SANS EFFET NE S'ANNONCE PAS COMME UN SUCCES.
     *
     * C'est le defaut central : le service refuse en silence, l'ecran felicitait quand meme.
     */
    public function test_un_geste_sans_effet_avertit_au_lieu_de_feliciter(): void
    {
        $demande = $this->demande('approved');

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->call('approveManager', $demande->id)
            ->assertDispatched('toast', function (string $evenement, array $arguments) {
                return $arguments[1] === 'warning';
            });

        $this->assertSame('approved', $demande->fresh()->status);
    }

    /** TEMOIN — le meme geste sur une demande recevable annonce bien un succes. */
    public function test_temoin_un_geste_qui_agit_annonce_un_succes(): void
    {
        $demande = $this->demande('pending_manager');

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->call('approveManager', $demande->id)
            ->assertDispatched('toast', function (string $evenement, array $arguments) {
                return $arguments[1] === 'success';
            });
    }

    /**
     * CHAQUE DEMANDE PORTE SA PROPRE NOTE.
     *
     * Une propriete unique attachait la note saisie sur une carte au geste donne sur une autre.
     */
    public function test_la_note_saisie_sur_une_demande_ne_part_pas_avec_une_autre(): void
    {
        $premiere = $this->demande('pending_manager');
        $seconde = $this->demande('pending_manager');

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->set('notes.'.$premiere->id, 'Note destinée à la PREMIÈRE demande')
            ->call('approveManager', $seconde->id);

        $this->assertNull($seconde->fresh()->manager_note,
            'La note d’une autre demande s’est attachée à celle-ci.');
    }

    /** TEMOIN — la note attachee a LA BONNE demande arrive bien jusqu'a l'historique. */
    public function test_temoin_la_note_de_la_bonne_demande_est_enregistree(): void
    {
        $demande = $this->demande('pending_manager');

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->set('notes.'.$demande->id, 'Budget validé par la direction')
            ->call('approveManager', $demande->id);

        $this->assertSame('Budget validé par la direction', $demande->fresh()->manager_note);
    }

    public function test_le_refus_exige_un_motif_et_ferme_la_demande(): void
    {
        $demande = $this->demande('pending_manager');

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->call('openRejectModal', $demande->id)
            ->set('rejectionReason', '')
            ->call('reject')
            ->assertHasErrors(['rejectionReason' => 'required']);

        $this->assertSame('pending_manager', $demande->fresh()->status);

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->call('openRejectModal', $demande->id)
            ->set('rejectionReason', 'Budget dépassé pour ce trimestre')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertSame('rejected', $demande->fresh()->status);
    }

    /** LE BON DE COMMANDE ET LE CENTRE DE COUT vivaient en base sans jamais s'afficher. */
    public function test_l_ecran_montre_le_bon_de_commande_et_le_centre_de_cout(): void
    {
        $demande = $this->demande('pending_manager');
        $demande->forceFill([
            'purchase_order_number' => 'PO-2026-0042',
            'cost_center' => 'CC-LOGISTIQUE',
        ])->save();

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->assertSee('PO-2026-0042')
            ->assertSee('CC-LOGISTIQUE');
    }

    /**
     * LE MARQUEUR EST PROPRE A LA LIGNE, pas le nom de la societe : celui-ci figure AUSSI dans la
     * liste deroulante du filtre, et l'assertion mesurerait alors la presence du filtre.
     */
    public function test_les_filtres_reduisent_la_file(): void
    {
        $enAttente = $this->demande('pending_manager');
        $enAttente->forceFill(['purchase_order_number' => 'PO-EN-ATTENTE'])->save();

        $approuvee = $this->demande('approved');
        $approuvee->forceFill(['purchase_order_number' => 'PO-APPROUVEE'])->save();

        Livewire::actingAs($this->admin())->test(EnterpriseApprovalsCenter::class)
            ->set('status', 'approved')
            ->assertSee('PO-APPROUVEE')
            ->assertDontSee('PO-EN-ATTENTE');
    }

    /**
     * LA CAPACITE GARDE AUSSI LE COMPOSANT : `module_gate` pose `manage-entreprises` sur la route,
     * mais `/livewire/update` ne rejoue aucun middleware.
     */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $sansCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-calendar'],
        ]);

        Livewire::actingAs($sansCapacite)->test(EnterpriseApprovalsCenter::class)
            ->assertForbidden();
    }

    /** TEMOIN — la meme visite avec la capacite aboutit ; sans lui le refus mesurerait une panne. */
    public function test_temoin_un_administrateur_avec_la_capacite_entre(): void
    {
        $avecCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-entreprises'],
        ]);

        Livewire::actingAs($avecCapacite)->test(EnterpriseApprovalsCenter::class)
            ->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }

    private function demande(string $statut): EnterpriseBookingApproval
    {
        $societe = OrganizationAccount::factory()->create(['status' => 'active']);

        $rdv = Booking::factory()->create([
            'organization_account_id' => $societe->id,
            'status' => 'pending_approval',
            'devis_estime' => 250,
        ]);

        return EnterpriseBookingApproval::query()->create([
            'rendez_vous_id' => $rdv->id,
            'organization_account_id' => $societe->id,
            'status' => $statut,
        ]);
    }
}
