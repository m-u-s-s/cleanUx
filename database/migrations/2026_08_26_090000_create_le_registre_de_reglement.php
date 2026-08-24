<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LE REGISTRE DES COMPTES QUI REÇOIVENT LA COMMISSION BRIO. Ce registre ATTESTE, il ne pilote pas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settlement_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('label');
            $table->string('currency', 3);
            $table->string('country', 2)->nullable();

            $table->string('bank_name')->nullable();
            $table->string('holder_name')->nullable();
            // Quatre derniers caractères SEULEMENT — voir l'en-tête.
            $table->string('iban_last4', 4)->nullable();
            $table->string('stripe_external_account_id')->nullable();

            // 'primary' = destination annoncée des versements ; 'backup' = compte de secours
            // vérifié d'avance, seul moyen de basculer de banque en une journée.
            $table->string('role', 16)->default('backup');
            // 'draft' → déclaré ; 'verified' → vérifié chez Stripe ; 'retired' → hors service.
            $table->string('status', 16)->default('draft');

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Noms d'index courts et explicites : au-delà de 64 caractères MySQL refuse la
            // migration, ce que SQLite ne signale jamais.
            $table->index(['currency', 'role'], 'psa_devise_role_idx');
            $table->index('status', 'psa_statut_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settlement_accounts');
    }
};
