<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Events\CallStarted;
use App\Jobs\Calls\CloreLAppelNonRepondu;
use App\Models\Call;
use App\Models\Channel;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Calls\CallService;
use App\Services\Messaging\ChannelManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * LOT 8 — APPELER DEPUIS UN CANAL D'ÉQUIPE.
 *
 * GREENFIELD TOTAL : `VideoCallService` était un squelette qui levait sur chaque méthode, et
 * `MaskedCallService` (Twilio Proxy) — complet mais jamais câblé — répond à un autre besoin, masquer
 * les numéros entre client et prestataire. Il reste intact.
 *
 * La note vocale du lot 7 couvre la consigne qu'on laisse ; un appel couvre la question qui n'attend
 * pas — « je suis devant la porte, quel est le code ? ».
 */
class AppelsCanalTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        // Sans clé, le service refuse de servir : c'est le sujet d'un test à part.
        config()->set('livekit.url', 'wss://livekit.test');
        config()->set('livekit.api_key', 'cle-de-test');
        config()->set('livekit.api_secret', 'secret-de-test');
    }

    private function membre(OrganizationRole $role = OrganizationRole::WORKER): User
    {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $this->org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $this->org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $this->org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    private function canalAvec(User $proprietaire, ?User $autre = null): Channel
    {
        $canal = app(ChannelManagementService::class)
            ->creer($proprietaire, $this->org->id, 'Équipe Nord');

        if ($autre !== null) {
            app(ChannelManagementService::class)->ajouterMembre($canal, $autre->id);
        }

        return $canal;
    }

    // ──────────────────────────────────────────────────────
    // Les jetons
    // ──────────────────────────────────────────────────────

    public function test_le_jeton_est_un_jwt_signe_qui_porte_la_salle(): void
    {
        /*
         * Un jeton LiveKit est un JWT HS256 dont la charge décrit la salle et les droits. On le
         * signe ici plutôt que d'ajouter un SDK : le format est entièrement maîtrisé, et une
         * dépendance de plus serait une surface de sécurité de plus.
         */
        $appelant = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($appelant);

        $service = app(CallService::class);
        $appel = $service->ouvrir($canal, $appelant);

        $jeton = $service->jetonPour($appel, $appelant);

        [$entete, $corps, $signature] = explode('.', $jeton);

        $charge = json_decode(base64_decode(strtr($corps, '-_', '+/')), true);

        $this->assertSame($appel->room_name, $charge['video']['room']);
        $this->assertTrue($charge['video']['roomJoin']);
        $this->assertSame('cle-de-test', $charge['iss']);

        // La signature doit correspondre : sinon le serveur LiveKit rejette, et personne n'entre.
        $attendue = rtrim(strtr(base64_encode(
            hash_hmac('sha256', $entete.'.'.$corps, 'secret-de-test', true)
        ), '+/', '-_'), '=');

        $this->assertSame($attendue, $signature);
    }

    public function test_le_jeton_ne_donne_pas_les_droits_d_administration_de_salle(): void
    {
        /*
         * PAS DE `roomCreate`, PAS DE `roomAdmin`. Le participant rejoint une salle que le SERVEUR a
         * nommée ; lui donner le droit d'en créer laisserait n'importe quel client ouvrir des
         * salles hors de toute trace côté produit.
         */
        $appelant = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($appelant);

        $service = app(CallService::class);
        $jeton = $service->jetonPour($service->ouvrir($canal, $appelant), $appelant);

        $charge = json_decode(base64_decode(strtr(explode('.', $jeton)[1], '-_', '+/')), true);

        $this->assertArrayNotHasKey('roomCreate', $charge['video']);
        $this->assertArrayNotHasKey('roomAdmin', $charge['video']);
    }

    public function test_deux_appels_successifs_n_ont_pas_la_meme_salle(): void
    {
        // Sinon un participant en retard rejoindrait la conversation PRÉCÉDENTE.
        $appelant = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($appelant);

        $service = app(CallService::class);

        $this->assertNotSame(
            $service->ouvrir($canal, $appelant)->room_name,
            $service->ouvrir($canal, $appelant)->room_name,
        );
    }

    // ──────────────────────────────────────────────────────
    // La machine à états
    // ──────────────────────────────────────────────────────

    public function test_ca_sonne_puis_c_est_actif_puis_c_est_termine(): void
    {
        $appelant = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($appelant);

        $service = app(CallService::class);
        $appel = $service->ouvrir($canal, $appelant);

        $this->assertSame(Call::STATUS_RINGING, $appel->status);

        $service->repondre($appel);
        $this->assertSame(Call::STATUS_ACTIVE, $appel->fresh()->status);

        $service->terminer($appel->fresh());
        $this->assertSame(Call::STATUS_ENDED, $appel->fresh()->status);
    }

    public function test_un_appel_qui_sonnait_encore_devient_manque(): void
    {
        /*
         * LA DISTINCTION EST TOUT CE QUI COMPTE quand on reprend son téléphone : « terminé » se lit
         * comme une conversation qui a eu lieu. C'est aussi le seul état que le serveur de médias ne
         * connaît pas — LiveKit sait qui est dans une salle à l'instant T, pas qu'un appel a sonné
         * dans le vide à 7 h du matin.
         */
        $appelant = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($appelant);

        $service = app(CallService::class);
        $appel = $service->ouvrir($canal, $appelant);

        $service->terminer($appel);

        $this->assertSame(Call::STATUS_MISSED, $appel->fresh()->status);
    }

    public function test_le_delai_de_sonnerie_marque_l_appel_manque(): void
    {
        $appelant = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($appelant);

        $appel = app(CallService::class)->ouvrir($canal, $appelant);

        (new CloreLAppelNonRepondu($appel->id))->handle(app(CallService::class));

        $this->assertSame(Call::STATUS_MISSED, $appel->fresh()->status);
    }

    public function test_le_delai_ne_touche_pas_un_appel_deja_decroche(): void
    {
        // Idempotent par construction : le job peut être rejoué, ou arriver après qu'on a
        // décroché, sans rien réécrire.
        $appelant = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($appelant);

        $service = app(CallService::class);
        $appel = $service->ouvrir($canal, $appelant);
        $service->repondre($appel);

        (new CloreLAppelNonRepondu($appel->id))->handle($service);

        $this->assertSame(Call::STATUS_ACTIVE, $appel->fresh()->status);
    }

    // ──────────────────────────────────────────────────────
    // L'API
    // ──────────────────────────────────────────────────────

    public function test_ouvrir_un_appel_diffuse_la_banniere_et_arme_le_delai(): void
    {
        Event::fake([CallStarted::class]);
        Queue::fake();

        $appelant = $this->membre(OrganizationRole::OWNER);
        $collegue = $this->membre();
        $canal = $this->canalAvec($appelant, $collegue);

        $reponse = $this->actingAs($appelant, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/calls", ['type' => 'audio'])
            ->assertCreated()
            ->json('data');

        $this->assertNotEmpty($reponse['token']);
        $this->assertSame('wss://livekit.test', $reponse['url']);

        Event::assertDispatched(CallStarted::class);
        Queue::assertPushed(CloreLAppelNonRepondu::class);
    }

    public function test_demander_son_jeton_vaut_decrocher(): void
    {
        $appelant = $this->membre(OrganizationRole::OWNER);
        $collegue = $this->membre();
        $canal = $this->canalAvec($appelant, $collegue);

        $appel = app(CallService::class)->ouvrir($canal, $appelant);

        $this->actingAs($collegue, 'sanctum')
            ->postJson("/api/provider/company/calls/{$appel->id}/token")
            ->assertOk();

        $this->assertSame(Call::STATUS_ACTIVE, $appel->fresh()->status);
    }

    public function test_l_appelant_qui_reprend_son_jeton_ne_decroche_pas_a_la_place_des_autres(): void
    {
        // Sinon un appel passerait « actif » sans que personne ait répondu, et la sonnerie
        // s'arrêterait chez l'appelant alors que personne n'est là.
        $appelant = $this->membre(OrganizationRole::OWNER);
        $collegue = $this->membre();
        $canal = $this->canalAvec($appelant, $collegue);

        $appel = app(CallService::class)->ouvrir($canal, $appelant);

        $this->actingAs($appelant, 'sanctum')
            ->postJson("/api/provider/company/calls/{$appel->id}/token")
            ->assertOk();

        $this->assertSame(Call::STATUS_RINGING, $appel->fresh()->status);
    }

    public function test_un_non_membre_du_canal_n_appelle_ni_ne_rejoint(): void
    {
        $appelant = $this->membre(OrganizationRole::OWNER);
        $etranger = $this->membre();
        $canal = $this->canalAvec($appelant);

        $this->actingAs($etranger, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/calls")
            ->assertForbidden();

        $appel = app(CallService::class)->ouvrir($canal, $appelant);

        $this->actingAs($etranger, 'sanctum')
            ->postJson("/api/provider/company/calls/{$appel->id}/token")
            ->assertForbidden();
    }

    public function test_une_societe_ne_rejoint_pas_l_appel_d_une_autre(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $leurProprietaire = User::factory()->employe()->create(['current_organization_id' => $autreOrg->id]);
        $leurProprietaire->providerProfile()->create([
            'organization_account_id' => $autreOrg->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_account_id' => $autreOrg->id,
            'user_id' => $leurProprietaire->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $leurCanal = app(ChannelManagementService::class)
            ->creer($leurProprietaire, $autreOrg->id, 'Leur équipe');

        $leurAppel = app(CallService::class)->ouvrir($leurCanal, $leurProprietaire);

        $curieux = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($curieux, 'sanctum')
            ->postJson("/api/provider/company/calls/{$leurAppel->id}/token")
            ->assertNotFound();
    }

    public function test_on_ne_rejoint_pas_un_appel_termine(): void
    {
        $appelant = $this->membre(OrganizationRole::OWNER);
        $collegue = $this->membre();
        $canal = $this->canalAvec($appelant, $collegue);

        $service = app(CallService::class);
        $appel = $service->ouvrir($canal, $appelant);
        $service->repondre($appel);
        $service->terminer($appel->fresh());

        $this->actingAs($collegue, 'sanctum')
            ->postJson("/api/provider/company/calls/{$appel->id}/token")
            ->assertStatus(410);
    }

    public function test_sans_cle_livekit_l_appel_est_refuse_explicitement(): void
    {
        /*
         * Un jeton signé avec un secret vide serait rejeté par le serveur LiveKit : mieux vaut un
         * refus explicite qu'un appel qui échoue à la connexion, sans que personne comprenne
         * pourquoi.
         */
        config()->set('livekit.api_secret', null);

        $appelant = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($appelant);

        $this->actingAs($appelant, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/calls")
            ->assertStatus(503);
    }
}
