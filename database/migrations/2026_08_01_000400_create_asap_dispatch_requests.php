<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La recherche d'un prestataire immédiat — la phase où le client attend devant son écran. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asap_dispatch_requests')) {
            return;
        }

        Schema::create('asap_dispatch_requests', function (Blueprint $table) {
            $table->id();

            // NULLABLES depuis que la recherche appartient à la RÉSERVATION et non au panier.
            $table->foreignId('order_draft_id')->nullable()->constrained('order_drafts')->cascadeOnDelete();
            $table->foreignId('order_draft_item_id')->nullable()->constrained('order_draft_items')->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();

            // CE QUE LA RECHERCHE PILOTE.
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->cascadeOnDelete();

            // searching | accepted | en_route | arrived | in_progress | completed | cancelled | expired
            $table->string('status', 20)->default('searching');

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Le rayon s'élargit par paliers tant que personne ne répond.
            $table->unsignedInteger('radius_m');
            $table->unsignedInteger('notified_count')->default(0);
            $table->unsignedInteger('expansion_count')->default(0);

            // LE RANG DE LA VAGUE ET L'ÉCHÉANCE GLOBALE — ce qui manquait pour raconter l'histoire.
            $table->unsignedInteger('wave')->default(1);
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('broadcast_at')->nullable();

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
            // Le moteur retrouve la recherche par sa réservation à chaque offre, chaque refus et
            // chaque expiration : sans index, c'est un balayage complet par événement.
            $table->index('booking_id');
            $table->index('mission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asap_dispatch_requests');
    }
};
