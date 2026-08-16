<?php

namespace Tests\Feature\Kyc;

use App\Models\KycCheck;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Kyc\KycStartRequest;
use App\Services\Kyc\Providers\OnfidoProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OnfidoProviderUnitTest extends TestCase
{
    protected OnfidoProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kyc.providers.onfido.api_token' => 'test_token',
            'kyc.providers.onfido.region' => 'eu',
            'kyc.providers.onfido.webhook_token' => 'secret_hmac',
        ]);

        $this->provider = new OnfidoProvider;
    }

    private function makeUser(string $name = 'John Doe', string $email = 'john@example.com'): User
    {
        return new User(['name' => $name, 'email' => $email]);
    }

    public function test_name_returns_onfido(): void
    {
        $this->assertSame('onfido', $this->provider->name());
    }

    public function test_start_verification_happy_path_posts_applicant_and_returns_id(): void
    {
        Http::fake([
            'api.eu.onfido.com/*' => Http::response(['id' => 'applicant_123', 'first_name' => 'John'], 201),
        ]);

        $request = new KycStartRequest($this->makeUser('John Doe', 'john@example.com'), 'be');

        $result = $this->provider->startVerification($request);

        $this->assertSame('applicant_123', $result->externalApplicantId);
        $this->assertNull($result->externalCheckId);
        $this->assertNull($result->hostedFlowUrl);
        $this->assertSame('John', $result->raw['first_name']);

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'api.eu.onfido.com/v3.6/applicants')
                && $req['first_name'] === 'John'
                && $req['last_name'] === 'Doe'
                && $req['email'] === 'john@example.com'
                && $req['location']['country_of_residence'] === 'BE';
        });
    }

    public function test_start_verification_single_word_name_uses_dash_lastname(): void
    {
        Http::fake([
            'api.eu.onfido.com/*' => Http::response(['id' => 'applicant_x'], 201),
        ]);

        $request = new KycStartRequest($this->makeUser('Madonna', 'm@example.com'), 'fr');

        $this->provider->startVerification($request);

        Http::assertSent(function ($req) {
            return $req['first_name'] === 'Madonna' && $req['last_name'] === '-';
        });
    }

    public function test_start_verification_throws_when_create_applicant_fails(): void
    {
        Http::fake([
            'api.eu.onfido.com/*' => Http::response(['error' => 'bad'], 422),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Onfido create applicant failed');

        $this->provider->startVerification(new KycStartRequest($this->makeUser(), 'be'));
    }

    public function test_client_throws_when_api_token_missing(): void
    {
        config(['kyc.providers.onfido.api_token' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Onfido API token missing');

        $this->provider->startVerification(new KycStartRequest($this->makeUser(), 'be'));
    }

    public function test_client_uses_us_base_url_for_us_region(): void
    {
        config(['kyc.providers.onfido.region' => 'us']);
        Http::fake(['api.us.onfido.com/*' => Http::response(['id' => 'a'], 201)]);

        $this->provider->startVerification(new KycStartRequest($this->makeUser(), 'us'));

        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.us.onfido.com/v3.6/applicants'));
    }

    public function test_client_uses_ca_base_url_for_ca_region(): void
    {
        config(['kyc.providers.onfido.region' => 'CA']);
        Http::fake(['api.ca.onfido.com/*' => Http::response(['id' => 'a'], 201)]);

        $this->provider->startVerification(new KycStartRequest($this->makeUser(), 'ca'));

        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.ca.onfido.com/v3.6/applicants'));
    }

    public function test_fetch_status_without_check_id_returns_awaiting_docs(): void
    {
        $verification = new KycVerification(['external_check_id' => null]);

        $result = $this->provider->fetchStatus($verification);

        $this->assertSame(KycVerification::STATUS_AWAITING_DOCS, $result->status);
        $this->assertSame(KycVerification::DECISION_PENDING, $result->decision);
    }

    public function test_fetch_status_clear_check_maps_to_approved(): void
    {
        Http::fake([
            'api.eu.onfido.com/*' => Http::response([
                'result' => 'clear',
                'status' => 'complete',
                'report_ids' => ['rep_1', 'rep_2'],
            ], 200),
        ]);

        $verification = new KycVerification(['external_check_id' => 'check_1']);

        $result = $this->provider->fetchStatus($verification);

        $this->assertSame(KycVerification::STATUS_CLEAR, $result->status);
        $this->assertSame(KycVerification::DECISION_APPROVED, $result->decision);
        $this->assertNull($result->rejectionReason);
        $this->assertCount(2, $result->checks);
        /*
         * CETTE ASSERTION DISAIT `TYPE_DOCUMENT`, ET ELLE FIGEAIT UN DEFAUT.
         *
         * `/checks/{id}` ne rend que `report_ids` : une liste d'identifiants, sans nature.
         * L'adaptateur les rangeait TOUS en `document`, y compris le rapport de similarite faciale
         * que `config/kyc.php` demande a chaque verification -- son resultat etait donc perdu a
         * l'ecriture. Ici, les rapports ne sont pas lisibles (le faux ne repond pas a `/reports`),
         * et le bon comportement est de dire qu'on ne sait pas plutot que d'inventer un type.
         */
        $this->assertSame(KycCheck::TYPE_UNKNOWN, $result->checks[0]['type']);
        $this->assertSame(KycCheck::RESULT_CLEAR, $result->checks[0]['result']);
        $this->assertSame('rep_1', $result->checks[0]['external_id']);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.eu.onfido.com/v3.6/checks/check_1'));
    }

    /**
     * TEMOIN DU CORRECTIF : quand les rapports SONT lisibles, chacun garde sa nature -- et le
     * resultat facial atterrit enfin sur `TYPE_FACIAL_SIMILARITY`, la constante que personne
     * n'ecrivait jamais.
     */
    public function test_fetch_status_reads_report_types_when_available(): void
    {
        Http::fake([
            'api.eu.onfido.com/v3.6/checks/*' => Http::response([
                'id' => 'check_7',
                'result' => 'consider',
                'status' => 'complete',
                'report_ids' => ['rep_a', 'rep_b'],
            ], 200),
            'api.eu.onfido.com/v3.6/reports*' => Http::response([
                'reports' => [
                    ['id' => 'rep_a', 'name' => 'document', 'result' => 'clear'],
                    [
                        'id' => 'rep_b',
                        'name' => 'facial_similarity_photo',
                        'result' => 'consider',
                        'sub_result' => 'rejected',
                        'properties' => ['score' => 0.31],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->provider->fetchStatus(new KycVerification(['external_check_id' => 'check_7']));

        $this->assertCount(2, $result->checks);
        $this->assertSame(KycCheck::TYPE_DOCUMENT, $result->checks[0]['type']);
        $this->assertSame(KycCheck::RESULT_CLEAR, $result->checks[0]['result']);

        $this->assertSame(KycCheck::TYPE_FACIAL_SIMILARITY, $result->checks[1]['type']);
        $this->assertSame(KycCheck::RESULT_CONSIDER, $result->checks[1]['result']);
        $this->assertSame('rejected', $result->checks[1]['sub_result']);
        $this->assertSame(0.31, $result->checks[1]['confidence']);
    }

    public function test_fetch_status_consider_check_maps_to_manual_review_with_sub_result(): void
    {
        Http::fake([
            'api.eu.onfido.com/*' => Http::response([
                'result' => 'consider',
                'status' => 'complete',
                'sub_result' => 'caution',
                'report_ids' => ['rep_9'],
            ], 200),
        ]);

        $result = $this->provider->fetchStatus(new KycVerification(['external_check_id' => 'c']));

        $this->assertSame(KycVerification::STATUS_CONSIDER, $result->status);
        $this->assertSame(KycVerification::DECISION_MANUAL_REVIEW, $result->decision);
        $this->assertSame('caution', $result->rejectionReason);
        $this->assertSame(KycCheck::RESULT_CONSIDER, $result->checks[0]['result']);
    }

    public function test_fetch_status_throws_when_check_fails(): void
    {
        Http::fake(['api.eu.onfido.com/*' => Http::response('boom', 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Onfido fetch check failed');

        $this->provider->fetchStatus(new KycVerification(['external_check_id' => 'c']));
    }

    public function test_fetch_status_status_only_branches(): void
    {
        $cases = [
            ['status' => 'awaiting_data', 'expected' => KycVerification::STATUS_AWAITING_DOCS],
            ['status' => 'in_progress', 'expected' => KycVerification::STATUS_IN_REVIEW],
            ['status' => 'withdrawn', 'expected' => KycVerification::STATUS_CANCELLED],
            ['status' => 'something_else', 'expected' => KycVerification::STATUS_IN_REVIEW],
        ];

        Http::fake([
            'api.eu.onfido.com/*' => Http::sequence()
                ->push(['status' => 'awaiting_data'], 200)
                ->push(['status' => 'in_progress'], 200)
                ->push(['status' => 'withdrawn'], 200)
                ->push(['status' => 'something_else'], 200),
        ]);

        foreach ($cases as $case) {
            $result = $this->provider->fetchStatus(new KycVerification(['external_check_id' => 'c']));

            $this->assertSame($case['expected'], $result->status, "status={$case['status']}");
            $this->assertSame(KycVerification::DECISION_PENDING, $result->decision);
        }
    }

    public function test_verify_webhook_returns_decoded_payload_with_valid_signature(): void
    {
        $payload = json_encode(['payload' => ['resource_type' => 'check']]);
        $signature = hash_hmac('sha256', $payload, 'secret_hmac');

        $decoded = $this->provider->verifyWebhook($payload, ['X-SHA2-Signature' => [$signature]]);

        $this->assertSame('check', $decoded['payload']['resource_type']);
    }

    public function test_verify_webhook_accepts_lowercase_header(): void
    {
        $payload = json_encode(['a' => 1]);
        $signature = hash_hmac('sha256', $payload, 'secret_hmac');

        $decoded = $this->provider->verifyWebhook($payload, ['x-sha2-signature' => [$signature]]);

        $this->assertSame(1, $decoded['a']);
    }

    public function test_verify_webhook_non_array_payload_returns_empty(): void
    {
        $payload = '"just a string"';
        $signature = hash_hmac('sha256', $payload, 'secret_hmac');

        $decoded = $this->provider->verifyWebhook($payload, ['X-SHA2-Signature' => [$signature]]);

        $this->assertSame([], $decoded);
    }

    public function test_verify_webhook_throws_when_token_missing(): void
    {
        config(['kyc.providers.onfido.webhook_token' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Onfido webhook token missing');

        $this->provider->verifyWebhook('{}', ['X-SHA2-Signature' => ['x']]);
    }

    public function test_verify_webhook_throws_when_signature_header_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing X-SHA2-Signature header');

        $this->provider->verifyWebhook('{}', []);
    }

    public function test_verify_webhook_throws_on_invalid_signature(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Onfido webhook signature');

        $this->provider->verifyWebhook('{}', ['X-SHA2-Signature' => ['deadbeef']]);
    }

    public function test_map_webhook_event_returns_null_for_non_check_resource(): void
    {
        $result = $this->provider->mapWebhookEvent([
            'payload' => ['resource_type' => 'report', 'object' => ['id' => 'x']],
        ]);

        $this->assertNull($result);
    }

    public function test_map_webhook_event_returns_null_for_empty_object(): void
    {
        $result = $this->provider->mapWebhookEvent([
            'payload' => ['resource_type' => 'check', 'object' => []],
        ]);

        $this->assertNull($result);
    }

    public function test_map_webhook_event_maps_check_object(): void
    {
        $result = $this->provider->mapWebhookEvent([
            'payload' => [
                'resource_type' => 'check',
                'object' => ['result' => 'clear', 'status' => 'complete', 'report_ids' => ['r1']],
            ],
        ]);

        $this->assertNotNull($result);
        $this->assertSame(KycVerification::STATUS_CLEAR, $result->status);
        $this->assertSame(KycVerification::DECISION_APPROVED, $result->decision);
        $this->assertCount(1, $result->checks);
    }
}
