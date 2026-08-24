<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** POURQUOI CETTE PERSONNE-LÀ, ET PAS UNE AUTRE ? */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internal_assignment_decisions')) {
            Schema::create('internal_assignment_decisions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('mission_id')
                    ->constrained('missions')
                    ->cascadeOnDelete();

                $table->foreignId('provider_organization_id')
                    ->constrained('organization_accounts')
                    ->cascadeOnDelete();

                // Qui a déclenché : un humain (bouton), ou personne (mode continu).
                $table->unsignedBigInteger('triggered_by')->nullable();

                // manual | auto_button | auto_mode
                $table->string('mode', 20);

                // assigned | no_candidate | skipped_locked
                $table->string('status', 20);

                $table->unsignedBigInteger('chosen_user_id')->nullable();
                $table->integer('chosen_score')->nullable();

                // Le détail par candidat : identifiant, score total, et la ventilation par critère.
                $table->json('candidates')->nullable();

                $table->timestamps();

                // Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64 caractères, limite que SQLite ignore — la migration passerait la suite de tests et casserait en production.
                $table->index(['provider_organization_id', 'created_at'], 'iad_org_date_idx');
                $table->index('mission_id', 'iad_mission_idx');
            });
        }

        // LE MODE CONTINU — « toute nouvelle mission de la société est auto-assignée ».
        if (Schema::hasTable('organization_accounts')
            && ! Schema::hasColumn('organization_accounts', 'auto_assign_enabled')) {
            Schema::table('organization_accounts', function (Blueprint $table) {
                $table->boolean('auto_assign_enabled')->default(false);
            });
        }
    }

    /** `down()` volontairement vide : migrations non destructives uniquement. */
    public function down(): void
    {
        // Retirer la table effacerait l'historique des décisions — une perte de données déguisée
        // en retour en arrière.
    }
};
