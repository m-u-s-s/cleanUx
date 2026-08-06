<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RENDEZ-VOUS DE SIGNATURE SUR PLACE.
 *
 * La signature électronique existait (`contract_signatures`, eIDAS-lite, rattachée à un
 * `contract_document`) et les rendez-vous existaient (`rendez_vous`), mais rien ne reliait les
 * deux. Une société exigeant une signature en présence — courant en B2B pour un contrat-cadre —
 * n'avait aucun moyen de la planifier.
 *
 * POURQUOI UNE TABLE DÉDIÉE. `rendez_vous` décrit une INTERVENTION : prestataire, zone de service,
 * surface, prix estimé, fréquence. Y greffer un rendez-vous commercial mêlerait deux objets métier
 * dans les mêmes requêtes, filtres et statistiques. Une table à part garde chacun lisible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente : rejouable sans erreur sur une base déjà migrée.
        if (Schema::hasTable('signing_appointments')) {
            return;
        }

        Schema::create('signing_appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            // Le document peut être choisi après la prise de rendez-vous.
            $table->foreignId('contract_document_id')->nullable()
                ->constrained('contract_documents')
                ->nullOnDelete();

            // « Sur place » : le local où l'on se rend. Nullable pour une signature au siège du
            // prestataire ou en visioconférence.
            $table->foreignId('organization_site_id')->nullable()
                ->constrained('organization_sites')
                ->nullOnDelete();

            $table->foreignId('signer_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('scheduled_at');
            $table->string('status', 20)->default('scheduled');   // scheduled | completed | cancelled
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            /*
             * NOM D'INDEX EXPLICITE, ET COURT (corrigé le 2026-08-05).
             *
             * Laravel aurait nommé cet index
             * `signing_appointments_organization_account_id_status_scheduled_at_index`, soit 70
             * caractères — au-delà de la limite MySQL de 64. SQLite n'a pas cette contrainte : la
             * migration passait donc en local et sur toute la suite de tests, puis échouait à
             * l'étape « Migrate (MySQL) » de la CI.
             *
             * C'est exactement la classe de défauts que le moteur de test masque ici. Le nom est
             * désormais choisi, pas subi.
             */
            $table->index(
                ['organization_account_id', 'status', 'scheduled_at'],
                'signing_appts_org_status_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_appointments');
    }
};
