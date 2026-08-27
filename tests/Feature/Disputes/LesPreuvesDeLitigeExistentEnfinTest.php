<?php

namespace Tests\Feature\Disputes;

use App\Livewire\Provider\ProviderDisputesPage;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\User;
use App\Support\Disputes\PreuvesDeLitige;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LES LITIGES v2 DÉCLARAIENT DES PIÈCES JOINTES QUE RIEN NE POUVAIT REMPLIR.
 *
 * `DisputeService` les acceptait — `open($user, ['attachments' => …])` et un sixième paramètre
 * `array $attachments = []` sur `addMessage()`. Les SIX appelants passaient au plus cinq
 * arguments, `StoreDisputeRequest` n'avait aucune règle, et les deux fabriques posaient `[]`.
 * Colonnes castées, type d'événement `attachment_added` déclaré, zéro ligne en base.
 *
 * DISQUE PRIVÉ, URL SIGNÉE : une preuve montre un logement, un dégât, parfois une personne.
 */
class LesPreuvesDeLitigeExistentEnfinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PreuvesDeLitige::DISQUE);
    }

    public function test_le_stockage_pose_la_forme_attendue_sur_le_disque_prive(): void
    {
        $stockees = PreuvesDeLitige::stocker([UploadedFile::fake()->image('degat.jpg')]);

        $this->assertCount(1, $stockees);
        $this->assertSame('degat.jpg', $stockees[0]['original_name']);
        $this->assertStringStartsWith(PreuvesDeLitige::DOSSIER.'/', $stockees[0]['path']);

        Storage::disk(PreuvesDeLitige::DISQUE)->assertExists($stockees[0]['path']);
    }

    public function test_un_client_ouvre_un_litige_avec_ses_preuves(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->postJson('/api/client/disputes', [
            'subject' => 'Prestataire absent',
            'description' => 'Personne n’est venu au rendez-vous prévu ce matin.',
            'category' => 'no_show',
            'attachments' => [UploadedFile::fake()->image('preuve.jpg')],
        ])->assertCreated();

        $case = ComplaintCase::query()->firstOrFail();

        $this->assertCount(1, $case->attachments);
        Storage::disk(PreuvesDeLitige::DISQUE)->assertExists($case->attachments[0]['path']);
    }

    /** LE TÉMOIN : sans pièce jointe, l'ouverture marche toujours et n'invente rien. */
    public function test_temoin_une_ouverture_sans_preuve_reste_possible(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->postJson('/api/client/disputes', [
            'subject' => 'Retard important',
            'description' => 'Le prestataire est arrivé avec deux heures de retard.',
            'category' => 'quality',
        ])->assertCreated();

        $this->assertSame([], ComplaintCase::query()->firstOrFail()->attachments);
    }

    /** Un fichier qui n'est pas une image est refusé — et le témoin ci-dessus prouve que le chemin marche. */
    public function test_un_fichier_qui_n_est_pas_une_image_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->postJson('/api/client/disputes', [
            'subject' => 'Tentative',
            'description' => 'Une description assez longue pour passer la validation.',
            'category' => 'other',
            'attachments' => [UploadedFile::fake()->create('charge.exe', 40)],
        ])->assertStatus(422);

        $this->assertSame(0, ComplaintCase::query()->count());
    }

    /**
     * LE POINT QUI COMPTE. Une preuve jointe à un événement PRIVÉ ne doit jamais atteindre le
     * prestataire : `visibleTo(ROLE_PROVIDER)` filtre à la requête, donc la pièce ne remonte
     * même pas jusqu'à la vue.
     */
    public function test_une_preuve_d_evenement_prive_n_atteint_pas_le_prestataire(): void
    {
        [$prestataire, $dossier] = $this->dossierAvecPrestataire();

        DisputeEvent::factory()->create([
            'complaint_case_id' => $dossier->id,
            'visibility' => DisputeEvent::VISIBILITY_PRIVATE,
            'body' => 'Note interne du support.',
            'attachments' => [['path' => 'disputes/interne.jpg', 'original_name' => 'interne.jpg']],
        ]);

        $html = Livewire::actingAs($prestataire)
            ->test(ProviderDisputesPage::class)
            ->call('select', $dossier->id)
            ->html();

        $this->assertStringNotContainsString('interne.jpg', $html);
    }

    /** SON TÉMOIN : une pièce visible de tous, elle, arrive — et par une URL signée. */
    public function test_temoin_une_preuve_visible_de_tous_arrive_par_une_url_signee(): void
    {
        [$prestataire, $dossier] = $this->dossierAvecPrestataire();

        DisputeEvent::factory()->create([
            'complaint_case_id' => $dossier->id,
            'visibility' => DisputeEvent::VISIBILITY_ALL,
            'body' => 'Voici la photo du dégât.',
            'attachments' => [['path' => 'disputes/partagee.jpg', 'original_name' => 'partagee.jpg']],
        ]);

        $html = Livewire::actingAs($prestataire)
            ->test(ProviderDisputesPage::class)
            ->call('select', $dossier->id)
            ->html();

        $this->assertStringContainsString('media/private', $html);
        $this->assertStringContainsString('signature=', $html);
        $this->assertStringNotContainsString('/storage/disputes', $html);
    }

    /** @return array{0: User, 1: ComplaintCase} */
    private function dossierAvecPrestataire(): array
    {
        $prestataire = User::factory()->employe()->create();

        $dossier = ComplaintCase::factory()->create([
            'provider_user_id' => $prestataire->id,
            'status' => ComplaintCase::STATUS_OPEN,
        ]);

        return [$prestataire, $dossier];
    }
}
