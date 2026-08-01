<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La recherche d'un prestataire immédiat — la phase où le client attend devant son écran.
 *
 * Elle précède la mission : à cet instant il n'y a ni réservation, ni prestataire, ni rien que le
 * dispatch existant sache porter. `MissionDispatchService` reprend la main dès l'acceptation ; ce
 * qu'on décrit ici est ce qui se passe AVANT, et que rien ne modélisait.
 *
 * Les horodatages sont un par état plutôt qu'un journal séparé : le client voit sa demande
 * progresser en direct, et l'écran doit pouvoir dire « accepté il y a 40 s, en route depuis 2 min »
 * sans une jointure de plus à chaque rafraîchissement.
 *
 * `free_cancellation_until` est écrit à l'ACCEPTATION, pas calculé à la lecture. La fenêtre
 * annoncée au client est ainsi celle qui s'applique, même si la configuration change entre-temps —
 * découvrir des frais qu'on n'avait pas annoncés est ce qui fait perdre un client pour de bon.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asap_dispatch_requests')) {
            return;
        }

        Schema::create('asap_dispatch_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_draft_id')->constrained('order_drafts')->cascadeOnDelete();
            $table->foreignId('order_draft_item_id')->constrained('order_draft_items')->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();

            // searching | accepted | en_route | arrived | in_progress | completed | cancelled | expired
            $table->string('status', 20)->default('searching');

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            /*
             * Le rayon s'élargit par paliers tant que personne ne répond. On garde le rayon
             * COURANT et le nombre de prestataires prévenus : l'écran d'attente les affiche, et
             * c'est ce qui distingue une recherche vivante d'un sablier qui tourne.
             */
            $table->unsignedInteger('radius_m');
            $table->unsignedInteger('notified_count')->default(0);
            $table->unsignedInteger('expansion_count')->default(0);

            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('searching_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Figée à l'acceptation : le client doit pouvoir compter dessus.
            $table->timestamp('free_cancellation_until')->nullable();

            $table->string('cancelled_by', 20)->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedInteger('cancellation_fee_cents')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'searching_at']);
            $table->index('order_draft_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asap_dispatch_requests');
    }
};
