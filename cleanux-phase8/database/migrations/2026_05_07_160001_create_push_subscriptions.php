<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — Stockage des subscriptions Web Push.
 *
 * Une subscription = un (user, navigateur, device). Un user peut avoir N subscriptions
 * (laptop + smartphone + tablette par ex.).
 *
 * Les champs endpoint/p256dh/auth viennent de pushManager.subscribe() côté browser.
 * Quand on veut envoyer une notif, on utilise minishlink/web-push (côté PHP) avec
 * ces 3 valeurs + les clés VAPID configurées dans .env.
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

            // Clés cryptographiques pour signer la notif
            $table->string('p256dh', 255);
            $table->string('auth', 255);

            // Métadonnées du device pour debug / UI ("ton iPhone Safari", etc.)
            $table->string('user_agent', 500)->nullable();
            $table->string('platform', 50)->nullable();   // ios, android, desktop
            $table->string('browser', 50)->nullable();    // chrome, firefox, safari

            // Activation : permet de désactiver sans supprimer (toggle UI)
            $table->boolean('is_active')->default(true);

            // Suivi des erreurs : si ça plante 5 fois on désactive auto
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_active']);

            // Endpoint est trop long pour un index unique standard MySQL (texte).
            // On utilise un hash en colonne séparée pour la déduplication.
            $table->string('endpoint_hash', 64)->index();
            $table->unique(['user_id', 'endpoint_hash'], 'push_user_endpoint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
