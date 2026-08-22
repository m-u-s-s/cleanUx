<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE REGISTRE DES COMPTES QUI REÇOIVENT LA COMMISSION BRIO.
 *
 * Ce registre ATTESTE, il ne pilote pas. Le compte qui reçoit réellement les versements est réglé
 * chez Stripe, protégé par sa propre double authentification et sa vérification bancaire — et
 * c'est délibéré : si la console d'administration pouvait rediriger les versements, un compte
 * super-administrateur compromis suffirait à détourner l'encaissement suivant.
 *
 * AUCUN IBAN COMPLET N'EST STOCKÉ, seulement ses quatre derniers caractères. Le registre sert à
 * reconnaître un compte et à tracer les changements, pas à le rejouer ; un IBAN complet en base
 * serait une cible sans contrepartie, puisque Stripe reste seul à en avoir besoin.
 *
 * UNE LIGNE PAR DEVISE ET PAR RÔLE : Stripe verse par devise, et une plateforme qui opère dans
 * plusieurs pays a donc autant de comptes de destination que de devises encaissées. Le compte de
 * secours se déclare par devise pour la même raison — un secours en euro ne dépanne pas un
 * versement en livre sterling.
 */
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
