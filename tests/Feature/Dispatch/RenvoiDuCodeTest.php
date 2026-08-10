<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionVerificationCode;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RENVOYER AU CLIENT LE CODE QU'IL N'A PAS REÇU.
 *
 * Un SMS se perd : réseau du client, numéro mal saisi, message noyé, plafond d'envoi atteint. Sans
 * ce geste, l'intervention s'arrêtait là — le prestataire devant la porte, le client sans ses six
 * chiffres, et pour seul recours l'annulation de la mission.
 *
 * DEUX INVARIANTS PORTENT TOUT LE RESTE :
 *
 *  1. LE CODE PRÉCÉDENT EST INVALIDÉ. Deux codes valides pour la même mission feraient hésiter un
 *     client qui a reçu les deux SMS, et le mauvais choix brûle un essai.
 *  2. UNE ATTENTE SÉPARE DEUX RENVOIS. Le module SMS plafonne à cinq messages par heure et par
 *     numéro : trois pressions distraites suffisaient à l'épuiser, après quoi le client ne recevait
 *     plus RIEN — ni ce code-ci, ni celui de fin. C'est arrivé sur la base de démonstration, et le
 *     statut `rate_limited` du registre était le seul endroit où cela se lisait.
 */
class RenvoiDuCodeTest extends TestCase
{
    use RefreshDatabase;

    private function prestataire(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYE, 'is_active' => true]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user;
    }

    /** @return array{0: User, 1: Mission} */
    private function missionAvecClient(?string $telephone = '+32470000111'): array
    {
        $prestataire = $this->prestataire();

        $client = User::factory()->client()->create(['phone' => $telephone]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
            'status' => 'confirme',
            /*
             * DEUX SOURCES POUR LE NUMÉRO, et le contrôleur les essaie dans l'ordre : le compte du
             * client, puis celui saisi sur la réservation. Ne vider que la première laissait la
             * fabrique fournir la seconde — et le test passait pour une mauvaise raison.
             */
            'telephone_client' => $telephone,
        ]);

        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => 'arrived',
        ]);

        MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'assignment_status' => 'accepted',
            'assigned_at' => now(),
        ]);

        return [$prestataire, $mission];
    }

    #[Test]
    public function le_prestataire_peut_faire_renvoyer_le_code(): void
    {
        [$prestataire, $mission] = $this->missionAvecClient();

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'start'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('type', 'start');

        $this->assertSame(
            1,
            MissionVerificationCode::query()
                ->where('mission_id', $mission->id)
                ->where('code_type', 'start')
                ->where('is_consumed', false)
                ->count(),
        );
    }

    #[Test]
    public function le_sms_part_reellement(): void
    {
        [$prestataire, $mission] = $this->missionAvecClient();

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'end'])
            ->assertOk();

        // Le registre est la seule preuve qu'un message est parti : le pilote de développement
        // n'envoie rien, il enregistre.
        $this->assertGreaterThan(0, DB::table('sms_messages')->count());
    }

    #[Test]
    public function le_code_precedent_cesse_d_etre_valide(): void
    {
        [$prestataire, $mission] = $this->missionAvecClient();

        MissionVerificationCode::create([
            'mission_id' => $mission->id,
            'code_type' => 'start',
            'code_hash' => bcrypt('111111'),
            'expires_at' => now()->addMinutes(20),
            'attempts' => 0,
            'is_consumed' => false,
        ]);

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'start'])
            ->assertOk();

        // Un seul code vivant : deux SMS reçus, un seul qui marche, sinon le client hésite et le
        // mauvais choix brûle un essai.
        $this->assertSame(
            1,
            MissionVerificationCode::query()
                ->where('mission_id', $mission->id)
                ->where('code_type', 'start')
                ->where('is_consumed', false)
                ->count(),
        );
    }

    #[Test]
    public function deux_renvois_coup_sur_coup_sont_refuses(): void
    {
        [$prestataire, $mission] = $this->missionAvecClient();

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'start'])
            ->assertOk();

        // Le plafond du module SMS est de cinq par heure : sans cette attente, trois pressions
        // distraites privent le client de TOUS ses codes suivants.
        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'start'])
            ->assertStatus(409)
            ->assertJsonPath('ok', false);
    }

    #[Test]
    public function l_attente_ne_bloque_pas_l_autre_code(): void
    {
        [$prestataire, $mission] = $this->missionAvecClient();

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'start'])
            ->assertOk();

        // Début et fin sont deux besoins distincts : renvoyer l'un ne doit pas verrouiller l'autre.
        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'end'])
            ->assertOk();
    }

    #[Test]
    public function un_client_sans_telephone_est_dit_en_toutes_lettres(): void
    {
        [$prestataire, $mission] = $this->missionAvecClient(null);

        // « Envoyé » sur un dossier sans numéro ferait attendre un SMS qui ne partira jamais.
        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'start'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    #[Test]
    public function un_autre_prestataire_ne_peut_pas_declencher_l_envoi(): void
    {
        [, $mission] = $this->missionAvecClient();
        $intrus = $this->prestataire();

        // Le numéro masqué que rend la réponse serait sinon une fuite : il confirme qu'un client
        // existe et à quoi ressemble son numéro.
        $this->actingAs($intrus, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'start'])
            ->assertForbidden();
    }

    #[Test]
    public function le_numero_rendu_est_masque(): void
    {
        [$prestataire, $mission] = $this->missionAvecClient('+32470000111');
        Cache::flush();

        $reponse = $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/codes/resend", ['type' => 'start'])
            ->assertOk();

        $masque = (string) $reponse->json('sent_to');

        // Il confirme au prestataire qu'on a écrit au bon client, sans lui livrer le téléphone de
        // quelqu'un chez qui il n'ira peut-être jamais.
        $this->assertStringNotContainsString('+32470000111', $masque);
        $this->assertStringContainsString('*', $masque);
        $this->assertStringEndsWith('11', $masque);
    }
}
