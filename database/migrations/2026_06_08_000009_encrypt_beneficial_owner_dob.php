<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** L7 (part 2) — encrypt business_beneficial_owners.date_of_birth at rest. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_beneficial_owners') || ! Schema::hasColumn('business_beneficial_owners', 'date_of_birth')) {
            return;
        }

        // 1. Widen DATE -> string so it can store ciphertext.
        Schema::table('business_beneficial_owners', function (Blueprint $table) {
            $table->string('date_of_birth')->nullable()->change();
        });

        // 2. Encrypt existing plaintext values in place (raw, bypassing the model cast).
        DB::table('business_beneficial_owners')
            ->select(['id', 'date_of_birth'])
            ->whereNotNull('date_of_birth')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $raw = $row->date_of_birth;
                    if ($raw === null || $raw === '') {
                        continue;
                    }
                    try {
                        Crypt::decryptString($raw); // already encrypted → skip

                        continue;
                    } catch (Throwable $e) {
                        DB::table('business_beneficial_owners')
                            ->where('id', $row->id)
                            ->update(['date_of_birth' => Crypt::encryptString((string) $raw)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op: we don't decrypt back to plaintext, and the column stays a (wider) string.
        // The EncryptedStringFallback cast continues to read these rows.
    }
};
