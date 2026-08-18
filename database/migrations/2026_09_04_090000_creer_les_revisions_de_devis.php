<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE NOUVEAU DEVIS — quand la demande était sous-dotée dès le départ.
 *
 * ── CE N'EST PAS UN SUPPLÉMENT, ET LA DISTINCTION EST LA PREMIÈRE RÈGLE ──────────────────────
 *
 * `mission_extras` AJOUTE une ligne à un devis juste : le plombier venu pour un siphon découvre les
 * tuyaux, et cela se facture en plus. Ici, le devis lui-même était faux — vingt mètres carrés
 * annoncés, deux cents constatés — et le prix ne s'additionne pas : il se REMPLACE.
 *
 * `MissionExtraService` le réclamait déjà en toutes lettres à sa ligne 42 : « au-delà de 500 €,
 * c'est un nouveau devis qui doit passer par le parcours normal ». Cette table est la porte qui
 * manquait.
 *
 * ── LE CONSTAT EST SÉPARÉ DE L'ENCAISSEMENT ──────────────────────────────────────────────────
 *
 * Même architecture que `mission_time_settlements`, et pour la même raison : le constat doit
 * exister même si le réseau tombe. Une panne de paiement ne doit pas effacer la trace de ce qui a
 * été proposé, ni de ce que le client a répondu.
 *
 * ── POURQUOI LES DEUX TOTAUX SONT GELÉS ICI ──────────────────────────────────────────────────
 *
 * `original_total_cents` est recopié à la proposition. Le relire sur la réservation après coup
 * donnerait le NOUVEAU montant — puisque l'acceptation le réécrit — et le dossier de désaccord
 * perdrait exactement le chiffre qu'il sert à établir.
 *
 * ── LA PREUVE EST OBLIGATOIRE ────────────────────────────────────────────────────────────────
 *
 * `evidence_media_ids` n'est pas nullable. Un prestataire qui affirme un écart sans le montrer
 * demande au client de le croire sur parole, et à l'arbitre de trancher sans matière.
 */
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

            /*
             * TROIS MONTANTS, ET CHACUN RÉPOND À UNE QUESTION DIFFÉRENTE.
             *
             *   `original_total_cents`    ce qui avait été accepté — gelé, voir l'en-tête
             *   `proposed_service_cents`  ce que le prestataire a SAISI : le prix du service, hors
             *                             remises. Il ne saisit jamais le total, sans quoi la
             *                             réduction du client serait silencieusement avalée.
             *   `revised_total_cents`     ce que le client paiera, remises réappliquées
             */
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

            /*
             * « Y a-t-il une révision vivante sur cette mission ? » — la question posée à chaque
             * ouverture de l'écran terrain, et avant chaque proposition.
             */
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
