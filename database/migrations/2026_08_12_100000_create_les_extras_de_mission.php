<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LES SUPPLÉMENTS PROPOSÉS SUR PLACE. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mission_extras')) {
            return;
        }

        Schema::create('mission_extras', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('mission_id');
            $table->unsignedBigInteger('proposed_by_user_id')->nullable();

            $table->string('label');
            $table->text('description')->nullable();

            // EN CENTIMES, comme tout l'argent de ce dépôt.
            $table->integer('price_cents');
            $table->string('currency', 3)->default('EUR');

            // Le devis Pricing v2 qui justifie ce montant. Nullable : un supplément saisi par
            // l'administration lors d'une reprise n'en a pas forcément.
            $table->unsignedBigInteger('price_quote_id')->nullable();

            $table->string('status', 20)->default('proposed');

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('charged_at')->nullable();

            // Le prélèvement incrémental est une charge Stripe à part entière, distincte de celle du
            // devis d'origine : elle a son propre identifiant, et son propre sort en cas d'échec.
            $table->string('stripe_payment_intent_id')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            // Nom court : MySQL refuse un nom d'index au-delà de 64 caractères, et celui que Laravel
            // génère par défaut pour deux colonnes sur cette table en approche.
            $table->index(['mission_id', 'status'], 'mission_extras_status_idx');

            $table->foreign('mission_id')->references('id')->on('missions')->cascadeOnDelete();
            $table->foreign('proposed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_extras');
    }
};
