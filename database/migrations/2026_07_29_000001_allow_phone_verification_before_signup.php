<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Vérifier un téléphone AVANT que le compte existe. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->timestamp('consumed_at')->nullable()->after('used_at');

            // Les codes d'inscription n'ont pas de user_id : c'est le couple (téléphone, objet)
            // qui les retrouve, là où l'index existant part de user_id.
            $table->index(['phone', 'purpose'], 'pvc_phone_purpose_idx');
        });
    }

    public function down(): void
    {
        // Les lignes sans user_id deviendraient invalides : on les retire avant de refermer.
        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->dropIndex('pvc_phone_purpose_idx');
            $table->dropColumn('consumed_at');
        });

        DB::table('phone_verification_codes')->whereNull('user_id')->delete();

        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
