<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            foreach ([
                'kyc_provider' => fn ($t) => $t->string('kyc_provider', 32)->nullable(),
                'kyc_external_applicant_id' => fn ($t) => $t->string('kyc_external_applicant_id', 128)->nullable(),
                'kyc_last_verification_id' => fn ($t) => $t->unsignedBigInteger('kyc_last_verification_id')->nullable(),
                'kyc_completed_at' => fn ($t) => $t->timestamp('kyc_completed_at')->nullable(),
                'kyc_score' => fn ($t) => $t->decimal('kyc_score', 4, 2)->nullable(),
            ] as $col => $builder) {
                if (! Schema::hasColumn('provider_profiles', $col)) {
                    $builder($table);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            foreach ([
                'kyc_provider', 'kyc_external_applicant_id', 'kyc_last_verification_id',
                'kyc_completed_at', 'kyc_score',
            ] as $col) {
                if (Schema::hasColumn('provider_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
