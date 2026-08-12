<?php

namespace Tests\Feature\Webhooks;

use App\Models\InsuranceWebhookEvent;
use App\Models\SmsWebhookEvent;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\Providers\InsuranceMockProvider;
use App\Services\Insurance\Providers\WakamInsuranceProvider;
use App\Services\Sms\Providers\SmsMockProvider;
use App\Services\Sms\Providers\TwilioSmsProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * B5 — le même motif « le segment {provider} de l'URL sélectionne le vérificateur »
 * existait pour Insurance et SMS. Ces tests exécutent l'attaque sur les deux.
 *
 * Insurance : la garde vit dans InsuranceWebhookController::resolveProvider (404).
 * SMS : SmsWebhookController instancie encore d'après l'URL, la garde vit donc dans
 *       SmsMockProvider::verifyWebhook — le contrôleur attrape et répond 400.
 *       Dans les deux cas : rien n'est stocké, rien n'est dispatché.
 */
class WebhookProviderSelectionAttackTest extends TestCase
{
    use RefreshDatabase;

    /** ForceHttps répond 301 sur http:// en production : on attaque en https. */
    private const URL_PROD_INSURANCE = 'https://localhost/webhooks/insurance/mock';

    private const URL_PROD_SMS = 'https://localhost/webhooks/sms/mock';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(InsuranceProviderInterface::class, InsuranceMockProvider::class);
        $this->app->bind(SmsProviderInterface::class, SmsMockProvider::class);
        config(['insurance.default_provider' => 'mock', 'sms.default_provider' => 'mock']);
    }

    private function basculeEnProduction(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
    }

    // ---------------------------------------------------------------- Insurance

    /**
     * (a) ATTAQUE — /webhooks/insurance/mock en production.
     * Assertion qui tombe sans le correctif : assertStatus(404) (redevient 200)
     * et assertSame(0, InsuranceWebhookEvent::count()).
     */
    public function test_attaque_insurance_le_provider_mock_est_injoignable_en_production(): void
    {
        $this->basculeEnProduction();

        $this->postJson(self::URL_PROD_INSURANCE, [
            'event_id' => 'attaque_prod_ins',
            'target' => 'policy',
            'external_id' => 'mock_pol_victime',
            'status' => 'cancelled',
        ])->assertStatus(404);

        $this->assertSame(0, InsuranceWebhookEvent::count());
    }

    /**
     * (b) ATTAQUE — segment d'URL « mock » alors que la configuration dit « wakam ».
     * Assertion qui tombe sans le correctif : assertStatus(404) (redevient 200).
     */
    public function test_attaque_insurance_un_segment_different_du_provider_configure_est_refuse(): void
    {
        $this->app->bind(InsuranceProviderInterface::class, WakamInsuranceProvider::class);
        config([
            'insurance.default_provider' => 'wakam',
            'insurance.providers.wakam.webhook_secret' => 'secret_wakam',
        ]);

        $this->postJson('/webhooks/insurance/mock', [
            'event_id' => 'attaque_segment_ins',
            'target' => 'policy',
            'external_id' => 'mock_pol_victime',
            'status' => 'cancelled',
        ])->assertStatus(404);

        $this->assertSame(0, InsuranceWebhookEvent::count());
    }

    /** CONTRÔLE POSITIF — le segment égal au provider configuré reste accepté. */
    public function test_insurance_le_segment_egal_au_provider_configure_reste_accepte(): void
    {
        $this->postJson('/webhooks/insurance/mock', [
            'event_id' => 'legitime_ins',
            'target' => 'policy',
            'external_id' => 'mock_pol_inconnue',
            'status' => 'cancelled',
        ])->assertOk();

        $this->assertSame(1, InsuranceWebhookEvent::count());
        $this->assertSame('mock', InsuranceWebhookEvent::firstOrFail()->provider);
    }

    /** (2) Le vérificateur mock assurance refuse en production, même appelé directement. */
    public function test_le_verificateur_mock_insurance_echoue_en_production(): void
    {
        $this->basculeEnProduction();

        $this->expectException(RuntimeException::class);

        (new InsuranceMockProvider)->verifyWebhook('{"target":"policy"}', []);
    }

    // ---------------------------------------------------------------------- SMS

    /**
     * (a) ATTAQUE — /webhooks/sms/mock en production.
     * Assertion qui tombe sans le correctif : assertStatus(400) (redevient 200)
     * et assertSame(0, SmsWebhookEvent::count()).
     */
    public function test_attaque_sms_le_provider_mock_est_injoignable_en_production(): void
    {
        $this->basculeEnProduction();

        $this->postJson(self::URL_PROD_SMS, [
            'external_id' => 'mock_sms_victime',
            'status' => 'delivered',
        ])->assertStatus(400);

        $this->assertSame(0, SmsWebhookEvent::count());
    }

    /**
     * (b) ATTAQUE — segment « mock » alors que la configuration dit « twilio ».
     * L'attaquant contourne ainsi le HMAC Twilio.
     * Assertion qui tombe sans le correctif : assertStatus(400) (redevient 200).
     */
    public function test_attaque_sms_un_segment_different_du_provider_configure_est_refuse(): void
    {
        $this->app->bind(SmsProviderInterface::class, TwilioSmsProvider::class);
        config([
            'sms.default_provider' => 'twilio',
            'sms.providers.twilio.token' => 'secret_twilio',
            'sms.providers.twilio.verify_signature' => true,
        ]);

        $this->postJson('/webhooks/sms/mock', [
            'external_id' => 'mock_sms_victime',
            'status' => 'delivered',
        ])->assertStatus(400);

        $this->assertSame(0, SmsWebhookEvent::count());
    }

    /** CONTRÔLE POSITIF — le segment égal au provider configuré reste accepté. */
    public function test_sms_le_segment_egal_au_provider_configure_reste_accepte(): void
    {
        $this->postJson('/webhooks/sms/mock', [
            'external_id' => 'mock_sms_legitime',
            'status' => 'delivered',
        ])->assertOk();

        $this->assertSame(1, SmsWebhookEvent::count());
        $this->assertSame('mock', SmsWebhookEvent::firstOrFail()->provider);
    }

    /** (2) Le vérificateur mock SMS refuse en production, même appelé directement. */
    public function test_le_verificateur_mock_sms_echoue_en_production(): void
    {
        $this->basculeEnProduction();

        $this->expectException(RuntimeException::class);

        (new SmsMockProvider)->verifyWebhook('{"external_id":"x"}', []);
    }
}
