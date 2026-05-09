<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 14 — Suivi de progression onboarding sur ProviderProfile.
 *
 * onboarding_step : étape courante (0..6)
 *   0. profile_basics  (nom, photo, bio)
 *   1. identity        (carte d'identité)
 *   2. tax             (numéro TVA / SIREN selon pays)
 *   3. insurance       (attestation responsabilité civile pro)
 *   4. skills          (sélection métiers + zones)
 *   5. stripe_connect  (onboarding Stripe)
 *   6. ready           (admin a validé)
 *
 * onboarding_completed_at : quand toutes les étapes sont OK + admin valide
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'onboarding_step')) {
                $table->unsignedTinyInteger('onboarding_step')->default(0)->after('verification_status');
            }
            if (! Schema::hasColumn('provider_profiles', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_step');
            }
            if (! Schema::hasColumn('provider_profiles', 'bio')) {
                $table->text('bio')->nullable()->after('onboarding_completed_at');
            }
            if (! Schema::hasColumn('provider_profiles', 'photo_path')) {
                $table->string('photo_path', 500)->nullable()->after('bio');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            foreach (['onboarding_step', 'onboarding_completed_at', 'bio', 'photo_path'] as $col) {
                if (Schema::hasColumn('provider_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
