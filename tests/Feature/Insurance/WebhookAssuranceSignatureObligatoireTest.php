<?php

namespace Tests\Feature\Insurance;

use App\Jobs\Insurance\ProcessInsuranceWebhookJob;
use App\Models\InsuranceWebhookEvent;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\Providers\HiscoxInsuranceProvider;
use App\Services\Insurance\Providers\WakamInsuranceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Le lot B5 avait fermé le provider « mock » et laissé grande ouverte la porte que la
 * configuration de production emprunte réellement : Hiscox et Wakam écrivaient
 * `if ($secret && $signature) { ... }`. Sans en-tête de signature, la condition était
 * fausse, aucune vérification n'avait lieu et la charge utile était acceptée — il
 * suffisait de ne rien signer pour n'être vérifié par personne.
 *
 * Ces tests fixent les trois propriétés attendues :
 *   (a) charge utile SANS en-tête de signature  → refusée (400, rien stocké)
 *   (b) webhook légitime du provider configuré  → accepté (contrôle positif : sans lui,
 *       on ne prouve pas que la porte laisse encore passer quelqu'un)
 *   (c) segment d'URL ≠ provider configuré      → refusé (404, rien stocké)
 */
class WebhookAssuranceSignatureObligatoireTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_HISCOX = 'whsec_hiscox';

    private const SECRET_WAKAM = 'whsec_wakam';

    protected function setUp(): void
    {
        parent::setUp();

        // La CONFIGURATION désigne Hiscox : c'est elle, jamais l'URL, qui choisit le
        // vérificateur de signature.
        Config::set('insurance.default_provider', 'hiscox');
        Config::set('insurance.providers.hiscox.webhook_secret', self::SECRET_HISCOX);
        $this->app->bind(InsuranceProviderInterface::class, HiscoxInsuranceProvider::class);

        Queue::fake();
    }

    /** La configuration bascule sur Wakam (binding compris). */
    private function configureWakam(): void
    {
        Config::set('insurance.default_provider', 'wakam');
        Config::set('insurance.providers.wakam.webhook_secret', self::SECRET_WAKAM);
        $this->app->bind(InsuranceProviderInterface::class, WakamInsuranceProvider::class);
    }

    private function basculeEnProduction(): void
    {
        $this->app['env'] = 'production';
        Config::set('app.env', 'production');
    }

    private function chargeUtileHiscox(string $eventId = 'evt_hiscox_1'): string
    {
        return json_encode([
            'event_id' => $eventId,
            'event_type' => 'policy.cancelled',
            'policy_id' => 'pol_hiscox_1',
            'status' => 'cancelled',
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * POST brut : la signature porte sur le corps EXACT, pas sur un tableau re-encodé.
     *
     * @param  array<string, string>  $entetes
     */
    private function envoyer(string $url, string $corps, array $entetes = []): TestResponse
    {
        return $this->call('POST', $url, [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $entetes), $corps);
    }

    // ------------------------------------------------------------- (b) contrôle positif

    /**
     * (b) CONTRÔLE POSITIF — Hiscox configuré, signature valide : le webhook passe.
     * Sans lui, les tests de refus seraient satisfaits par une porte murée.
     * Assertion qui tombe si on annule le correctif : aucune — c'est justement le
     * point, le correctif ne doit RIEN changer pour un appelant légitime.
     */
    public function test_controle_positif_le_webhook_signe_du_provider_configure_est_accepte(): void
    {
        $corps = $this->chargeUtileHiscox();

        $this->envoyer('/webhooks/insurance/hiscox', $corps, [
            'HTTP_HISCOX_SIGNATURE' => hash_hmac('sha256', $corps, self::SECRET_HISCOX),
        ])->assertOk();

        $this->assertSame(1, InsuranceWebhookEvent::count());

        $evenement = InsuranceWebhookEvent::firstOrFail();
        $this->assertSame('hiscox', $evenement->provider);
        $this->assertSame('evt_hiscox_1', $evenement->external_event_id);

        Queue::assertPushed(ProcessInsuranceWebhookJob::class, 1);
    }

    /** (b bis) Même contrôle positif côté Wakam : /webhooks/insurance/wakam passe aussi. */
    public function test_controle_positif_wakam_signe_est_accepte(): void
    {
        $this->configureWakam();

        $corps = json_encode([
            'id' => 'evt_wakam_1',
            'type' => 'contract.cancelled',
            'contract_id' => 'ctr_1',
            'statut' => 'cancelled',
        ], JSON_THROW_ON_ERROR);

        $this->envoyer('/webhooks/insurance/wakam', $corps, [
            'HTTP_X_WAKAM_SIGNATURE' => hash_hmac('sha256', $corps, self::SECRET_WAKAM),
        ])->assertOk();

        $this->assertSame(1, InsuranceWebhookEvent::count());
        $this->assertSame('wakam', InsuranceWebhookEvent::firstOrFail()->provider);
        Queue::assertPushed(ProcessInsuranceWebhookJob::class, 1);
    }

    // -------------------------------------------------------------- (a) signature absente

    /**
     * (a) ATTAQUE — la charge utile n'est pas signée du tout.
     * Assertion qui tombe si on annule le correctif : assertStatus(400) (redevient 200),
     * assertSame(0, InsuranceWebhookEvent::count()) et Queue::assertNothingPushed().
     */
    public function test_attaque_une_charge_utile_sans_entete_de_signature_est_refusee(): void
    {
        $this->envoyer('/webhooks/insurance/hiscox', $this->chargeUtileHiscox('evt_non_signe'))
            ->assertStatus(400);

        $this->assertSame(0, InsuranceWebhookEvent::count(), 'un webhook non signé ne doit rien laisser en base');
        Queue::assertNothingPushed();
    }

    /**
     * (a bis) Un en-tête présent mais VIDE ne vaut pas mieux qu'un en-tête absent.
     * Assertion qui tombe sans le correctif : assertStatus(400).
     */
    public function test_attaque_un_entete_de_signature_vide_est_refuse(): void
    {
        $this->envoyer('/webhooks/insurance/hiscox', $this->chargeUtileHiscox('evt_signature_vide'), [
            'HTTP_HISCOX_SIGNATURE' => '   ',
        ])->assertStatus(400);

        $this->assertSame(0, InsuranceWebhookEvent::count());
        Queue::assertNothingPushed();
    }

    /** (a ter) Wakam refuse aussi la charge utile non signée. */
    public function test_attaque_wakam_sans_entete_de_signature_est_refuse(): void
    {
        $this->configureWakam();

        $this->envoyer('/webhooks/insurance/wakam', json_encode([
            'id' => 'evt_wakam_non_signe',
            'type' => 'contract.cancelled',
            'contract_id' => 'ctr_1',
        ], JSON_THROW_ON_ERROR))->assertStatus(400);

        $this->assertSame(0, InsuranceWebhookEvent::count());
        Queue::assertNothingPushed();
    }

    /** Une signature fausse reste refusée (comportement déjà présent, on l'épingle). */
    public function test_une_signature_fausse_est_refusee(): void
    {
        $this->envoyer('/webhooks/insurance/hiscox', $this->chargeUtileHiscox('evt_mal_signe'), [
            'HTTP_HISCOX_SIGNATURE' => 'deadbeef',
        ])->assertStatus(400);

        $this->assertSame(0, InsuranceWebhookEvent::count());
        Queue::assertNothingPushed();
    }

    /** Le corps signé fait foi : rejouer la signature sur un AUTRE corps est refusé. */
    public function test_une_signature_valable_pour_un_autre_corps_est_refusee(): void
    {
        $signatureDUnAutreCorps = hash_hmac('sha256', $this->chargeUtileHiscox('evt_origine'), self::SECRET_HISCOX);

        $this->envoyer('/webhooks/insurance/hiscox', $this->chargeUtileHiscox('evt_modifie'), [
            'HTTP_HISCOX_SIGNATURE' => $signatureDUnAutreCorps,
        ])->assertStatus(400);

        $this->assertSame(0, InsuranceWebhookEvent::count());
    }

    // ------------------------------------------------------------ (c) segment d'URL faux

    /**
     * (c) ATTAQUE — segment « mock » alors que la configuration dit « hiscox ».
     * C'est l'attaque d'origine : choisir un vérificateur qui ne vérifie rien.
     * Assertion qui tombe si on annule la porte : assertStatus(404) (redevient 200).
     */
    public function test_attaque_le_segment_mock_est_refuse_quand_la_configuration_dit_hiscox(): void
    {
        $this->postJson('/webhooks/insurance/mock', [
            'event_id' => 'evt_segment_mock',
            'target' => 'policy',
            'external_id' => 'pol_hiscox_1',
            'status' => 'cancelled',
        ])->assertStatus(404);

        $this->assertSame(0, InsuranceWebhookEvent::count());
        Queue::assertNothingPushed();
    }

    /**
     * (c bis) Même un AUTRE vrai provider est refusé : la configuration désigne un
     * seul assureur joignable, et le segment doit lui être égal.
     */
    public function test_attaque_le_segment_wakam_est_refuse_quand_la_configuration_dit_hiscox(): void
    {
        $corps = $this->chargeUtileHiscox('evt_segment_wakam');

        // Même signé pour Hiscox, le segment « wakam » ne désigne pas le provider configuré.
        $this->envoyer('/webhooks/insurance/wakam', $corps, [
            'HTTP_HISCOX_SIGNATURE' => hash_hmac('sha256', $corps, self::SECRET_HISCOX),
        ])->assertStatus(404);

        $this->assertSame(0, InsuranceWebhookEvent::count());
        Queue::assertNothingPushed();
    }

    // ------------------------------------------------ vérificateurs appelés directement

    /**
     * La garde vit dans le VÉRIFICATEUR, pas seulement dans le contrôleur : elle tient
     * donc même si on atteignait Hiscox par un autre chemin.
     * Assertion qui tombe si on annule le correctif : expectException (aucune exception
     * n'était levée, la charge utile était décodée et retournée).
     */
    public function test_le_verificateur_hiscox_refuse_une_charge_utile_non_signee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing hiscox-signature header.');

        (new HiscoxInsuranceProvider)->verifyWebhook($this->chargeUtileHiscox(), []);
    }

    /** Idem Wakam. */
    public function test_le_verificateur_wakam_refuse_une_charge_utile_non_signee(): void
    {
        Config::set('insurance.providers.wakam.webhook_secret', self::SECRET_WAKAM);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing x-wakam-signature header.');

        (new WakamInsuranceProvider)->verifyWebhook('{"type":"contract.cancelled"}', []);
    }

    /** La signature valide reste acceptée par le vérificateur appelé directement. */
    public function test_le_verificateur_hiscox_accepte_une_signature_valide(): void
    {
        $corps = $this->chargeUtileHiscox();

        $decode = (new HiscoxInsuranceProvider)->verifyWebhook($corps, [
            'hiscox-signature' => [hash_hmac('sha256', $corps, self::SECRET_HISCOX)],
        ]);

        $this->assertSame('policy.cancelled', $decode['event_type']);
    }

    /**
     * Secret absent = aucune signature vérifiable. En PRODUCTION c'est un refus :
     * accepter reviendrait à traiter la charge utile de n'importe qui sur une route
     * publique. (Hors production, deux tests hors périmètre épinglent encore le
     * comportement permissif — voir le commentaire des vérificateurs.)
     */
    public function test_sans_secret_configure_le_verificateur_hiscox_refuse_en_production(): void
    {
        Config::set('insurance.providers.hiscox.webhook_secret', '');
        $this->basculeEnProduction();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hiscox webhook secret missing');

        (new HiscoxInsuranceProvider)->verifyWebhook($this->chargeUtileHiscox(), []);
    }

    /** Idem Wakam : pas de secret en production, pas de webhook. */
    public function test_sans_secret_configure_le_verificateur_wakam_refuse_en_production(): void
    {
        Config::set('insurance.providers.wakam.webhook_secret', '');
        $this->basculeEnProduction();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wakam webhook secret missing');

        (new WakamInsuranceProvider)->verifyWebhook('{"type":"contract.cancelled"}', []);
    }
}
