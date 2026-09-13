<?php

namespace Tests\Feature\ChatV2;

use App\Models\Booking;
use App\Models\ChatThread;
use App\Models\ComplaintCase;
use App\Models\User;
use App\Services\ChatV2\ChatRelationshipGuard;
use App\Services\ChatV2\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DEUX TROUS DISTINCTS DANS LE MÊME GARDE, et le second survivait à la correction du premier :
 * la sortie anticipée quand on est seul participant, et le `||` du lien par litige.
 */
class SeNommerSeulNOuvrePasLeFilDAutruiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('chat_v2.allowed_context_types', ['booking', 'dispute', 'admin', 'generic']);
        Config::set('chat_v2.broadcast_enabled', false);
    }

    private function reservation(User $client, ?User $prestataire = null): Booking
    {
        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'assigned_provider_user_id' => $prestataire?->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);
    }

    private function seul(User $user): array
    {
        return [['user_id' => $user->id, 'role' => 'client']];
    }

    private function garde(): ChatRelationshipGuard
    {
        return app(ChatRelationshipGuard::class);
    }

    /** LE TÉMOIN POSITIF : la partie légitime rejoint bien son propre fil en se nommant seule. */
    #[Test]
    public function le_client_de_la_reservation_rejoint_son_propre_fil(): void
    {
        $client = User::factory()->create();
        $reservation = $this->reservation($client, User::factory()->create());

        app(ChatService::class)->startThread('booking', $reservation->id, [
            ['user_id' => $client->id, 'role' => 'client'],
            ['user_id' => $reservation->assigned_provider_user_id, 'role' => 'provider'],
        ]);

        $this->garde()->assertPeutOuvrirUnFil($client, $this->seul($client), 'booking', $reservation->id);

        $this->assertTrue(true, 'aucune exception : la partie légitime passe');
    }

    /** LE TÉMOIN POSITIF DU CAS NEUF : ouvrir un fil seul sur un contexte vierge reste permis. */
    #[Test]
    public function ouvrir_un_fil_neuf_ou_l_on_est_seul_reste_permis(): void
    {
        $inconnu = User::factory()->create();

        $this->garde()->assertPeutOuvrirUnFil($inconnu, $this->seul($inconnu), 'booking', 999123);

        $this->assertTrue(true, 'un fil neuf sans autre participant ne touche personne');
    }

    #[Test]
    public function un_etranger_ne_rejoint_pas_le_fil_d_une_reservation_qui_n_est_pas_la_sienne(): void
    {
        $client = User::factory()->create();
        $reservation = $this->reservation($client, User::factory()->create());
        $intrus = User::factory()->create();

        app(ChatService::class)->startThread('booking', $reservation->id, [
            ['user_id' => $client->id, 'role' => 'client'],
        ]);

        $this->expectException(ValidationException::class);

        $this->garde()->assertPeutOuvrirUnFil($intrus, $this->seul($intrus), 'booking', $reservation->id);
    }

    /** La conséquence de bout en bout : l'intrus n'entre pas dans les participants du fil. */
    #[Test]
    public function le_fil_existant_ne_gagne_pas_l_intrus_comme_participant(): void
    {
        $client = User::factory()->create();
        $reservation = $this->reservation($client);
        $intrus = User::factory()->create();

        $fil = app(ChatService::class)->startThread('booking', $reservation->id, [
            ['user_id' => $client->id, 'role' => 'client'],
        ]);

        try {
            $this->garde()->assertPeutOuvrirUnFil($intrus, $this->seul($intrus), 'booking', $reservation->id);
            $this->fail('le garde a laissé passer un étranger');
        } catch (ValidationException) {
            // attendu
        }

        $this->assertDatabaseMissing('chat_participants', [
            'thread_id' => $fil->id,
            'user_id' => $intrus->id,
        ]);
    }

    /**
     * LE SECOND TROU. `partagentUnLitige` se satisfaisait que L'AUTRE soit le plaignant : l'auteur
     * obtenait un canal direct avec lui sans avoir aucun lien avec le dossier.
     */
    #[Test]
    public function un_etranger_n_ouvre_pas_un_fil_avec_le_plaignant_d_un_dossier(): void
    {
        $plaignant = User::factory()->create();
        $intrus = User::factory()->create();

        $dossier = ComplaintCase::factory()->create([
            'client_id' => $plaignant->id,
            'booking_id' => null,
        ]);

        $this->expectException(ValidationException::class);

        $this->garde()->assertPeutOuvrirUnFil($intrus, [
            ['user_id' => $plaignant->id, 'role' => 'client'],
        ], 'dispute', $dossier->id);
    }

    /** LE TÉMOIN POSITIF DU LITIGE : deux vraies parties du dossier s'ouvrent bien un fil. */
    #[Test]
    public function deux_parties_du_dossier_ouvrent_bien_leur_fil(): void
    {
        $plaignant = User::factory()->create();
        $traitant = User::factory()->create();

        $dossier = ComplaintCase::factory()->create([
            'client_id' => $plaignant->id,
            'assigned_to' => $traitant->id,
            'booking_id' => null,
        ]);

        $this->garde()->assertPeutOuvrirUnFil($plaignant, [
            ['user_id' => $traitant->id, 'role' => 'provider'],
        ], 'dispute', $dossier->id);

        $this->assertTrue(true, 'aucune exception : les deux sont parties au dossier');
    }

    /** L'administration modère : elle reste au-dessus du garde, et c'est voulu. */
    #[Test]
    public function temoin_l_administrateur_reste_au_dessus_du_garde(): void
    {
        $client = User::factory()->create();
        $reservation = $this->reservation($client);
        $admin = User::factory()->create(['platform_role' => 'admin']);

        app(ChatService::class)->startThread('booking', $reservation->id, [
            ['user_id' => $client->id, 'role' => 'client'],
        ]);

        $this->garde()->assertPeutOuvrirUnFil($admin, $this->seul($admin), 'booking', $reservation->id);

        $this->assertSame(1, ChatThread::query()->where('context_id', $reservation->id)->count());
    }
}
