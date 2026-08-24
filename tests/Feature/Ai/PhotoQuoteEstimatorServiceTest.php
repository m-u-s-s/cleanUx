<?php

namespace Tests\Feature\Ai;

use App\Models\Trade;
use App\Services\Ai\PhotoQuoteEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhotoQuoteEstimatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private function base64WithSignature(string $signaturePrefix): string
    {
        // Pad to at least 24 decoded bytes so the first 32 base64 chars decode cleanly.
        return base64_encode(str_pad($signaturePrefix, 32, "\x00"));
    }

    public function test_returns_null_when_api_key_missing(): void
    {
        config(['services.anthropic.key' => null]);
        putenv('ANTHROPIC_API_KEY=');

        Http::fake();

        $trade = Trade::factory()->create();
        $estimator = new PhotoQuoteEstimator;

        $result = $estimator->estimateFromPhoto($this->base64WithSignature("\xff\xd8\xff\xe0"), $trade);

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_parsed_success_quote_on_happy_path(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        $payload = [
            'surface_estimee_m2' => 24.5,
            'etat_observe' => 'bon état',
            'estimated_duration_minutes_min' => 180,
            'prix_min_cents' => 9000,
            'prix_max_cents' => 12000,
            'confiance' => 82,
            'observations' => 'Pièce moyenne, peu de saleté.',
            'options_suggerees' => ['vitres', 'sols'],
        ];

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode($payload)]],
            ], 200),
        ]);

        $trade = Trade::factory()->create(['name' => 'Nettoyage', 'code' => 'CLEAN']);
        $result = (new PhotoQuoteEstimator)->estimateFromPhoto(
            $this->base64WithSignature("\xff\xd8\xff\xe0"),
            $trade,
            'Cuisine sale'
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('CLEAN', $result['trade_code']);
        $this->assertSame('Nettoyage', $result['trade_name']);
        $this->assertSame(24.5, $result['surface_estimee_m2']);
        $this->assertSame(180, $result['estimated_duration_minutes_min']);
        $this->assertSame(9000, $result['prix_min_cents']);
        $this->assertSame(12000, $result['prix_max_cents']);
        $this->assertSame(90.0, $result['prix_min_eur']);
        $this->assertSame(120.0, $result['prix_max_eur']);
        $this->assertSame(82, $result['confiance']);
        $this->assertSame(['vitres', 'sols'], $result['options_suggerees']);

        // Confirm the user note made it into the request and JPEG mime was detected.
        Http::assertSent(function ($req) {
            $body = $req->data();
            $content = $body['messages'][0]['content'];

            return $content[0]['source']['media_type'] === 'image/jpeg'
                && str_contains($content[1]['text'], 'Cuisine sale')
                && str_contains($body['system'], 'Nettoyage');
        });
    }

    public function test_returns_error_structure_when_claude_reports_not_relevant(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => "```json\n".json_encode([
                        'error' => 'photo_not_relevant',
                        'reason' => 'Photo de chat',
                    ])."\n```",
                ]],
            ], 200),
        ]);

        $trade = Trade::factory()->create(['code' => 'PAINT']);
        $result = (new PhotoQuoteEstimator)->estimateFromPhoto(
            $this->base64WithSignature("\x89PNG\r\n\x1a\n"),
            $trade
        );

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('photo_not_relevant', $result['error']);
        $this->assertSame('Photo de chat', $result['reason']);
        $this->assertSame('PAINT', $result['trade_code']);

        // PNG signature should be detected.
        Http::assertSent(function ($req) {
            return $req->data()['messages'][0]['content'][0]['source']['media_type'] === 'image/png';
        });
    }

    public function test_returns_null_on_non_ok_http_status(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $trade = Trade::factory()->create();
        $result = (new PhotoQuoteEstimator)->estimateFromPhoto(
            $this->base64WithSignature('RIFF'),
            $trade
        );

        $this->assertNull($result);
    }

    public function test_returns_null_when_response_text_is_not_json(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Je ne peux pas analyser cette photo.']],
            ], 200),
        ]);

        $trade = Trade::factory()->create();
        $result = (new PhotoQuoteEstimator)->estimateFromPhoto(
            $this->base64WithSignature('RIFF'),
            $trade
        );

        $this->assertNull($result);

        // WEBP signature should be detected on the sent request.
        Http::assertSent(function ($req) {
            return $req->data()['messages'][0]['content'][0]['source']['media_type'] === 'image/webp';
        });
    }

    public function test_returns_null_when_http_throws(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake(function () {
            throw new ConnectionException('network down');
        });

        $trade = Trade::factory()->create();
        $result = (new PhotoQuoteEstimator)->estimateFromPhoto(
            $this->base64WithSignature("\xff\xd8\xff\xe0"),
            $trade
        );

        $this->assertNull($result);
    }

    public function test_falls_back_to_jpeg_for_unrecognized_or_short_signature(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode(['confiance' => 10])]],
            ], 200),
        ]);

        // 'AB' decodes to fewer than 4 bytes -> early jpeg fallback branch.
        $trade = Trade::factory()->create();
        $result = (new PhotoQuoteEstimator)->estimateFromPhoto('AB', $trade);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        // Defaults applied for missing fields.
        $this->assertSame(0, $result['prix_min_cents']);
        $this->assertSame('', $result['etat_observe']);
        $this->assertSame([], $result['options_suggerees']);

        Http::assertSent(function ($req) {
            return $req->data()['messages'][0]['content'][0]['source']['media_type'] === 'image/jpeg';
        });
    }
}
