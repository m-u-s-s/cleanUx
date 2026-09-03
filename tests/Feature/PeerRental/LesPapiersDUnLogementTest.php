<?php

namespace Tests\Feature\PeerRental;

use App\Livewire\PeerRental\PeerAdminCenter;
use App\Livewire\PeerRental\PeerStayEditor;
use App\Livewire\PeerRental\PeerVehicleEditor;
use App\Models\PeerStay;
use App\Models\PeerVehicle;
use App\Models\PeerVehicleDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LES PAPIERS D'UN LOGEMENT.
 *
 * Un logement se justifie comme un vehicule : une assurance, un titre qui prouve le droit de le
 * louer. La FILE D'ATTENTE est la meme — un administrateur ouvre le fichier, le valide ou le
 * refuse avec un motif — donc la table est devenue polymorphe plutot que d'etre ecrite deux fois.
 *
 * Chaque cas porte son temoin vehicule : le module vivant ne doit rien perdre au passage.
 */
class LesPapiersDUnLogementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ── Le depot ───────────────────────────────────────────────────────────

    public function test_le_proprietaire_depose_un_papier_sur_son_logement(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->set('typeDocument', PeerVehicleDocument::TYPE_TITRE)
            ->set('fichierDocument', UploadedFile::fake()->create('titre.pdf', 40, 'application/pdf'))
            ->call('deposerUnDocument')
            ->assertHasNoErrors();

        $papier = $logement->documents()->firstOrFail();

        $this->assertSame(PeerVehicleDocument::TYPE_TITRE, $papier->document_type);
        $this->assertSame(PeerVehicleDocument::STATUT_EN_REVUE, $papier->status);
        // LE BIEN EST DESIGNE PAR LES COLONNES POLYMORPHES, et par elles seules.
        $this->assertSame(PeerStay::class, $papier->documentable_type);
        $this->assertNull($papier->peer_vehicle_id);
    }

    /**
     * TEMOIN — le vehicule garde ses deux colonnes vraies.
     *
     * Depuis que la relation est polymorphe, l'editeur de vehicule n'ecrit plus `peer_vehicle_id`
     * lui-meme : un crochet le fait. Sans lui, le papier disparaitrait au retour en arriere de la
     * migration, qui supprime les lignes sans vehicule.
     */
    public function test_temoin_un_papier_de_vehicule_porte_toujours_ses_deux_colonnes(): void
    {
        $vehicule = PeerVehicle::factory()->create();

        Livewire::actingAs($vehicule->owner)
            ->test(PeerVehicleEditor::class, ['vehicle' => $vehicule])
            ->set('typeDocument', PeerVehicleDocument::TYPE_CARTE_GRISE)
            ->set('fichierDocument', UploadedFile::fake()->create('carte.pdf', 40, 'application/pdf'))
            ->call('deposerUnDocument')
            ->assertHasNoErrors();

        $papier = $vehicule->documents()->firstOrFail();

        $this->assertSame(PeerVehicle::class, $papier->documentable_type);
        $this->assertSame($vehicule->id, (int) $papier->peer_vehicle_id);
    }

    /** UN TYPE HORS LISTE EST REFUSE : la file d'attente ne trie pas de l'inconnu. */
    public function test_un_type_de_papier_inconnu_est_refuse(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->set('typeDocument', 'carte_de_fidelite')
            ->set('fichierDocument', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
            ->call('deposerUnDocument')
            ->assertHasErrors('typeDocument');

        $this->assertSame(0, $logement->documents()->count());
    }

    // ── Ce qui bloque la publication ───────────────────────────────────────

    /** SANS SES PAPIERS, L'ANNONCE NE PART PAS EN VERIFICATION. */
    public function test_les_papiers_manquants_bloquent_la_publication(): void
    {
        $logement = $this->logementComplet();

        $motifs = Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->instance()->motifsDeBlocage;

        $this->assertContains('Le document « Attestation d’assurance » doit être validé.', $motifs);
        $this->assertContains('Le document « Titre de propriété ou mandat de gestion » doit être validé.', $motifs);
    }

    /** TEMOIN — les deux papiers validés, plus aucun motif ne reste. */
    public function test_temoin_avec_ses_papiers_valides_l_annonce_ne_bloque_plus(): void
    {
        $logement = $this->logementComplet();

        foreach ($logement->typesDeDocumentsRequis() as $type) {
            $logement->documents()->create([
                'document_type' => $type,
                'status' => PeerVehicleDocument::STATUT_VALIDE,
                'file_path' => 'peer-documents/x.pdf',
            ]);
        }

        $motifs = Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->instance()->motifsDeBlocage;

        $this->assertSame([], $motifs);
    }

    /** UN PAPIER PERIME NE VAUT PAS MIEUX QU'UN PAPIER ABSENT. */
    public function test_un_papier_valide_mais_perime_bloque_encore(): void
    {
        $logement = $this->logementComplet();

        foreach ($logement->typesDeDocumentsRequis() as $type) {
            $logement->documents()->create([
                'document_type' => $type,
                'status' => PeerVehicleDocument::STATUT_VALIDE,
                'file_path' => 'peer-documents/x.pdf',
                'expires_at' => now()->subDay()->toDateString(),
            ]);
        }

        $motifs = Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->instance()->motifsDeBlocage;

        $this->assertNotEmpty($motifs);
    }

    // ── Le retrait ─────────────────────────────────────────────────────────

    /**
     * UN PAPIER VALIDE NE SE RETIRE PAS.
     *
     * Il justifie les sejours deja conclus : le faire disparaitre effacerait la preuve sur
     * laquelle la plateforme s'est engagee.
     */
    public function test_un_papier_valide_ne_se_retire_pas(): void
    {
        $logement = PeerStay::factory()->create();
        $papier = $logement->documents()->create([
            'document_type' => PeerVehicleDocument::TYPE_ASSURANCE,
            'status' => PeerVehicleDocument::STATUT_VALIDE,
            'file_path' => 'peer-documents/x.pdf',
        ]);

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->call('supprimerUnDocument', $papier->id)
            ->assertSet('erreur', 'Un document validé ne se retire pas.');

        $this->assertSame(1, $logement->documents()->count());
    }

    /** TEMOIN — un papier refuse se retire, pour en redeposer un lisible. */
    public function test_temoin_un_papier_refuse_se_retire(): void
    {
        $logement = PeerStay::factory()->create();
        $papier = $logement->documents()->create([
            'document_type' => PeerVehicleDocument::TYPE_ASSURANCE,
            'status' => PeerVehicleDocument::STATUT_REFUSE,
            'file_path' => 'peer-documents/x.pdf',
        ]);

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->call('supprimerUnDocument', $papier->id);

        $this->assertSame(0, $logement->documents()->count());
    }

    /** LE PAPIER D'UN AUTRE NE SE RETIRE PAS DEPUIS SON PROPRE ECRAN. */
    public function test_on_ne_retire_pas_le_papier_d_un_autre_logement(): void
    {
        $mien = PeerStay::factory()->create();
        $autre = PeerStay::factory()->create();
        $papier = $autre->documents()->create([
            'document_type' => PeerVehicleDocument::TYPE_ASSURANCE,
            'status' => PeerVehicleDocument::STATUT_REFUSE,
            'file_path' => 'peer-documents/x.pdf',
        ]);

        // LA REQUETE PART DE MON PROPRE LOGEMENT : le papier d'un autre n'y est pas, et
        // `firstOrFail` refuse plutot que de chercher ailleurs.
        $this->expectException(ModelNotFoundException::class);

        try {
            Livewire::actingAs($mien->owner)
                ->test(PeerStayEditor::class, ['stay' => $mien])
                ->call('supprimerUnDocument', $papier->id);
        } finally {
            $this->assertSame(1, $autre->documents()->count());
        }
    }

    // ── La file d'attente de l'administration ──────────────────────────────

    public function test_la_file_d_attente_montre_les_papiers_des_deux_biens(): void
    {
        $logement = PeerStay::factory()->create(['title' => 'Loft du canal']);
        $logement->documents()->create([
            'document_type' => PeerVehicleDocument::TYPE_TITRE,
            'status' => PeerVehicleDocument::STATUT_EN_REVUE,
            'file_path' => 'peer-documents/x.pdf',
        ]);

        $vehicule = PeerVehicle::factory()->create(['brand' => 'Renault', 'model' => 'Zoe']);
        $vehicule->documents()->create([
            'document_type' => PeerVehicleDocument::TYPE_CARTE_GRISE,
            'status' => PeerVehicleDocument::STATUT_EN_REVUE,
            'file_path' => 'peer-documents/y.pdf',
        ]);

        Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->set('onglet', 'papiers')
            ->assertSee('Loft du canal')
            ->assertSee('Renault Zoe');
    }

    public function test_l_administration_valide_le_papier_d_un_logement(): void
    {
        $logement = PeerStay::factory()->create();
        $papier = $logement->documents()->create([
            'document_type' => PeerVehicleDocument::TYPE_ASSURANCE,
            'status' => PeerVehicleDocument::STATUT_EN_REVUE,
            'file_path' => 'peer-documents/x.pdf',
        ]);

        Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->call('validerLePapier', $papier->id);

        $this->assertSame(PeerVehicleDocument::STATUT_VALIDE, $papier->fresh()->status);
    }

    // ── L'ouverture du fichier ─────────────────────────────────────────────

    /**
     * LE FICHIER NE SORT PAS DU DISQUE PRIVE.
     *
     * Un titre de propriete porte un nom, une adresse et parfois un numero national : un tiers
     * connecte ne doit pas pouvoir l'ouvrir en devinant un identifiant.
     */
    public function test_un_tiers_ne_peut_pas_ouvrir_le_papier_d_un_autre(): void
    {
        $papier = $this->papierDepose();

        $this->actingAs(User::factory()->create())
            ->get(route('peer.document', $papier))
            ->assertForbidden();
    }

    /** TEMOIN — le proprietaire ouvre le sien. */
    public function test_temoin_le_proprietaire_ouvre_son_propre_papier(): void
    {
        $papier = $this->papierDepose();

        $this->actingAs($papier->documentable->owner)
            ->get(route('peer.document', $papier))
            ->assertOk();
    }

    /** TEMOIN — l'administrateur qui doit l'examiner l'ouvre aussi. */
    public function test_temoin_l_administrateur_ouvre_le_papier_qu_il_examine(): void
    {
        $papier = $this->papierDepose();

        $this->actingAs($this->admin())
            ->get(route('peer.document', $papier))
            ->assertOk();
    }

    private function papierDepose(): PeerVehicleDocument
    {
        Storage::disk('local')->put('peer-documents/preuve.pdf', 'contenu');

        $logement = PeerStay::factory()->create();

        return $logement->documents()->create([
            'document_type' => PeerVehicleDocument::TYPE_ASSURANCE,
            'status' => PeerVehicleDocument::STATUT_EN_REVUE,
            'file_path' => 'peer-documents/preuve.pdf',
            'file_name' => 'preuve.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    /** Une annonce à qui il ne manque QUE ses papiers. */
    private function logementComplet(): PeerStay
    {
        $proprietaire = User::factory()->create(['stripe_connect_account_id' => 'acct_test', 'stripe_connect_status' => 'active']);

        $logement = PeerStay::factory()->create([
            'owner_id' => $proprietaire->id,
            'title' => 'Loft du canal',
            'description' => 'Un loft clair au bord du canal, deux chambres.',
            'city' => 'Bruxelles',
            'address_line' => 'Quai des Péniches 12',
            'nightly_price_cents' => 9000,
        ]);

        $logement->media()->create(['path' => 'peer-stays/1.jpg', 'position' => 0]);

        return $logement->fresh();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-peer-rentals'],
        ]);
    }
}
