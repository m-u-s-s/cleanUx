<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LE FIL DE DISCUSSION D'UNE RÉCLAMATION CLIENT. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_claim_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_claim_id')
                ->constrained('customer_claims')
                ->cascadeOnDelete();

            // Qui parle. La vue s'en sert pour la couleur et le libellé : « Vous », « Support
            // Brio », « Prestataire », « Système ».
            $table->string('author_role', 20)->default('client');

            // L'auteur RÉEL quand il y en a un — les messages du système n'en ont pas.
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            $table->timestamps();

            // On lit toujours un fil entier, du plus ancien au plus récent.
            $table->index(['customer_claim_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_claim_events');
    }
};
