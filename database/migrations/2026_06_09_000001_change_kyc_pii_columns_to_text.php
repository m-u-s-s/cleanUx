<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L7 fix — kyc_verifications.metadata / result_summary were JSON columns, but L7 now stores
 * ENCRYPTED ciphertext in them (EncryptedArrayFallback cast). MySQL rejects non-JSON text in a
 * JSON column (SQLSTATE 3140), while SQLite (JSON = TEXT, no validation) silently accepted it —
 * so the bug only surfaced on the MySQL+FK CI job. Widen both to longtext so ciphertext fits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kyc_verifications')) {
            return;
        }

        Schema::table('kyc_verifications', function (Blueprint $table) {
            foreach (['metadata', 'result_summary'] as $col) {
                if (Schema::hasColumn('kyc_verifications', $col)) {
                    $table->longText($col)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally not reverting to JSON: encrypted values are not valid JSON, so a JSON
        // column would reject them. Leaving as longtext is safe for both engines.
    }
};
