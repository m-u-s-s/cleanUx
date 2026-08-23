<?php

namespace Tests\Feature\Kyc;

use App\Models\KycVerification;
use App\Models\KycWebhookEvent;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\KycVerificationService;
use App\Services\Kyc\Providers\KycMockProvider;
use App\Services\Kyc\Providers\OnfidoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/** B5 — tests D'ATTAQUE sur le webhook KYC. */
class KycWebhookProviderSelectionAttackTest extends TestCase
{
    use RefreshDatabase;

    /** En production le middleware ForceHttps répond 301 sur http:// — on attaque donc en https, sinon on mesurerait la redirection au lieu de la garde (piège de mesure). */
    private const URL_PROD = 'https://localhost/webhooks/kyc/mock';

    protected function setUp(): void
    {
        parent::setUp();
        // Configuration « normale » du projet : provider mock, hors production.
        $this->app->bind(KycProviderInterface::class, KycMockProvider::class);
        config(['kyc.default_provider' => 'mock']);
    }

    /** Bascule l'application en production, comme un vrai déploiement. */
    private function basculeEnProduction(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
    }

    /** (a) ATTAQUE — POST sur /webhooks/kyc/mock en production. */
    public function test_attaque_le_provider_mock_est_injoignable_en_production(): void
    {
        $this->basculeEnProduction();

        $reponse = $this->postJson(self::URL_PROD, [
            'id' => 'attaque_prod_001',
            'event_type' => 'check.completed',
            'decision' => 'approved',
            'status' => 'clear',
            'score' => 0.99,
        ]);

        $reponse->assertStatus(404);
        $this->assertSame(0, KycWebhookEvent::count(), 'aucun événement ne doit être stocké en production via le mock');
    }

    /** (b) ATTAQUE — le segment d'URL diffère du provider configuré. */
    public function test_attaque_un_segment_d_url_different_du_provider_configure_est_refuse(): void
    {
        $this->app->bind(KycProviderInterface::class, OnfidoProvider::class);
        config([
            'kyc.default_provider' => 'onfido',
            'kyc.providers.onfido.webhook_token' => 'secret_onfido',
        ]);

        $reponse = $this->postJson('/webhooks/kyc/mock', [
            'id' => 'attaque_segment_001',
            'event_type' => 'check.completed',
            'decision' => 'approved',
            'status' => 'clear',
        ]);

        $reponse->assertStatus(404);
        $this->assertSame(0, KycWebhookEvent::count(), 'un segment d\'URL hostile ne doit rien stocker');
    }

    /** (b bis) CONTRÔLE POSITIF — le segment ÉGAL au provider configuré, avec une signature valide, reste accepté. */
    public function test_le_segment_egal_au_provider_configure_avec_signature_valide_reste_accepte(): void
    {
        Queue::fake();

        $this->app->bind(KycProviderInterface::class, OnfidoProvider::class);
        config([
            'kyc.default_provider' => 'onfido',
            'kyc.providers.onfido.webhook_token' => 'secret_onfido',
        ]);

        $donnees = [
            'payload' => [
                'resource_type' => 'check',
                'action' => 'check.completed',
                'object' => ['id' => 'chk_legit_001', 'result' => 'clear', 'status' => 'complete'],
            ],
        ];

        $this->postJson('/webhooks/kyc/onfido', $donnees, [
            'X-SHA2-Signature' => hash_hmac('sha256', json_encode($donnees), 'secret_onfido'),
        ])->assertOk();

        $this->assertSame(1, KycWebhookEvent::count());
        $this->assertSame('onfido', KycWebhookEvent::first()->provider);
    }

    /** (b ter) Une signature INVALIDE sur le bon segment reste refusée (400). */
    public function test_une_signature_invalide_sur_le_bon_segment_est_refusee(): void
    {
        $this->app->bind(KycProviderInterface::class, OnfidoProvider::class);
        config([
            'kyc.default_provider' => 'onfido',
            'kyc.providers.onfido.webhook_token' => 'secret_onfido',
        ]);

        $this->postJson('/webhooks/kyc/onfido', ['payload' => ['resource_type' => 'check']], [
            'X-SHA2-Signature' => 'deadbeef',
        ])->assertStatus(400);

        $this->assertSame(0, KycWebhookEvent::count());
    }

    /** (c) ATTAQUE LA PLUS GRAVE — événement SANS identifiant de ressource. */
    public function test_attaque_un_evenement_sans_identifiant_de_ressource_ne_touche_pas_la_verification_la_plus_recente(): void
    {
        $victime = User::factory()->create(['role' => 'employe', 'email' => 'victime@example.com']);
        ProviderProfile::create([
            'user_id' => $victime->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $verification = app(KycVerificationService::class)->start($victime);

        // La charge utile ne porte AUCUN identifiant de ressource : ni payload.object.id,
        // ni object.id, ni check_id. L'attaquant ne désigne personne.
        $this->postJson('/webhooks/kyc/mock', [
            'id' => 'attaque_sans_ressource_001',
            'event_type' => 'check.completed',
            'decision' => 'approved',
            'status' => 'clear',
            'score' => 0.99,
        ])->assertOk();

        $profil = ProviderProfile::where('user_id', $victime->id)->firstOrFail();
        $this->assertSame(
            'pending',
            $profil->verification_status,
            'un événement anonyme ne doit JAMAIS faire passer en « vérifié » le KYC d\'autrui'
        );

        $verification->refresh();
        $this->assertSame(KycVerification::STATUS_IN_REVIEW, $verification->status);
        $this->assertSame(KycVerification::DECISION_PENDING, $verification->decision);
        $this->assertNull($verification->completed_at);

        $evenement = KycWebhookEvent::firstOrFail();
        $this->assertSame(KycWebhookEvent::STATUS_IGNORED, $evenement->status);
    }

    /** (c bis) Même refus au niveau du service, avec un identifiant vide ou non exploitable — la chaîne vide ne doit pas rouvrir le repli sur « la plus récente ». */
    public function test_un_identifiant_de_ressource_vide_est_traite_comme_absent(): void
    {
        $victime = User::factory()->create(['role' => 'employe', 'email' => 'victime2@example.com']);
        ProviderProfile::create([
            'user_id' => $victime->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        app(KycVerificationService::class)->start($victime);

        // Les trois formes d'identifiant vide relevees ensemble : une garde qui laisse passer
        // l'une laisse souvent passer les autres, et chacune est une verification approuvee a tort.
        $acceptees = [];

        foreach ([
            'espaces' => ['payload' => ['object' => ['id' => '   ']]],
            'chaine vide' => ['check_id' => ''],
            'null' => ['object' => ['id' => null]],
        ] as $forme => $charge) {
            $resultat = app(KycVerificationService::class)->applyWebhookPayload(
                $charge + ['decision' => 'approved', 'status' => 'clear', 'score' => 0.99]
            );

            if ($resultat !== null) {
                $acceptees[] = $forme;
            }
        }

        $this->assertSame([], $acceptees, 'Ces identifiants vides ne designent aucune verification et sont pourtant acceptes.');

        $this->assertSame(
            'pending',
            ProviderProfile::where('user_id', $victime->id)->firstOrFail()->verification_status
        );
    }

    /** (2) Le vérificateur mock lui-même refuse en production, même atteint autrement que par le contrôleur (job, commande, appel direct). */
    public function test_le_verificateur_mock_echoue_en_production_meme_appele_directement(): void
    {
        $this->basculeEnProduction();

        $this->expectException(RuntimeException::class);

        (new KycMockProvider)->verifyWebhook('{"decision":"approved"}', []);
    }

    /** (2 bis) Hors production, le mock refuse aussi dès que la configuration désigne un autre provider : être appelé signifie que le vrai vérificateur a été contourné. */
    public function test_le_verificateur_mock_echoue_si_la_configuration_designe_un_autre_provider(): void
    {
        config(['kyc.default_provider' => 'onfido']);

        $this->expectException(RuntimeException::class);

        (new KycMockProvider)->verifyWebhook('{"decision":"approved"}', []);
    }
}
