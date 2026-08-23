<?php

namespace Tests\Feature\ChatV2;

use App\Models\Booking;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\ChatV2\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LE CHAT CLIENT ↔ PRESTATAIRE : DIFFUSION AUTORISÉE, ET COMPOSITION CONTRÔLÉE. */
class FilTempsReelEtParticipantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les callbacks d'autorisation ne sont pas chargées par défaut dans le noyau de test.
        require base_path('routes/channels.php');
    }

    /** @return array{0: User, 1: User, 2: Booking} */
    private function clientEtPrestataireLies(): array
    {
        $client = User::factory()->client()->create();
        $prestataire = User::factory()->employe()->create();

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
        ]);

        return [$client, $prestataire, $booking];
    }

    private function filEntre(User $client, User $prestataire, ?Booking $booking = null): ChatThread
    {
        return app(ChatService::class)->startThread(
            contextType: $booking ? 'booking' : null,
            contextId: $booking?->id,
            participants: [
                ['user_id' => $client->id, 'role' => ChatParticipant::ROLE_CLIENT],
                ['user_id' => $prestataire->id, 'role' => ChatParticipant::ROLE_PROVIDER],
            ],
        );
    }

    // ── Diffusion ────────────────────────────────────────────────────────────

    #[Test]
    public function un_participant_est_autorise_sur_le_canal_du_fil(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);

        $this->actingAs($client)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => 'private-chat.thread.'.$fil->id,
            ])
            ->assertOk();
    }

    #[Test]
    public function un_etranger_au_fil_est_refuse(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);

        // Sans ce refus, il suffirait de deviner un identifiant de fil pour écouter la conversation
        // d'autrui en direct — l'adresse du client et les détails de l'intervention comprises.
        $this->actingAs(User::factory()->create())
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => 'private-chat.thread.'.$fil->id,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function un_participant_retire_perd_le_temps_reel(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);

        app(ChatService::class)->removeParticipant($fil, $prestataire->id);

        // C'EST LE POINT QUI RELIE LES DEUX RÉPARATIONS.
        $this->actingAs($prestataire)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => 'private-chat.thread.'.$fil->id,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function l_administration_lit_tous_les_fils(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);

        // La modération des conversations passe par les mêmes écrans : la lui refuser reviendrait à
        // rendre le signalement d'un message inexploitable.
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => 'private-chat.thread.'.$fil->id,
            ])
            ->assertOk();
    }

    // ── Ouverture contrôlée ──────────────────────────────────────────────────

    #[Test]
    public function on_ne_peut_pas_ouvrir_un_fil_avec_un_inconnu(): void
    {
        $curieux = User::factory()->client()->create();
        $cible = User::factory()->client()->create();

        $this->actingAs($curieux, 'sanctum')
            ->postJson('/api/v2/chat/threads', [
                'participants' => [
                    ['user_id' => $cible->id, 'role' => 'client'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertSame(0, ChatThread::query()->count());
    }

    #[Test]
    public function une_reservation_partagee_ouvre_le_droit_d_ecrire(): void
    {
        [$client, $prestataire] = $this->clientEtPrestataireLies();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v2/chat/threads', [
                'participants' => [
                    ['user_id' => $prestataire->id, 'role' => 'provider'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);
    }

    #[Test]
    public function nul_ne_se_declare_administrateur_de_son_propre_fil(): void
    {
        [$client, $prestataire] = $this->clientEtPrestataireLies();

        // Le rôle `admin` d'un fil ouvre sa modération. Le laisser au formulaire, c'est le donner à
        // qui le demande.
        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v2/chat/threads', [
                'participants' => [
                    ['user_id' => $client->id, 'role' => 'admin'],
                    ['user_id' => $prestataire->id, 'role' => 'provider'],
                ],
            ])
            ->assertStatus(422);
    }

    // ── Composition ──────────────────────────────────────────────────────────

    #[Test]
    public function un_participant_peut_quitter_le_fil(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);

        $this->actingAs($prestataire, 'sanctum')
            ->deleteJson("/api/v2/chat/threads/{$fil->id}/participants/{$prestataire->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull(
            ChatParticipant::query()
                ->where('thread_id', $fil->id)
                ->where('user_id', $prestataire->id)
                ->value('left_at'),
        );
    }

    #[Test]
    public function on_ne_retire_pas_quelqu_un_d_autre_sans_titre(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);

        // Exclure un interlocuteur d'une conversation n'est pas un geste ordinaire : sans ce refus,
        // le premier arrivé pourrait couper l'autre du fil au milieu d'une intervention.
        $this->actingAs($client, 'sanctum')
            ->deleteJson("/api/v2/chat/threads/{$fil->id}/participants/{$prestataire->id}")
            ->assertForbidden();
    }

    #[Test]
    public function un_participant_retire_ne_lit_plus_le_fil(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);

        app(ChatService::class)->removeParticipant($fil, $prestataire->id);

        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/v2/chat/threads/{$fil->id}")
            ->assertForbidden();
    }

    #[Test]
    public function on_n_ajoute_pas_un_inconnu_a_un_fil_existant(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);
        $tiers = User::factory()->client()->create();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v2/chat/threads/{$fil->id}/participants", [
                'participants' => [['user_id' => $tiers->id, 'role' => 'observer']],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function reintegrer_quelqu_un_lui_rend_le_fil_sans_le_dupliquer(): void
    {
        [$client, $prestataire, $booking] = $this->clientEtPrestataireLies();
        $fil = $this->filEntre($client, $prestataire, $booking);

        $service = app(ChatService::class);
        $service->removeParticipant($fil, $prestataire->id);
        $service->syncParticipants($fil, [
            ['user_id' => $prestataire->id, 'role' => ChatParticipant::ROLE_PROVIDER],
        ]);

        // Une seule ligne : la clé (fil, utilisateur) fait foi, sinon un prestataire réassigné puis
        // rappelé apparaîtrait deux fois dans la liste des participants.
        $this->assertSame(
            1,
            ChatParticipant::query()
                ->where('thread_id', $fil->id)
                ->where('user_id', $prestataire->id)
                ->count(),
        );

        $this->assertNull(
            ChatParticipant::query()
                ->where('thread_id', $fil->id)
                ->where('user_id', $prestataire->id)
                ->value('left_at'),
        );
    }
}
