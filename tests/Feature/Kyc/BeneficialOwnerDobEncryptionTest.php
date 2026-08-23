<?php

namespace Tests\Feature\Kyc;

use App\Models\BusinessBeneficialOwner;
use Database\Factories\BusinessEntityFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** L7 (part 2) — business_beneficial_owners.date_of_birth is encrypted at rest, while legacy plaintext rows stay readable and get encrypted by the backfill migration. */
class BeneficialOwnerDobEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dob_is_encrypted_at_rest_and_round_trips(): void
    {
        $entityId = BusinessEntityFactory::new()->create()->id;

        $owner = BusinessBeneficialOwner::create([
            'entity_id' => $entityId,
            'full_name' => 'Jane Owner',
            'date_of_birth' => '1980-07-15',
        ]);

        $this->assertSame('1980-07-15', $owner->fresh()->date_of_birth);

        $raw = DB::table('business_beneficial_owners')->where('id', $owner->id)->value('date_of_birth');
        $this->assertStringNotContainsString('1980-07-15', (string) $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_legacy_plaintext_is_readable_then_backfilled(): void
    {
        $entityId = BusinessEntityFactory::new()->create()->id;

        $id = DB::table('business_beneficial_owners')->insertGetId([
            'entity_id' => $entityId,
            'full_name' => 'Legacy Owner',
            'date_of_birth' => '1975-03-22', // plaintext, as written before encryption
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fallback cast reads the legacy plaintext without throwing.
        $this->assertSame('1975-03-22', BusinessBeneficialOwner::find($id)->date_of_birth);

        $migration = require base_path('database/migrations/2026_06_08_000009_encrypt_beneficial_owner_dob.php');
        $migration->up();

        $raw = DB::table('business_beneficial_owners')->where('id', $id)->value('date_of_birth');
        $this->assertStringNotContainsString('1975-03-22', (string) $raw);
        $this->assertSame('1975-03-22', BusinessBeneficialOwner::find($id)->date_of_birth);
    }
}
