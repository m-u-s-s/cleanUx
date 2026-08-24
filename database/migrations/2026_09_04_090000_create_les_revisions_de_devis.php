<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LE NOUVEAU DEVIS — quand la demande était sous-dotée dès le départ. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mission_quote_revisions')) {
            return;
        }

        Schema::create('mission_quote_revisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by_user_id')->constrained('users')->restrictOnDelete();

            // TROIS MONTANTS, ET CHACUN RÉPOND À UNE QUESTION DIFFÉRENTE.
            $table->unsignedInteger('original_total_cents');
            $table->unsignedInteger('proposed_service_cents');
            $table->unsignedInteger('revised_total_cents');
            $table->json('discount_breakdown')->nullable();
            $table->char('currency', 3);

            $table->string('reason_code', 64);
            $table->text('reason_text');
            $table->json('evidence_media_ids');

            $table->string('status', 24)->default('proposed');
            $table->timestamp('window_closes_at');
            $table->timestamp('responded_at')->nullable();
            $table->string('client_decision', 16)->nullable();

            // Le COMPLÉMENT, et non un remplacement : l'empreinte d'origine n'est jamais annulée.
            $table->string('top_up_payment_intent_id', 128)->nullable();
            $table->timestamp('charged_at')->nullable();
            $table->string('last_error', 1000)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            // « Y a-t-il une révision vivante sur cette mission ?
            $table->index(['mission_id', 'status'], 'mqr_mission_statut_index');
            // « Ce prestataire révise-t-il plus que ses pairs ? » — la requête de l'arbitre.
            $table->index(['proposed_by_user_id', 'status'], 'mqr_prestataire_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_quote_revisions');
    }
};
