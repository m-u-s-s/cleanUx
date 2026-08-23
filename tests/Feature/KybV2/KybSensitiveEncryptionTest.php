<?php

namespace Tests\Feature\KybV2;

use App\Models\BusinessBeneficialOwner;
use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Audit LOW (RGPD/sécurité) — les charges utiles de screening sanctions et les métadonnées des bénéficiaires effectifs (PII) doivent être chiffrées au repos. */
class KybSensitiveEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctions_match_payload_is_encrypted_at_rest(): void
    {
        $secret = 'SANCTIONED-PERSON-XYZ-123';

        $check = BusinessSanctionsCheck::create([
            'entity_id' => BusinessEntity::factory()->create()->id,
            'list_name' => 'EU',
            'status' => 'match',
            'match_count' => 1,
            'match_payload' => ['name' => $secret],
            'provider' => 'mock',
            'checked_at' => now(),
        ]);

        $raw = (string) DB::table('business_sanctions_checks')->where('id', $check->id)->value('match_payload');
        $this->assertStringNotContainsString($secret, $raw, 'Le payload sanctions ne doit pas être en clair en base.');
        $this->assertSame($secret, $check->fresh()->match_payload['name'], 'Il doit se déchiffrer à la lecture.');
    }

    public function test_beneficial_owner_metadata_is_encrypted_at_rest(): void
    {
        $secret = 'PEP-SECRET-NOTE-456';

        $owner = BusinessBeneficialOwner::create([
            'entity_id' => BusinessEntity::factory()->create()->id,
            'full_name' => 'Jane Doe',
            'ownership_percent' => 30,
            'aml_status' => 'pending',
            'metadata' => ['note' => $secret],
        ]);

        $raw = (string) DB::table('business_beneficial_owners')->where('id', $owner->id)->value('metadata');
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertSame($secret, $owner->fresh()->metadata['note']);
    }
}
