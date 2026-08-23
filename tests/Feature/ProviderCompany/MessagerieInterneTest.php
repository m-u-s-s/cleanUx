<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Channel;
use App\Models\Message;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Messaging\ChannelManagementService;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** LOT 7 — LA MESSAGERIE INTERNE, DEPUIS LE TERRAIN. */
class MessagerieInterneTest extends TestCase
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
    }

    private function membre(OrganizationRole $role = OrganizationRole::WORKER, ?OrganizationAccount $org = null): User
    {
        $org ??= $this->org;

        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    // ──────────────────────────────────────────────────────
    // Créer, et ouvrir une conversation à deux
    // ──────────────────────────────────────────────────────

    public function test_on_cree_un_canal_depuis_le_mobile(): void
    {
        $acteur = $this->membre();

        $this->actingAs($acteur, 'sanctum')
            ->postJson('/api/provider/company/channels', ['name' => 'Chantier Tilleuls'])
            ->assertCreated();

        $this->assertDatabaseHas('channels', [
            'organization_account_id' => $this->org->id,
            'name' => 'Chantier Tilleuls',
        ]);
    }

    public function test_la_conversation_a_deux_se_retrouve_au_lieu_de_se_dupliquer(): void
    {
        // ON CHERCHE AVANT DE CRÉER, et c'est le cœur du sujet.
        $ana = $this->membre();
        $patron = $this->membre(OrganizationRole::OWNER);

        $premier = $this->actingAs($patron, 'sanctum')
            ->postJson('/api/provider/company/channels/direct', ['user_id' => $ana->id])
            ->assertOk()
            ->json('data.id');

        // Ana répond depuis SON téléphone : elle doit tomber sur le MÊME fil.
        $second = $this->actingAs($ana, 'sanctum')
            ->postJson('/api/provider/company/channels/direct', ['user_id' => $patron->id])
            ->assertOk()
            ->json('data.id');

        $this->assertSame($premier, $second);
        $this->assertSame(1, Channel::where('type', 'private')->count());
    }

    public function test_on_n_ouvre_pas_de_conversation_avec_l_employe_d_une_autre_societe(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $acteur = $this->membre();
        $etranger = $this->membre(OrganizationRole::WORKER, $autreOrg);

        $this->actingAs($acteur, 'sanctum')
            ->postJson('/api/provider/company/channels/direct', ['user_id' => $etranger->id])
            ->assertNotFound();
    }

    // ──────────────────────────────────────────────────────
    // Les participants, en deux gestes
    // ──────────────────────────────────────────────────────

    private function canalAvec(User $proprietaire): Channel
    {
        return app(ChannelManagementService::class)
            ->creer($proprietaire, $this->org->id, 'Équipe Nord');
    }

    public function test_on_ajoute_puis_retire_un_participant(): void
    {
        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $collegue = $this->membre();
        $canal = $this->canalAvec($proprietaire);

        $this->actingAs($proprietaire, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/members", ['user_id' => $collegue->id])
            ->assertCreated();

        $this->assertDatabaseHas('channel_members', [
            'channel_id' => $canal->id,
            'user_id' => $collegue->id,
        ]);

        $this->actingAs($proprietaire, 'sanctum')
            ->deleteJson("/api/provider/company/channels/{$canal->id}/members/{$collegue->id}")
            ->assertOk();

        // RETIRER COUPE AUSSI L'ACCÈS TEMPS RÉEL : l'autorisation Reverb `channel.{id}` vérifie l'appartenance à chaque abonnement.
        $this->assertDatabaseMissing('channel_members', [
            'channel_id' => $canal->id,
            'user_id' => $collegue->id,
        ]);
    }

    public function test_on_n_enrole_pas_l_employe_d_une_autre_societe_dans_un_canal(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $etranger = $this->membre(OrganizationRole::WORKER, $autreOrg);
        $canal = $this->canalAvec($proprietaire);

        $this->actingAs($proprietaire, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/members", ['user_id' => $etranger->id])
            ->assertNotFound();
    }

    public function test_un_simple_membre_n_ajoute_personne(): void
    {
        // C'est le rôle DANS LE CANAL qui décide qui y entre — pas une clé d'organisation : le
        // propriétaire d'une conversation à deux n'a aucune clé, et un gestionnaire de la société
        // n'est pas forcément dans le fil.
        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $simple = $this->membre();
        $autre = $this->membre();

        $canal = $this->canalAvec($proprietaire);
        app(ChannelManagementService::class)->ajouterMembre($canal, $simple->id);

        $this->actingAs($simple, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/members", ['user_id' => $autre->id])
            ->assertForbidden();
    }

    public function test_une_societe_ne_voit_ni_ne_rejoint_le_canal_d_une_autre(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $leurProprietaire = $this->membre(OrganizationRole::OWNER, $autreOrg);
        $leurCanal = app(ChannelManagementService::class)
            ->creer($leurProprietaire, $autreOrg->id, 'Leur équipe');

        $curieux = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($curieux, 'sanctum')
            ->getJson("/api/provider/company/channels/{$leurCanal->id}/messages")
            ->assertNotFound();

        $this->actingAs($curieux, 'sanctum')
            ->postJson("/api/provider/company/channels/{$leurCanal->id}/members", ['user_id' => $curieux->id])
            ->assertNotFound();
    }

    public function test_on_quitte_un_canal_sans_demander_la_permission(): void
    {
        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $collegue = $this->membre();
        $canal = $this->canalAvec($proprietaire);

        app(ChannelManagementService::class)->ajouterMembre($canal, $collegue->id);

        $this->actingAs($collegue, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/leave")
            ->assertOk();

        $this->assertDatabaseMissing('channel_members', [
            'channel_id' => $canal->id,
            'user_id' => $collegue->id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Non-lus
    // ──────────────────────────────────────────────────────

    public function test_les_non_lus_comptent_les_messages_des_autres_et_pas_les_siens(): void
    {
        // `channel_members.last_read_at` existait depuis l'origine et n'était écrit par PERSONNE : les non-lus ne pouvaient donc pas exister, et la liste des canaux ne disait jamais où il se passait quelque chose.
        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $collegue = $this->membre();
        $canal = $this->canalAvec($proprietaire);

        app(ChannelManagementService::class)->ajouterMembre($canal, $collegue->id);

        app(MessageService::class)->send($canal, $proprietaire, 'Rendez-vous à 8 h');
        app(MessageService::class)->send($canal, $collegue, 'Bien reçu');

        $nonLus = $this->actingAs($collegue, 'sanctum')
            ->getJson('/api/provider/company/channels/unread-counts')
            ->assertOk()
            ->json('data');

        // Un seul : le sien ne compte pas — se compter soi-même afficherait un badge à chaque fois
        // qu'on parle. Et la ligne système de création n'est pas un message.
        $this->assertSame(1, $nonLus[$canal->id]);
    }

    public function test_marquer_comme_lu_remet_le_compteur_a_zero(): void
    {
        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $collegue = $this->membre();
        $canal = $this->canalAvec($proprietaire);

        app(ChannelManagementService::class)->ajouterMembre($canal, $collegue->id);
        app(MessageService::class)->send($canal, $proprietaire, 'Rendez-vous à 8 h');

        $this->actingAs($collegue, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/read")
            ->assertOk();

        $nonLus = $this->actingAs($collegue, 'sanctum')
            ->getJson('/api/provider/company/channels/unread-counts')
            ->json('data');

        $this->assertSame(0, $nonLus[$canal->id]);
    }

    // ──────────────────────────────────────────────────────
    // Modération
    // ──────────────────────────────────────────────────────

    public function test_la_moderation_caviarde_les_donnees_personnelles(): void
    {
        // Une équipe pouvait coller le numéro de carte d'un client dans un canal interne sans que rien ne le voie.
        config()->set('messaging.moderation.channels', true);

        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($proprietaire);

        $message = app(MessageService::class)
            ->send($canal, $proprietaire, 'Appelle le client au 0470 12 34 56');

        $this->assertStringNotContainsString('0470 12 34 56', $message->content);
    }

    public function test_la_moderation_epargne_les_messages_systeme(): void
    {
        // Ce sont des textes que le PRODUIT écrit : les caviarder troue une annonce technique, et un blocage rendrait la création de canal impossible sur un nom malheureux.
        config()->set('messaging.moderation.channels', true);

        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($proprietaire);

        $systeme = Message::where('channel_id', $canal->id)
            ->where('type', Message::TYPE_SYSTEM)
            ->first();

        $this->assertNotNull($systeme);
        $this->assertStringContainsString($proprietaire->name, $systeme->content);
    }

    // ──────────────────────────────────────────────────────
    // Notes vocales
    // ──────────────────────────────────────────────────────

    public function test_on_envoie_une_note_vocale(): void
    {
        // Sur un chantier, on ne tape pas : mains prises, gants, téléphone au fond d'une poche.
        Storage::fake('public');

        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($proprietaire);

        $this->actingAs($proprietaire, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/voice", [
                'audio' => UploadedFile::fake()->create('note.m4a', 120, 'audio/mp4'),
                'duration' => 12,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'voice');

        $this->assertDatabaseHas('messages', [
            'channel_id' => $canal->id,
            'type' => 'voice',
        ]);
    }

    public function test_un_executable_deguise_en_audio_est_refuse(): void
    {
        // `mimetypes` regarde le CONTENU réel, pas l'extension : un exécutable renommé `.m4a` ne
        // passe pas.
        Storage::fake('public');

        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($proprietaire);

        $this->actingAs($proprietaire, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/voice", [
                'audio' => UploadedFile::fake()->create('note.m4a', 120, 'application/x-msdownload'),
            ])
            ->assertStatus(422);
    }

    public function test_un_non_membre_n_envoie_pas_de_note_vocale(): void
    {
        Storage::fake('public');

        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $etranger = $this->membre();
        $canal = $this->canalAvec($proprietaire);

        $this->actingAs($etranger, 'sanctum')
            ->postJson("/api/provider/company/channels/{$canal->id}/voice", [
                'audio' => UploadedFile::fake()->create('note.m4a', 120, 'audio/mp4'),
            ])
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────
    // Pagination par curseur
    // ──────────────────────────────────────────────────────

    public function test_l_historique_se_remonte_par_curseur(): void
    {
        // Un fil vivant reçoit des messages pendant qu'on le remonte : une pagination par décalage rejouerait ou sauterait des lignes à chaque nouveau message.
        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $canal = $this->canalAvec($proprietaire);

        $ids = [];

        foreach (range(1, 5) as $i) {
            $ids[] = app(MessageService::class)->send($canal, $proprietaire, "Message {$i}")->id;
        }

        $avant = $ids[2];

        $recus = $this->actingAs($proprietaire, 'sanctum')
            ->getJson("/api/provider/company/channels/{$canal->id}/messages?before_id={$avant}")
            ->assertOk()
            ->json('data');

        $recusIds = array_column($recus, 'id');

        $this->assertNotContains($ids[3], $recusIds);
        $this->assertNotContains($ids[4], $recusIds);
        $this->assertContains($ids[0], $recusIds);
    }
}
