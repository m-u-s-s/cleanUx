<?php

namespace Tests\Feature\Email;

use App\Models\EmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LE POINT D'ENTREE DES RETOURS D'EXPEDITION.
 *
 * C'est la porte qui manquait : les colonnes d'ouverture et de clic existaient sur les envois, la
 * table des evenements aussi, et RIEN ne les alimentait.
 *
 * Elle est PUBLIQUE — un service d'expedition ne sait pas s'authentifier — et sa seule protection
 * est la signature. Un webhook qui accepte des charges non verifiees est pire que pas de webhook :
 * n'importe qui pourrait declarer qu'un e-mail a ete ouvert, rejete, ou qu'on s'est plaint.
 */
class LesRetoursDExpeditionTest extends TestCase
{
    use RefreshDatabase;

    private const CLE = 'cle-de-signature-de-test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('email_v2.webhooks.mailgun.signing_key', self::CLE);
    }

    public function test_un_evenement_signe_marque_l_envoi_comme_remis(): void
    {
        $message = $this->envoi();

        $this->postJson('/webhooks/email/mailgun', $this->charge('delivered', 'ev-1', $message->provider_message_id))
            ->assertOk()
            ->assertJson(['enregistres' => 1]);

        $message->refresh();

        $this->assertSame('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
        $this->assertDatabaseHas('email_webhook_events', ['provider_event_id' => 'ev-1', 'event_type' => 'delivered']);
    }

    /** SANS SIGNATURE VALIDE, RIEN N'ENTRE. */
    public function test_une_signature_invalide_est_refusee(): void
    {
        $message = $this->envoi();

        $charge = $this->charge('opened', 'ev-2', $message->provider_message_id);
        $charge['signature']['signature'] = 'fausse';

        $this->postJson('/webhooks/email/mailgun', $charge)->assertStatus(401);

        $this->assertNull($message->fresh()->opened_at);
        $this->assertDatabaseCount('email_webhook_events', 0);
    }

    /** TEMOIN — la meme charge, correctement signee, entre bien. */
    public function test_temoin_la_meme_charge_bien_signee_entre(): void
    {
        $message = $this->envoi();

        $this->postJson('/webhooks/email/mailgun', $this->charge('opened', 'ev-2', $message->provider_message_id))
            ->assertOk();

        $this->assertNotNull($message->fresh()->opened_at);
    }

    /**
     * UNE CLE ABSENTE VAUT REFUS, jamais « accepter tout le monde ».
     *
     * C'est le reglage par defaut d'une installation neuve : il ne doit surtout pas ouvrir la porte.
     */
    public function test_sans_cle_configuree_la_porte_reste_fermee(): void
    {
        config()->set('email_v2.webhooks.mailgun.signing_key', null);

        $message = $this->envoi();

        $this->postJson('/webhooks/email/mailgun', $this->charge('delivered', 'ev-3', $message->provider_message_id))
            ->assertStatus(401);
    }

    /**
     * UN HORODATAGE ANCIEN EST UN REJEU.
     *
     * Sans fenetre, une requete authentique captee une fois se rejoue indefiniment : sa signature
     * reste valable pour toujours.
     */
    public function test_une_requete_trop_ancienne_est_refusee(): void
    {
        $message = $this->envoi();

        $this->postJson('/webhooks/email/mailgun',
            $this->charge('delivered', 'ev-4', $message->provider_message_id, Carbon::now()->subHour()->getTimestamp()))
            ->assertStatus(401);
    }

    /** L'EVENEMENT NE S'ENREGISTRE QU'UNE FOIS : un fournisseur rejoue ses appels. */
    public function test_un_evenement_rejoue_ne_compte_pas_deux_fois(): void
    {
        $message = $this->envoi();
        $charge = $this->charge('opened', 'ev-5', $message->provider_message_id);

        $this->postJson('/webhooks/email/mailgun', $charge)->assertJson(['enregistres' => 1]);
        $this->postJson('/webhooks/email/mailgun', $charge)->assertJson(['enregistres' => 0, 'ignores' => 1]);

        $this->assertDatabaseCount('email_webhook_events', 1);
    }

    /**
     * LE STATUT NE RECULE PAS.
     *
     * L'ordre d'arrivee n'est jamais garanti sur un reseau : un « remis » posterieur a un
     * « ouvert » ne doit pas effacer l'ouverture.
     */
    public function test_un_remis_arrive_apres_une_ouverture_ne_fait_pas_reculer_le_statut(): void
    {
        $message = $this->envoi();

        $this->postJson('/webhooks/email/mailgun', $this->charge('opened', 'ev-6', $message->provider_message_id));
        $this->postJson('/webhooks/email/mailgun', $this->charge('delivered', 'ev-7', $message->provider_message_id));

        $message->refresh();

        $this->assertSame('opened', $message->status, 'Le statut a recule.');
        $this->assertNotNull($message->delivered_at, 'L’horodatage du remis doit tout de même être posé.');
    }

    /** UN REBOND GAGNE SUR TOUT : c'est l'information la plus couteuse a perdre. */
    public function test_un_rebond_l_emporte_sur_une_ouverture(): void
    {
        $message = $this->envoi();

        $this->postJson('/webhooks/email/mailgun', $this->charge('opened', 'ev-8', $message->provider_message_id));
        $this->postJson('/webhooks/email/mailgun',
            $this->charge('failed', 'ev-9', $message->provider_message_id, null, ['severity' => 'permanent']));

        $this->assertSame('bounced', $message->fresh()->status);
    }

    /**
     * UN ECHEC TEMPORAIRE N'EST PAS UN REBOND.
     *
     * Une boite pleine n'est pas une adresse inexistante : les confondre marquerait comme perdue
     * une adresse qui fonctionne.
     */
    public function test_un_echec_temporaire_n_est_pas_un_rebond(): void
    {
        $message = $this->envoi();

        $this->postJson('/webhooks/email/mailgun',
            $this->charge('failed', 'ev-10', $message->provider_message_id, null, ['severity' => 'temporary']))
            ->assertStatus(202);

        $this->assertNotSame('bounced', $message->fresh()->status);
        $this->assertDatabaseCount('email_webhook_events', 0);
    }

    /** UN FOURNISSEUR SANS VERIFICATEUR EST REFUSE, jamais accepte « en attendant ». */
    public function test_un_fournisseur_inconnu_est_refuse(): void
    {
        $this->postJson('/webhooks/email/sendgrid', ['peu' => 'importe'])->assertStatus(404);
    }

    private function envoi(): EmailMessage
    {
        return EmailMessage::query()->create([
            // `code` est NOT NULL : c'est l'identifiant public d'un envoi cote Brio.
            'code' => 'msg-'.uniqid(),
            'to_email' => 'client@brio.test',
            'from_email' => 'noreply@brio.test',
            'subject' => 'Test',
            'status' => 'sent',
            'provider' => 'mailgun',
            'provider_message_id' => '<abc@brio.test>',
            'template_code' => 'booking_confirmed',
        ]);
    }

    /**
     * Une charge Mailgun correctement signee.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function charge(
        string $type,
        string $identifiant,
        ?string $messageId,
        ?int $horodatage = null,
        array $extra = [],
    ): array {
        $horodatage ??= Carbon::now()->getTimestamp();
        $jeton = 'jeton-'.$identifiant;

        return [
            'signature' => [
                'timestamp' => (string) $horodatage,
                'token' => $jeton,
                'signature' => hash_hmac('sha256', $horodatage.$jeton, self::CLE),
            ],
            'event-data' => array_merge([
                'id' => $identifiant,
                'event' => $type,
                'timestamp' => $horodatage,
                'message' => ['headers' => ['message-id' => $messageId]],
            ], $extra),
        ];
    }
}
