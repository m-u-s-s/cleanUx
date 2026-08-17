<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE RÈGLEMENT DU TEMPS SUPPLÉMENTAIRE — une ligne par mission, qui ATTESTE puis encaisse.
 *
 * POURQUOI UNE TABLE ET NON UNE COLONNE. Un montant seul ne se défend pas. Le jour où un client
 * conteste vingt-deux euros, il faut pouvoir montrer : voilà ce qui avait été acheté, voilà ce qui
 * a été presté, voilà le tarif horaire appliqué, voilà la franchise déduite et le plafond atteint.
 * Ces chiffres se déduisent aujourd'hui de quatre tables, et ils se déduiront différemment dans un
 * an, quand la configuration aura changé — un litige porte sur le passé, pas sur les réglages du
 * jour. On fige donc le calcul au moment où il est fait.
 *
 * POURQUOI CE N'EST PAS UN `MissionExtra`. Un supplément est PROPOSÉ par le prestataire et ACCEPTÉ
 * par le client ; il a un demandeur, une réponse, un refus possible. Un règlement de temps n'est ni
 * proposé ni accepté : c'est la conséquence automatique d'une règle annoncée d'avance. Les loger
 * ensemble obligerait tout lecteur de suppléments à distinguer deux natures, et ferait apparaître
 * dans la liste « ce que vous avez accepté » une ligne que personne n'a acceptée.
 *
 * LE STATUT SUIT CELUI DES SUPPLÉMENTS, à dessein : `pending` tant que rien n'est encaissé,
 * `charged` uniquement quand Stripe l'a confirmé, `failed` avec son motif. `not_required` est le
 * cas courant — la mission a tenu dans son temps, il n'y a rien à réclamer, et l'écrire vaut mieux
 * que l'absence de ligne, qui ne distingue pas « rien à payer » de « jamais calculé ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mission_time_settlements')) {
            return;
        }

        Schema::create('mission_time_settlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            // ── Ce qui a été constaté, figé au moment du calcul ──────────────────────────────
            $table->unsignedInteger('authorized_minutes');
            $table->unsignedInteger('purchased_minutes');
            $table->unsignedInteger('elapsed_minutes');
            $table->unsignedInteger('extension_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('grace_minutes')->default(0);
            $table->boolean('capped')->default(false);

            // ── L'argent ────────────────────────────────────────────────────────────────────
            $table->unsignedInteger('effective_hourly_rate_cents')->nullable();
            $table->decimal('overtime_multiplier', 5, 2)->default(1.00);
            $table->unsignedInteger('authorized_amount_cents')->default(0);
            $table->unsignedInteger('extension_amount_cents')->default(0);
            $table->unsignedInteger('overtime_amount_cents')->default(0);
            /*
             * CE QUI RESTE À ENCAISSER, et rien d'autre. L'autorisation initiale est capturée par
             * son propre chemin ; confondre les deux montants ferait débiter deux fois la partie
             * déjà payée.
             */
            $table->unsignedInteger('amount_due_cents')->default(0);
            $table->string('currency', 3)->default('EUR');

            // ── L'encaissement ──────────────────────────────────────────────────────────────
            $table->string('status', 20)->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('charged_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            /*
             * UNE SEULE LIGNE PAR MISSION. Sans cette contrainte, deux clôtures concurrentes — la
             * reprise planifiée et une action manuelle — produiraient deux règlements pour le même
             * dépassement, et le client serait débité deux fois.
             *
             * Le nom est posé à la main : `mission_time_settlements_mission_id_unique` tient dans
             * les 64 caractères de MySQL, mais le laisser deviner sur une table au nom déjà long
             * est le genre de pari qui casse la migration en production sans que SQLite le voie.
             */
            $table->unique('mission_id', 'mts_mission_unique');
            $table->index(['status', 'last_attempt_at'], 'mts_reprise_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_time_settlements');
    }
};
