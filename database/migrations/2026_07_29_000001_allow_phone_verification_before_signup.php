<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifier un téléphone AVANT que le compte existe.
 *
 * Le parcours d'inscription prestataire demande le téléphone en premier écran et le vérifie par
 * OTP avant toute autre saisie — c'est le pattern Uber/Heetch, et c'est ce qui fait du numéro
 * l'identifiant opérationnel du prestataire plutôt qu'un champ de profil parmi d'autres.
 *
 * Or `phone_verification_codes.user_id` était NOT NULL avec clé étrangère : le module OTP ne
 * savait vérifier que le téléphone d'un compte déjà créé. D'où ces deux changements :
 *
 *  - `user_id` devient nullable : une demande de code d'inscription n'a pas encore de compte à
 *    rattacher. Les codes rattachés à un compte continuent de fonctionner à l'identique.
 *  - `consumed_at` marque le code déjà échangé contre une inscription. Sans lui, le jeton remis
 *    après vérification servirait à créer autant de comptes que voulu sur un seul numéro vérifié.
 */
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
