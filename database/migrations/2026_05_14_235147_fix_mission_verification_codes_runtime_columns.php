<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_verification_codes')) {
            return;
        }

        Schema::table('mission_verification_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_verification_codes', 'code_type')) {
                $table->string('code_type')->default('start')->after('mission_id');
            }

            if (! Schema::hasColumn('mission_verification_codes', 'code_hash')) {
                $table->string('code_hash')->nullable()->after('code_type');
            }

            if (! Schema::hasColumn('mission_verification_codes', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('code_hash');
            }

            if (! Schema::hasColumn('mission_verification_codes', 'validated_by_user_id')) {
                $table->foreignId('validated_by_user_id')->nullable()->after('expires_at');
            }

            if (! Schema::hasColumn('mission_verification_codes', 'validated_at')) {
                $table->timestamp('validated_at')->nullable()->after('validated_by_user_id');
            }

            if (! Schema::hasColumn('mission_verification_codes', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0)->after('validated_at');
            }

            if (! Schema::hasColumn('mission_verification_codes', 'is_consumed')) {
                $table->boolean('is_consumed')->default(false)->after('attempts');
            }
        });
    }

    public function down(): void
    {
        //
    }
};