<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE CONTRÔLE FACIAL DU PRESTATAIRE — trois tables, pas quatre.
 *
 * `provider_face_profiles` porte LE VISAGE DE RÉFÉRENCE et l'état courant (échéance, blocage) ;
 * `provider_face_checks` porte CHAQUE contrôle ; `provider_face_incidents` porte les signalements
 * de panne et les alertes de fraude.
 *
 * Il n'y a délibérément PAS de quatrième table pour le blocage : l'état courant vit sur le profil,
 * l'historique se lit dans les contrôles et les incidents. Une table de plus serait une seconde
 * source de vérité sur la même question — « ce prestataire peut-il travailler aujourd'hui ? » —
 * et c'est précisément le défaut dominant de ce dépôt.
 *
 * Le visage est une donnée biométrique (RGPD art. 9) : les colonnes qui portent du contenu brut
 * (`metadata`, `raw`) sont en `longText` et NON en `json`, parce que MySQL refuse un chiffré dans
 * une colonne JSON (SQLSTATE 3140) — la migration KYC l'a déjà appris à ses dépens.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_face_profiles')) {
            Schema::create('provider_face_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

                // pending | enrolled | rejected | revoked
                $table->string('status', 16)->default('pending');

                // Le visage de référence. Chemin sur le disque PRIVÉ, contenu chiffré au repos.
                $table->string('reference_path', 500)->nullable();
                $table->char('reference_hash', 64)->nullable();
                $table->string('reference_mime', 64)->nullable();
                $table->string('external_face_id', 128)->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->char('captured_ip_hash', 64)->nullable();
                $table->string('captured_device_name', 120)->nullable();

                // RGPD art. 9 : le consentement explicite est une condition de licéité, pas un confort.
                $table->timestamp('consent_given_at')->nullable();
                $table->string('consent_version', 16)->nullable();
                $table->timestamp('consent_withdrawn_at')->nullable();

                // Appariement avec la pièce d'identité déjà déposée.
                $table->foreignId('id_document_id')->nullable()
                    ->constrained('provider_onboarding_documents')->nullOnDelete();
                // pending | match | mismatch | inconclusive | manual_override
                $table->string('id_match_status', 20)->default('pending');
                $table->decimal('id_match_score', 5, 2)->nullable();
                $table->timestamp('id_match_checked_at')->nullable();
                $table->string('id_match_provider', 32)->nullable();

                /*
                 * L'ÉCHÉANCE EST TIRÉE AU SORT ET NE SORT JAMAIS DE LA BASE.
                 *
                 * Elle est calculée côté serveur au moment du contrôle PRÉCÉDENT. Aucune réponse
                 * d'API ne la renvoie : un prestataire qui connaîtrait sa prochaine échéance
                 * saurait exactement quand se présenter en personne, et le contrôle ne prouverait
                 * plus rien le reste du temps.
                 */
                $table->timestamp('next_check_due_at')->nullable();
                $table->timestamp('last_check_at')->nullable();
                $table->unsignedSmallInteger('consecutive_failures')->default(0);

                // Blocage dur : la levée est un geste d'administrateur, jamais automatique.
                $table->timestamp('blocked_at')->nullable();
                $table->string('block_reason', 64)->nullable();
                $table->timestamp('unblocked_at')->nullable();
                $table->foreignId('unblocked_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();

                $table->foreignId('reviewed_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();

                $table->longText('metadata')->nullable();
                $table->timestamps();

                $table->index('status', 'pfp_status_idx');
                $table->index('next_check_due_at', 'pfp_due_idx');
                $table->index('blocked_at', 'pfp_blocked_idx');
            });
        }

        if (! Schema::hasTable('provider_face_checks')) {
            Schema::create('provider_face_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('provider_face_profile_id')->constrained()->cascadeOnDelete();

                // enrollment | interval | risk_device | risk_failures | risk_abandons | admin_forced
                $table->string('triggered_by', 24);
                // pending | passed | failed | abandoned | expired | error
                $table->string('status', 16)->default('pending');
                // auto | manual  — renseigné à la décision
                $table->string('decision_source', 8)->nullable();

                $table->decimal('score', 5, 2)->nullable();
                // pass | fail | unknown
                $table->string('liveness_result', 12)->nullable();
                $table->string('match_provider', 32)->nullable();
                $table->string('external_check_id', 128)->nullable();

                // Le selfie du contrôle est éphémère : purgé par la rétention, seul le verdict reste.
                $table->string('selfie_path', 500)->nullable();
                $table->timestamp('selfie_purged_at')->nullable();

                $table->unsignedTinyInteger('attempt_number')->default(1);
                $table->timestamp('requested_at');
                $table->timestamp('answered_at')->nullable();
                $table->timestamp('expires_at')->nullable();

                $table->char('ip_hash', 64)->nullable();
                $table->string('device_name', 120)->nullable();
                $table->string('app_version', 32)->nullable();
                $table->string('failure_reason', 64)->nullable();
                $table->longText('raw')->nullable();

                $table->foreignId('reviewed_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();

                $table->timestamps();

                $table->index(['user_id', 'status'], 'pfc_user_status_idx');
                $table->index(['status', 'requested_at'], 'pfc_status_requested_idx');
                $table->index('provider_face_profile_id', 'pfc_profile_idx');
            });
        }

        if (! Schema::hasTable('provider_face_incidents')) {
            Schema::create('provider_face_incidents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('provider_face_check_id')->nullable()
                    ->constrained()->nullOnDelete();

                // provider_report | repeated_abandon | repeated_failure | liveness_fail | id_mismatch
                $table->string('type', 24);
                // info | warning | critical
                $table->string('severity', 12)->default('info');
                // open | acknowledged | resolved | dismissed
                $table->string('status', 16)->default('open');

                $table->text('message')->nullable();
                $table->json('diagnostics')->nullable();
                $table->unsignedSmallInteger('occurrence_count')->default(1);

                $table->foreignId('acknowledged_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('acknowledged_at')->nullable();
                $table->foreignId('resolved_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->string('resolution', 32)->nullable();
                $table->text('resolution_note')->nullable();

                $table->timestamps();

                $table->index(['status', 'severity'], 'pfi_status_severity_idx');
                $table->index(['user_id', 'type'], 'pfi_user_type_idx');
            });
        }

        /*
         * L'ACTIVATION PAR MÉTIER, SUR LE PATRON EXACT DES AUTRES EXIGENCES.
         *
         * `requires_certification`, `requires_insurance_proof` et `requires_site_visit` vivent déjà
         * sur `trades` : une quatrième colonne du même genre se lit sans explication, là où un
         * registre séparé aurait demandé qu'on aille le chercher. `false` par défaut — aucun métier
         * ne change de comportement tant qu'un administrateur ne l'a pas coché.
         */
        if (Schema::hasTable('trades') && ! Schema::hasColumn('trades', 'requires_face_check')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->boolean('requires_face_check')->default(false)->after('requires_insurance_proof');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_face_incidents');
        Schema::dropIfExists('provider_face_checks');
        Schema::dropIfExists('provider_face_profiles');

        if (Schema::hasTable('trades') && Schema::hasColumn('trades', 'requires_face_check')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->dropColumn('requires_face_check');
            });
        }
    }
};
