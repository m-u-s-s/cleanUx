<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Stockage des subscriptions Web Push.
 *
 * 1 (user, navigateur, device) = 1 subscription.
 * Un user peut avoir N subscriptions (laptop + smartphone + tablette).
 *
 * Les champs endpoint/p256dh/auth viennent de pushManager.subscribe() côté browser.
 * Pour envoyer une notif → minishlink/web-push (côté PHP) avec ces 3 valeurs
 * + les clés VAPID configurées dans .env.
 *
 * Approche défensive : if (! Schema::hasTable()) pour idempotence.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            return;
        }

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Endpoint = URL de souscription chez Mozilla/Google/Apple
            $table->text('endpoint');

            // Hash de l'endpoint pour déduplication (sha256 = 64 chars)
            $table->string('endpoint_hash', 64)->index();

            // Clés cryptographiques pour signer la notif
            $table->string('p256dh', 255);
            $table->string('auth', 255);

            // Métadonnées du device (pour debug + UI "ton iPhone Safari")
            $table->string('user_agent', 500)->nullable();
            $table->string('platform', 50)->nullable();   // ios, android, desktop
            $table->string('browser', 50)->nullable();    // chrome, firefox, safari

            // Activation : permet de désactiver sans supprimer (toggle UI)
            $table->boolean('is_active')->default(true);

            // Suivi des erreurs : 5 fois → désactivation auto
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->unique(['user_id', 'endpoint_hash'], 'push_user_endpoint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
