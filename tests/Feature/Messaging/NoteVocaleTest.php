<?php

namespace Tests\Feature\Messaging;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LA NOTE VOCALE : ENVOYÉE D'UN CÔTÉ, ÉCOUTÉE DE L'AUTRE.
 *
 * L'ENVOI EXISTAIT, L'ÉCOUTE NON. Le terrain pouvait enregistrer depuis son téléphone depuis le lot
 * 8 ; la réponse de l'API ne disait ni que le message était vocal, ni où trouver le son. Le fil
 * affichait « 🎙️ Note vocale » comme une phrase ordinaire, sur mobile comme sur le web. Une
 * messagerie vocale à sens unique se fait remplacer par WhatsApp — hors de l'outil, hors de toute
 * trace, hors de la modération.
 *
 * ET LE SCAN ANTIVIRUS ÉTAIT UNE PROMESSE EN COMMENTAIRE. Le contrôleur écrivait « le fichier passe
 * par le même chemin que les autres pièces jointes : même disque, même plafond, même scan » — et
 * appelait `store()`, qui ne déclenche rien. Une seconde porte d'entrée de fichiers, sans analyse,
 * sur une messagerie d'équipe. Le fichier passe désormais par `AttachmentUploadService`, ce qui lui
 * donne d'un coup le scan, le refus de lecture si infecté, et la route de téléchargement signée qui
 * vérifie déjà l'appartenance au canal.
 */
class NoteVocaleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Channel} */
    private function membreEtCanal(): array
    {
        // Un canal d'équipe prestataire appartient à une organisation PRESTATAIRE.
        // La fabrique crée une organisation cliente par défaut : le test appelait
        // donc l'API société prestataire avec une société cliente, ce que
        // `org.type:provider` refuse désormais — à juste titre.
        $organisation = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'organization_account_id' => $organisation->id,
            'current_organization_id' => $organisation->id,
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $canal = Channel::query()->create([
            'organization_account_id' => $organisation->id,
            'name' => 'Chantier',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $user->id,
        ]);

        $canal->members()->attach($user->id);

        return [$user, $canal];
    }

    private function son(): UploadedFile
    {
        // `UploadedFile::fake()->create()` avec un type explicite : c'est le type RÉEL que le
        // serveur vérifie, pas l'extension du nom.
        return UploadedFile::fake()->create('note.m4a', 120, 'audio/mp4');
    }

    #[Test]
    public function la_note_part_et_devient_une_piece_jointe_analysee(): void
    {
        Storage::fake('public');
        [$user, $canal] = $this->membreEtCanal();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/voice", [
                'audio' => $this->son(),
                'duration' => 12,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', Message::TYPE_VOICE);

        $message = Message::query()->where('channel_id', $canal->id)->firstOrFail();

        // LA PIÈCE JOINTE EST LA PREUVE que le fichier est passé par le service — et donc qu'il est
        // soumis au scan, au plafond audio et à la route signée. Un `store()` direct n'aurait laissé
        // aucune ligne ici.
        $this->assertSame(Message::TYPE_VOICE, $message->type);
        $this->assertSame(12, (int) data_get($message->metadata, 'duration'));
        $this->assertSame(1, MessageAttachment::query()->where('message_id', $message->id)->count());
    }

    #[Test]
    public function le_fil_dit_que_le_message_est_vocal_et_ou_l_ecouter(): void
    {
        Storage::fake('public');
        [$user, $canal] = $this->membreEtCanal();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/voice", [
                'audio' => $this->son(),
                'duration' => 8,
            ])
            ->assertCreated();

        $reponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/provider/company/channels/{$canal->id}/messages")
            ->assertOk();

        /*
         * C'EST L'ASSERTION QUI FERMAIT LE TROU. Sans le type, l'application affiche un texte ; sans
         * l'adresse, le bouton d'écoute n'a rien à ouvrir. Vérifier seulement que le message existe
         * aurait été vert pendant tout le temps où personne ne pouvait écouter.
         */
        $premier = $reponse->json('data.0');

        $this->assertSame(Message::TYPE_VOICE, $premier['type']);
        $this->assertSame(8, $premier['duration']);
        $this->assertNotNull($premier['audio_url']);
        $this->assertStringContainsString('signature=', (string) $premier['audio_url']);
    }

    #[Test]
    public function un_message_texte_ne_porte_pas_d_adresse_audio(): void
    {
        [$user, $canal] = $this->membreEtCanal();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/messages", ['content' => 'Bonjour'])
            ->assertCreated();

        $premier = $this->actingAs($user, 'sanctum')
            ->getJson("/api/provider/company/channels/{$canal->id}/messages")
            ->json('data.0');

        $this->assertNull($premier['audio_url']);
    }

    #[Test]
    public function un_fichier_qui_n_est_pas_de_l_audio_est_refuse(): void
    {
        Storage::fake('public');
        [$user, $canal] = $this->membreEtCanal();

        // Un exécutable renommé `.m4a` : la validation regarde le contenu, pas l'extension.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/voice", [
                'audio' => UploadedFile::fake()->create('note.m4a', 10, 'application/x-msdownload'),
            ])
            ->assertStatus(422);

        $this->assertSame(0, Message::query()->where('channel_id', $canal->id)->count());
    }

    #[Test]
    public function un_etranger_au_canal_ne_peut_pas_y_deposer_de_note(): void
    {
        Storage::fake('public');
        [, $canal] = $this->membreEtCanal();
        $intrus = User::factory()->create();

        $this->actingAs($intrus, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/voice", ['audio' => $this->son()])
            ->assertForbidden();
    }

    #[Test]
    public function une_note_trop_lourde_est_refusee(): void
    {
        Storage::fake('public');
        [$user, $canal] = $this->membreEtCanal();

        // Le plafond audio est de 5 Mo, bien plus bas que celui des documents : une note de trente
        // secondes pèse quelques centaines de kilo-octets, et accepter vingt-cinq mégaoctets
        // laisserait passer un fichier renommé.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/voice", [
                'audio' => UploadedFile::fake()->create('longue.m4a', 6000, 'audio/mp4'),
            ])
            ->assertStatus(422);
    }
}
