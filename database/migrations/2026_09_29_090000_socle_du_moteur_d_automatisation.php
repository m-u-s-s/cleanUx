<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('entite');
            $table->string('declencheur')->default('cadence');
            $table->string('cadence')->nullable();
            $table->json('conditions');
            $table->json('actions');
            $table->string('politique_reprise')->default('une_fois');
            $table->string('etat')->default('brouillon');
            $table->unsignedInteger('quota_par_passage')->default(50);
            $table->unsignedInteger('plafond_journalier')->default(500);
            $table->unsignedTinyInteger('plafonds_consecutifs')->default(0);
            $table->unsignedTinyInteger('echecs_consecutifs')->default(0);
            $table->timestamp('dernier_passage_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['etat', 'declencheur']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('mode');
            $table->timestamp('demarre_le');
            $table->timestamp('termine_le')->nullable();
            $table->unsignedInteger('entites_vues')->default(0);
            $table->unsignedInteger('actions_posees')->default(0);
            $table->string('statut')->default('ok');
            $table->text('message')->nullable();

            $table->index(['automation_rule_id', 'demarre_le']);
        });

        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entite_type');
            $table->unsignedBigInteger('entite_id');
            $table->string('action_cle');
            $table->json('parametres')->nullable();
            $table->string('mode');
            $table->string('resultat');
            $table->foreignId('decide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->text('motif')->nullable();
            $table->unsignedInteger('etape')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('pose_le');

            $table->index(
                ['automation_rule_id', 'entite_type', 'entite_id', 'pose_le'],
                'automation_actions_registre_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
    }
};
