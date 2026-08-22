<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La commande en cours de construction, avant qu'elle n'existe comme réservation.
 *
 * DÉCISION DE NOMMAGE, prise faute de pouvoir suivre la spécification à la lettre. Celle-ci
 * demande `missions`, `mission_items`, `mission_answers` et `mission_media` : les QUATRE noms sont
 * déjà pris sur ce projet, par un tout autre concept. Ici, `missions` est le dossier d'EXÉCUTION
 * d'une intervention — statut terrain, codes de démarrage et de clôture, position vérifiée,
 * encaissement — rattaché à une réservation existante. Ce n'est pas une commande.
 *
 * Mais le conflit de noms n'est pas la vraie raison de ces tables. La loi 1 l'est : le client voit
 * un prix AVANT qu'on lui demande un compte. Il n'y a donc, à ce moment-là, ni client_id, ni
 * réservation, ni mission — rien à quoi rattacher ses réponses. Un panier anonyme est structurellement
 * obligatoire, et aucune table existante ne peut jouer ce rôle : `bookings` exige un client,
 * `multi_trade_bundles` aussi.
 *
 * Le brouillon se matérialise à la confirmation : une réservation pour un métier seul, un
 * `multi_trade_bundle` pour plusieurs. Les brouillons ne sont pas purgés — ils portent les réponses
 * horodatées qui rendent un devis explicable ligne par ligne, et opposable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260803000100AddRecoveryKeyToOrderDrafts();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_draft_media');
        Schema::dropIfExists('order_draft_answers');
        Schema::dropIfExists('order_draft_items');
        Schema::dropIfExists('order_drafts');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
    {
        if (! Schema::hasTable('order_drafts')) {
            Schema::create('order_drafts', function (Blueprint $table) {
                $table->id();

                // Référence lisible, communicable au téléphone : CLX-8F3K2.
                $table->string('reference', 20)->unique();

                /*
                 * Nullable : c'est tout l'objet de la loi 1. Le jeton de session est ce qui permet
                 * de retrouver son panier trois heures plus tard, dans un autre onglet, sans avoir
                 * jamais créé de compte.
                 */
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('session_token', 64)->nullable()->index();

                // scheduled | asap | bundle
                $table->string('mode', 20)->default('scheduled');

                // draft | submitted | converted | abandoned
                $table->string('status', 20)->default('draft');

                /*
                 * Adresse au niveau de la COMMANDE, jamais de la ligne : en multi-services, elle
                 * n'est demandée qu'une fois. Les contraintes d'accès suivent la même règle.
                 */
                $table->string('address')->nullable();
                $table->string('address_details')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();

                /*
                 * LA GÉOGRAPHIE, RÉSOLUE PENDANT LE PARCOURS — pas après.
                 *
                 * Le panier portait une adresse et des coordonnées, mais ni code postal ni zone.
                 * Le prix par zone existait en base et n'atteignait donc jamais le calcul
                 * (`PricingEngine` recevait un `zone_multiplier` que personne ne fournissait, et
                 * il valait 1,0 partout), et le dispatch héritait d'une réservation sans zone :
                 * il fallait la redeviner au moment d'envoyer quelqu'un.
                 *
                 * Résolus ICI, ils sont connus AVANT la confirmation — ce qui permet de refuser
                 * une commande hors couverture au lieu de la confirmer puis de ne trouver
                 * personne.
                 */
                $table->string('postal_code', 12)->nullable();
                $table->foreignId('service_zone_id')->nullable()
                    ->constrained('service_zones')->nullOnDelete();

                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('asap_requested_at')->nullable();

                /*
                 * Toujours une FOURCHETTE, jamais un prix ferme, tant qu'un professionnel n'a pas
                 * confirmé. Chaque « je ne sais pas » l'élargit — c'est ce qui rend la porte de
                 * sortie de la loi 6 tenable sans mentir sur le prix.
                 */
                $table->unsignedInteger('estimate_min_cents')->nullable();
                $table->unsignedInteger('estimate_max_cents')->nullable();
                $table->unsignedInteger('total_cents')->nullable();
                $table->string('currency', 3)->default('EUR');

                $table->text('client_notes')->nullable();
                $table->string('source', 40)->nullable();

                // Ce que le brouillon est devenu une fois confirmé. Les deux restent nuls tant que
                // la commande n'a pas été payée ou validée.
                $table->foreignId('converted_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('converted_bundle_id')->nullable()->constrained('multi_trade_bundles')->nullOnDelete();
                $table->timestamp('converted_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'mode']);
                $table->index(['client_id', 'status']);
                $table->index('service_zone_id');
            });
        }

        // Une ligne par métier : c'est ce qui rend le mode multi-services possible sans cas particulier.
        if (! Schema::hasTable('order_draft_items')) {
            Schema::create('order_draft_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_draft_id')->constrained('order_drafts')->cascadeOnDelete();
                $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();

                // Révision du questionnaire employée : sans elle, un devis ancien n'est plus rejouable.
                $table->foreignId('trade_form_revision_id')->nullable()
                    ->constrained('trade_form_revisions')->nullOnDelete();

                $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();

                // Ordre d'intervention, et dépendance : le carreleur ne passe qu'après le plombier.
                $table->unsignedInteger('sequence')->default(0);
                $table->foreignId('depends_on_item_id')->nullable()
                    ->constrained('order_draft_items')->nullOnDelete();

                // Tampon de séchage entre deux interventions dépendantes.
                $table->unsignedInteger('sequence_gap_min')->default(0);

                $table->string('status', 20)->default('draft');
                $table->timestamp('scheduled_at')->nullable();

                $table->unsignedInteger('estimate_min_cents')->nullable();
                $table->unsignedInteger('estimate_max_cents')->nullable();
                $table->unsignedInteger('duration_min')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['order_draft_id', 'sequence']);
            });
        }

        if (! Schema::hasTable('order_draft_answers')) {
            Schema::create('order_draft_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_draft_item_id')->constrained('order_draft_items')->cascadeOnDelete();

                /*
                 * La question peut disparaître ; la réponse, non.
                 *
                 * `question_id` est nullable et se détache si la question est archivée. Ce sont les
                 * trois colonnes suivantes qui portent la vérité : le CODE stable, et un INSTANTANÉ
                 * du libellé de la question comme de la réponse, tels qu'ils étaient affichés au
                 * client au moment où il a répondu.
                 *
                 * Sans cet instantané, renommer une question six mois plus tard réécrirait
                 * rétroactivement des devis et des factures déjà émis — et rendrait indéfendable
                 * tout litige portant dessus.
                 */
                $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
                $table->string('question_code', 80);
                $table->string('question_label_snapshot');

                $table->json('answer_value')->nullable();
                $table->string('answer_label_snapshot')->nullable();

                // Ce que CETTE réponse a coûté ou fait économiser : le devis devient explicable
                // ligne par ligne, ce qui supprime la majorité des litiges avant qu'ils n'existent.
                $table->integer('price_impact_cents')->default(0);
                $table->integer('duration_impact_min')->default(0);

                /*
                 * La porte de sortie a été empruntée. On la distingue d'une absence de réponse :
                 * « je ne sais pas » est une réponse, qui élargit la fourchette au lieu de bloquer.
                 */
                $table->boolean('is_unknown')->default(false);

                $table->timestamps();

                $table->unique(['order_draft_item_id', 'question_code'], 'draft_answer_unique');
            });
        }

        // Une photo vaut dix questions. Table distincte de `mission_media`, qui appartient au
        // dossier d'exécution et non à la commande.
        if (! Schema::hasTable('order_draft_media')) {
            Schema::create('order_draft_media', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_draft_item_id')->constrained('order_draft_items')->cascadeOnDelete();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('path');
                $table->string('caption')->nullable();
                $table->unsignedInteger('size_bytes')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->timestamps();

                $table->index('order_draft_item_id');
            });
        }
    }

    /** Fusionne depuis 2026_08_03_000100_add_recovery_key_to_order_drafts */
    private function fusion20260803000100AddRecoveryKeyToOrderDrafts(): void
    {
        Schema::table('order_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('order_drafts', 'recovery_key_hash')) {
                $table->string('recovery_key_hash', 64)->nullable();
                $table->timestamp('recovery_key_expires_at')->nullable();

                $table->index('recovery_key_hash');
            }
        });
    }
};
