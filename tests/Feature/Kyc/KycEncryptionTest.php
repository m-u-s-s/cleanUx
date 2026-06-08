<?php

namespace Tests\Feature\Kyc;

use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L7 — KYC identity PII (metadata / result_summary) must be encrypted at rest, while legacy
 * plaintext rows remain readable (fallback cast) and get encrypted by the backfill migration.
 */
class KycEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private function makeKyc(array $attrs = []): KycVerification
    {
        return KycVerification::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'provider' => 'mock',
            'status' => 'clear',
            'decision' => 'approved',
        ], $attrs));
    }

    public function test_metadata_is_encrypted_at_rest_and_round_trips(): void
    {
        $kyc = $this->makeKyc([
            'metadata' => ['first_name' => 'Alice', 'last_name' => 'TopSecret'],
            'result_summary' => ['checks' => ['document' => 'clear']],
        ]);

        // Model reads back the decrypted array.
        $this->assertSame('TopSecret', $kyc->fresh()->metadata['last_name']);
        $this->assertSame('clear', $kyc->fresh()->result_summary['checks']['document']);

        // Raw column value is ciphertext — the plaintext must not appear.
        $rawMeta = DB::table('kyc_verifications')->where('id', $kyc->id)->value('metadata');
        $this->assertStringNotContainsString('TopSecret', (string) $rawMeta);
        $this->assertNotEmpty($rawMeta);
    }

    public function test_legacy_plaintext_is_readable_then_backfilled(): void
    {
        $user = User::factory()->create();

        // Simulate a legacy row written before encryption: raw plaintext JSON via DB::table.
        $id = DB::table('kyc_verifications')->insertGetId([
            'user_id' => $user->id,
            'provider' => 'mock',
            'status' => 'clear',
            'decision' => 'approved',
            'metadata' => json_encode(['first_name' => 'Bob', 'last_name' => 'LegacyPlain']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fallback cast reads the legacy plaintext gracefully (no DecryptException).
        $this->assertSame('LegacyPlain', KycVerification::find($id)->metadata['last_name']);

        // Backfill encrypts it at rest.
        $migration = require base_path('database/migrations/2026_06_08_000006_encrypt_kyc_verification_pii.php');
        $migration->up();

        $rawMeta = DB::table('kyc_verifications')->where('id', $id)->value('metadata');
        $this->assertStringNotContainsString('LegacyPlain', (string) $rawMeta);
        // Still readable after backfill.
        $this->assertSame('LegacyPlain', KycVerification::find($id)->metadata['last_name']);
    }
}
