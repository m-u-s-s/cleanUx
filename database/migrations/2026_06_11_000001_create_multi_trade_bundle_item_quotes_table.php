<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bundle Marketplace P1 — devis concurrents par item de chantier groupé.
 *
 * Le modèle d'origine ne portait qu'UN devis par item (assigned_provider_user_id +
 * quoted_price_cents). Pour une vraie marketplace (plusieurs prestataires quotent,
 * le client compare et choisit), chaque item peut recevoir N devis ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_trade_bundle_item_quotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bundle_item_id')
                ->constrained('multi_trade_bundle_items')->cascadeOnDelete();
            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('status', [
                'pending',    // sollicité, en attente du prix
                'submitted',  // prix proposé par le prestataire
                'selected',   // choisi par le client
                'rejected',   // un autre devis a été choisi
                'withdrawn',  // retiré par le prestataire
                'expired',    // délai dépassé sans réponse / non retenu
            ])->default('pending');

            $table->unsignedInteger('price_cents')->nullable();
            $table->text('message')->nullable();

            $table->timestamp('valid_until')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            // Un seul devis par prestataire par item.
            $table->unique(['bundle_item_id', 'provider_user_id']);
            $table->index(['provider_user_id', 'status']);
            $table->index(['bundle_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_trade_bundle_item_quotes');
    }
};
