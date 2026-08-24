<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** L7 — encrypt existing kyc_verifications.metadata / result_summary at rest. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kyc_verifications')) {
            return;
        }

        $columns = array_values(array_filter(
            ['metadata', 'result_summary'],
            fn ($c) => Schema::hasColumn('kyc_verifications', $c)
        ));
        if (! $columns) {
            return;
        }

        DB::table('kyc_verifications')
            ->select(array_merge(['id'], $columns))
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($columns) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $col) {
                        $raw = $row->{$col};
                        if ($raw === null || $raw === '') {
                            continue;
                        }
                        // Already encrypted? leave it. Otherwise encrypt the raw JSON in place.
                        try {
                            Crypt::decryptString($raw);

                            continue;
                        } catch (Throwable $e) {
                            $updates[$col] = Crypt::encryptString($raw);
                        }
                    }
                    if ($updates) {
                        DB::table('kyc_verifications')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op: we don't decrypt back to plaintext (the fallback cast still reads these rows).
    }
};
