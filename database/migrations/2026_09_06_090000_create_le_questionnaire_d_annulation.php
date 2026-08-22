<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE QUESTIONNAIRE D'ANNULATION — la pièce qui manquait en amont d'un moteur déjà complet.
 *
 * ── CE QUI EXISTAIT, ET CE QUI NE MARCHAIT PAS ───────────────────────────────────────────────
 *
 * `CancellationEngine::quote()` interroge déjà `cancellation_exempt_reasons` avec un `reason_code`,
 * met les frais à zéro et pose `exempt_applied`. Toute la machinerie d'exemption fonctionne. Mais
 * l'API n'accepte qu'`'reason' => ['nullable','string']` : un CHAMP LIBRE. Or un champ libre ne se
 * compte pas, ne se compare pas, ne déclenche aucun palier — et ne peut donc jamais valoir
 * exemption. Le moteur attendait un code que personne ne lui a jamais donné.
 *
 * ── DEUX TABLES, PARCE QUE CE SONT DEUX NOTIONS ──────────────────────────────────────────────
 *
 * La QUESTION porte le contexte : à qui on la pose, sur quel moteur, à quel moment de la mission.
 * L'OPTION porte la réponse : son code stable, ce qu'on vérifie avant de la proposer, et ce qu'elle
 * déclenche. Les fondre obligerait à répéter le contexte sur chaque ligne, et la première
 * divergence entre deux copies rendrait le questionnaire incohérent.
 *
 * ── POURQUOI RIEN NE SE SUPPRIME VRAIMENT ────────────────────────────────────────────────────
 *
 * Une annulation passée a été décidée avec les questions d'alors — c'est déjà la raison pour
 * laquelle `cancellation_policies` est versionnée. L'administrateur « supprime » : la ligne quitte
 * les écrans et reste lisible pour les dossiers anciens. Et `code` ne se réutilise jamais : c'est
 * lui qui vit dans `booking_cancellations_v2.reason_code`.
 *
 * ── `verification` EST CE QUI SÉPARE UN QUESTIONNAIRE D'UN MENU D'ÉVITEMENT ──────────────────
 *
 * « Le prestataire est en retard » ne se déclare pas : le serveur connaît `planned_start_at` et le
 * statut réel. Une option dont la vérification échoue n'est pas PROPOSÉE — la refuser après coup
 * ferait cocher puis rejeter, ce qui se lit comme une panne.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cancellation_questions')) {
            Schema::create('cancellation_questions', function (Blueprint $table) {
                $table->id();

                $table->string('code', 64)->unique();

                // À qui, sur quel moteur, à quel moment. `null` veut dire « partout ».
                $table->string('audience', 16);
                $table->string('engine', 16)->nullable();
                $table->string('moment', 24)->nullable();

                $table->string('label', 191);
                $table->text('help_text')->nullable();

                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);

                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['audience', 'is_active', 'sort_order'], 'cq_audience_actif_index');
            });
        }

        if (! Schema::hasTable('cancellation_question_options')) {
            Schema::create('cancellation_question_options', function (Blueprint $table) {
                $table->id();

                $table->foreignId('cancellation_question_id')->constrained()->cascadeOnDelete();

                // STABLE ET JAMAIS RÉUTILISÉ : il alimente `booking_cancellations_v2.reason_code`,
                // et c'est sur lui que le moteur retrouve un motif exempté.
                $table->string('code', 64);
                $table->string('label', 191);

                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);

                // `none` | `provider_late` | `gps_movement` | `client_unreachable`
                $table->string('verification', 32)->default('none');

                // `cancel` | `redirect_requote` | `redirect_reinforcement` | `redirect_noshow`
                // | `review`. C'est ce qui fait du questionnaire un AIGUILLAGE : un prestataire qui
                // veut partir parce que le chantier est trop gros ne doit pas annuler.
                $table->string('outcome', 32)->default('cancel');

                $table->unsignedBigInteger('exempt_reason_id')->nullable();

                /*
                 * LE PIÈGE À ENTENTE. « L'autre m'a demandé d'annuler » ne coûte rien à poser et
                 * donne à la plateforme le seul renseignement qu'elle ne peut pas obtenir
                 * autrement : ce qui se dit sur le palier.
                 */
                $table->boolean('collusion_signal')->default(false);

                $table->boolean('requires_text')->default(false);
                $table->boolean('requires_proof')->default(false);

                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['cancellation_question_id', 'code'], 'cqo_question_code_unique');
                $table->index(['is_active', 'sort_order'], 'cqo_actif_ordre_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_question_options');
        Schema::dropIfExists('cancellation_questions');
    }
};
