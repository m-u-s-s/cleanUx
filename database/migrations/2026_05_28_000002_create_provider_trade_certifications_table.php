<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: la table peut déjà exister sur une base ayant subi un drift
        // d'historique (objet présent mais migration non enregistrée). Cohérent avec
        // les 44 autres migrations gardées du lot.
        if (Schema::hasTable('provider_trade_certifications')) {
            return;
        }

        Schema::create('provider_trade_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->string('certification_type'); // diploma, attestation, experience
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending'); // pending, verified, rejected
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Explicit short name: the auto-generated name
            // (provider_trade_certifications_user_id_trade_id_certification_type_unique, 72 chars)
            // exceeds MySQL's 64-char identifier limit (SQLSTATE 1059). (issue #3)
            $table->unique(['user_id', 'trade_id', 'certification_type'], 'ptc_user_trade_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_trade_certifications');
    }
};
