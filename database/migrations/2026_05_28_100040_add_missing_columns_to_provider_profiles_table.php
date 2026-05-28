<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\ProviderProfile.
 *
 * The model declares onboarding_started_at (datetime), verified_at (datetime),
 * verification_notes (free text) and battery_level (integer) as fillable/cast
 * with no matching column on provider_profiles. Added defensively.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'onboarding_started_at')) {
                $table->timestamp('onboarding_started_at')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'verification_notes')) {
                $table->text('verification_notes')->nullable();
            }
            if (! Schema::hasColumn('provider_profiles', 'battery_level')) {
                $table->unsignedInteger('battery_level')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            foreach ([
                'onboarding_started_at',
                'verified_at',
                'verification_notes',
                'battery_level',
            ] as $col) {
                if (Schema::hasColumn('provider_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
