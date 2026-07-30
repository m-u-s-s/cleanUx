<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmation de présence du prestataire, par code affiché chez le client.
 *
 * La géo-barrière atteste d'une proximité, pas d'une présence : un téléphone à 100 m de la porte
 * la franchit. Le client affiche donc un code à usage unique que le prestataire scanne sur place,
 * ce qui exige les deux appareils au même endroit.
 *
 * Le code vit sur la session de suivi, et non sur `missions` : c'est la session qui porte l'état
 * `in_mission` et qui est réellement créée à chaque intervention.
 *
 * Le code n'est jamais stocké en clair — seule son empreinte l'est, comme pour
 * `mission_verification_codes`, dont ce mécanisme reprend les garde-fous (péremption et
 * plafonnement des tentatives, un code à six chiffres étant devinable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_code_hash')) {
                $table->string('presence_code_hash')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_code_expires_at')) {
                $table->timestamp('presence_code_expires_at')->nullable()->after('presence_code_hash');
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_code_attempts')) {
                $table->unsignedSmallInteger('presence_code_attempts')->default(0)->after('presence_code_expires_at');
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_at')) {
                $table->timestamp('presence_confirmed_at')->nullable()->after('presence_code_attempts');
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_by_user_id')) {
                $table->unsignedBigInteger('presence_confirmed_by_user_id')->nullable()->after('presence_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            foreach ([
                'presence_code_hash',
                'presence_code_expires_at',
                'presence_code_attempts',
                'presence_confirmed_at',
                'presence_confirmed_by_user_id',
            ] as $column) {
                if (Schema::hasColumn('trip_tracking_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
