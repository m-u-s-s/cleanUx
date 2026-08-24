<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Qui a été prévenu, quand, et ce qu'il en a fait. Le compteur seul ne suffisait pas. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asap_dispatch_notifications')) {
            return;
        }

        Schema::create('asap_dispatch_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asap_dispatch_request_id')
                ->constrained('asap_dispatch_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // La distance au moment de l'envoi. Le prestataire bouge ; ce qu'on lui a annoncé, non.
            $table->unsignedInteger('distance_m')->nullable();

            // Le rayon en vigueur lors de l'envoi : de quoi relire une recherche et comprendre à
            // quel palier chacun a été touché.
            $table->unsignedInteger('radius_m')->nullable();

            $table->timestamp('notified_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason')->nullable();

            // L'envoi a échoué (jeton mort, service indisponible).
            $table->string('delivery_error')->nullable();

            $table->timestamps();

            // Idempotence au niveau de la base : un prestataire n'est prévenu qu'une fois par
            // recherche, quoi que fasse le code au-dessus.
            $table->unique(['asap_dispatch_request_id', 'user_id'], 'asap_notif_unique');
            $table->index(['user_id', 'declined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asap_dispatch_notifications');
    }
};
