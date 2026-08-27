<?php

namespace Tests\Feature\Media;

use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\User;
use App\Support\Media\PrivateMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L'APPLICATION AFFICHAIT UNE PAGE DE CONNEXION EN GUISE DE PHOTO.
 *
 * `media.private.show` exige une session EN PLUS de la signature. Une balise `Image` d'un téléphone
 * n'envoie ni cookie ni en-tête d'autorisation : mesuré, elle recevait `302 → /login`. Et
 * `MissionFieldScreen` rendait déjà ces URL depuis toujours — les photos de mission étaient donc
 * cassées dans l'application, sans que rien ne le dise.
 *
 * `media.private.device` porte sa seule preuve : signature HMAC sur l'URL entière, chemin et
 * expiration compris, quinze minutes. C'est le modèle d'une URL pré-signée d'un stockage objet.
 * LA ROUTE WEB N'EST PAS TOUCHÉE, et son témoin ci-dessous le vérifie.
 */
class LeMediaPriveSOuvreDepuisUnAppareilTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::disk('private')->putFileAs('missions', UploadedFile::fake()->image('p.jpg'), 'p.jpg');
    }

    public function test_le_lien_d_appareil_s_ouvre_sans_aucune_authentification(): void
    {
        $this->get((string) PrivateMedia::urlPourAppareil('missions/p.jpg'))->assertOk();
    }

    /** LE TÉMOIN : la porte du web garde son verrou de session. Rien n'a été affaibli là-bas. */
    public function test_temoin_le_lien_web_exige_toujours_une_session(): void
    {
        $this->get((string) PrivateMedia::url('missions/p.jpg'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->employe()->create())
            ->get((string) PrivateMedia::url('missions/p.jpg'))
            ->assertOk();
    }

    public function test_une_signature_falsifiee_ou_expiree_est_refusee(): void
    {
        $this->get(PrivateMedia::urlPourAppareil('missions/p.jpg').'x')->assertForbidden();

        $perime = URL::temporarySignedRoute('media.private.device', now()->subMinute(), [
            'path' => 'missions/p.jpg',
        ]);

        $this->get($perime)->assertForbidden();
    }

    /** Le lien ne vaut que pour le chemin qu'il nomme : la signature couvre la requête entière. */
    public function test_le_lien_d_un_fichier_n_en_ouvre_pas_un_autre(): void
    {
        Storage::disk('private')->putFileAs('missions', UploadedFile::fake()->image('autre.jpg'), 'autre.jpg');

        $lien = (string) PrivateMedia::urlPourAppareil('missions/p.jpg');

        // Le chemin voyage ENCODÉ dans la requête : remplacer la forme lisible ne changerait rien,
        // et le test passerait au vert en rejouant le lien d'origine.
        $detourne = str_replace(rawurlencode('missions/p.jpg'), rawurlencode('missions/autre.jpg'), $lien);

        $this->assertNotSame($lien, $detourne, 'Le chemin n’a pas été détourné : ce cas ne mesurerait rien.');

        $this->get($detourne)->assertForbidden();
    }

    /**
     * LA PROPRIÉTÉ QUI COMPTE, mesurée de bout en bout : une URL servie à un porteur de jeton doit
     * s'ouvrir SANS session. C'est elle qui attrapera le prochain point d'API qui sert le mauvais
     * lien, quelle que soit la fonction employée pour le construire.
     */
    public function test_les_liens_servis_par_l_api_s_ouvrent_depuis_un_appareil(): void
    {
        $prestataire = User::factory()->employe()->create();

        $dossier = ComplaintCase::factory()->create([
            'provider_user_id' => $prestataire->id,
            'status' => ComplaintCase::STATUS_OPEN,
            'attachments' => [['path' => 'missions/p.jpg', 'original_name' => 'p.jpg']],
        ]);

        DisputeEvent::factory()->create([
            'complaint_case_id' => $dossier->id,
            'visibility' => DisputeEvent::VISIBILITY_ALL,
            'attachments' => [['path' => 'missions/p.jpg', 'original_name' => 'p.jpg']],
        ]);

        Sanctum::actingAs($prestataire);

        $charge = $this->getJson("/api/provider/disputes/{$dossier->id}")->assertOk()->json('data');

        $liens = array_filter([
            $charge['attachments'][0]['url'] ?? null,
            $charge['events'][0]['attachments'][0]['url'] ?? null,
        ]);

        $this->assertCount(2, $liens, 'L’API ne sert pas de lien pour les pièces jointes.');

        // On repart d'une application SANS session, comme une balise `Image` le ferait.
        $this->app['auth']->forgetGuards();

        foreach ($liens as $lien) {
            $this->get($lien)->assertOk();
        }
    }
}
