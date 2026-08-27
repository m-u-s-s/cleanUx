<?php

namespace Tests\Feature\Api\Provider;

use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\User;
use App\Support\Disputes\PreuvesDeLitige;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L'APPLICATION PRESTATAIRE NE POUVAIT QUE LISTER.
 *
 * `GET /provider/disputes` rendait des dossiers, `POST /{dispute}/respond` acceptait une réponse —
 * et rien entre les deux. Répondre depuis le téléphone demandait donc d'écrire à l'aveugle, sans
 * voir ce que le client avait dit ni ce qu'il avait joint.
 *
 * CE QUE LE DÉTAIL NE DOIT PAS LAISSER PASSER : `visibleTo(ROLE_PROVIDER)` filtre à la requête.
 * Une note interne du support ne remonte ni par son texte, ni par ses pièces jointes.
 */
class LePrestataireVoitSonLitigeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: ComplaintCase} */
    private function dossier(): array
    {
        $prestataire = User::factory()->employe()->create();

        $dossier = ComplaintCase::factory()->create([
            'provider_user_id' => $prestataire->id,
            'status' => ComplaintCase::STATUS_OPEN,
        ]);

        return [$prestataire, $dossier];
    }

    public function test_le_detail_rend_le_dossier_et_son_fil(): void
    {
        [$prestataire, $dossier] = $this->dossier();

        DisputeEvent::factory()->create([
            'complaint_case_id' => $dossier->id,
            'visibility' => DisputeEvent::VISIBILITY_ALL,
            'body' => 'Le salon est resté sale.',
        ]);

        Sanctum::actingAs($prestataire);

        $this->getJson("/api/provider/disputes/{$dossier->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $dossier->id)
            ->assertJsonPath('data.events.0.body', 'Le salon est resté sale.');
    }

    /** Une note interne ne franchit pas la requête — ni son texte, ni ses preuves. */
    public function test_une_note_interne_ne_remonte_pas(): void
    {
        [$prestataire, $dossier] = $this->dossier();

        DisputeEvent::factory()->create([
            'complaint_case_id' => $dossier->id,
            'visibility' => DisputeEvent::VISIBILITY_PRIVATE,
            'body' => 'Note interne du support.',
            'attachments' => [['path' => 'disputes/interne.jpg', 'original_name' => 'interne.jpg']],
        ]);

        Sanctum::actingAs($prestataire);

        $reponse = $this->getJson("/api/provider/disputes/{$dossier->id}")->assertOk();

        $this->assertStringNotContainsString('Note interne', $reponse->getContent() ?: '');
        $this->assertStringNotContainsString('interne.jpg', $reponse->getContent() ?: '');
    }

    /** LE TÉMOIN du filtre : un message visible de tous arrive, lui, avec sa pièce jointe. */
    public function test_temoin_un_message_partage_arrive_avec_sa_piece(): void
    {
        [$prestataire, $dossier] = $this->dossier();

        DisputeEvent::factory()->create([
            'complaint_case_id' => $dossier->id,
            'visibility' => DisputeEvent::VISIBILITY_ALL,
            'body' => 'Voici la photo.',
            'attachments' => [['path' => 'disputes/partagee.jpg', 'original_name' => 'partagee.jpg']],
        ]);

        Sanctum::actingAs($prestataire);

        $this->getJson("/api/provider/disputes/{$dossier->id}")
            ->assertOk()
            ->assertJsonPath('data.events.0.attachments.0.original_name', 'partagee.jpg');
    }

    public function test_le_litige_d_un_autre_prestataire_est_refuse(): void
    {
        [, $dossier] = $this->dossier();

        Sanctum::actingAs(User::factory()->employe()->create());

        $this->getJson("/api/provider/disputes/{$dossier->id}")->assertForbidden();
    }

    /** Répondre depuis le téléphone, avec ses preuves. */
    public function test_le_prestataire_repond_avec_une_preuve(): void
    {
        Storage::fake(PreuvesDeLitige::DISQUE);

        [$prestataire, $dossier] = $this->dossier();

        Sanctum::actingAs($prestataire);

        $this->postJson("/api/provider/disputes/{$dossier->id}/respond", [
            'body' => 'Voici l’état des lieux à mon départ.',
            'attachments' => [UploadedFile::fake()->image('depart.jpg')],
        ])->assertCreated();

        $evenement = DisputeEvent::query()
            ->where('complaint_case_id', $dossier->id)
            ->where('author_role', DisputeEvent::ROLE_PROVIDER)
            ->firstOrFail();

        $this->assertCount(1, $evenement->attachments);
        Storage::disk(PreuvesDeLitige::DISQUE)->assertExists($evenement->attachments[0]['path']);
    }
}
