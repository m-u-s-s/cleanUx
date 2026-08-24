<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LES OBJECTIFS (E13) ET L'ACADÉMIE (E16). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_quests')) {
            Schema::create('provider_quests', function (Blueprint $table) {
                $table->id();

                $table->string('code', 60)->unique();
                $table->string('title', 160);
                $table->text('description')->nullable();

                // `missions_completed` | `ratings_received` | `consecutive_days`
                $table->string('metric', 40);
                $table->unsignedInteger('target');

                // Une fenêtre, ou rien : une quête « 50 missions » sans échéance est un palier de
                // carrière ; « 10 missions ce mois-ci » est un objectif. Les deux ont leur usage.
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();

                // LA RÉCOMPENSE PASSE PAR LES MODULES EXISTANTS.
                $table->string('reward_type', 30)->default('loyalty_points');
                $table->unsignedInteger('reward_value')->default(0);

                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'ends_on'], 'provider_quests_active_end_idx');
            });
        }

        if (! Schema::hasTable('provider_quest_progress')) {
            Schema::create('provider_quest_progress', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('provider_quest_id');
                $table->unsignedBigInteger('user_id');

                $table->unsignedInteger('progress')->default(0);
                $table->timestamp('completed_at')->nullable();
                // La récompense est-elle versée ? Séparé de la complétion : atteindre l'objectif et
                // être payé sont deux événements, et confondre les deux fait payer deux fois.
                $table->timestamp('rewarded_at')->nullable();

                $table->timestamps();

                // Un compteur qui se dédouble donne deux vérités, et c'est toujours la plus
                // flatteuse qu'on affiche.
                $table->unique(['provider_quest_id', 'user_id'], 'quest_progress_unique');
                $table->index(['user_id', 'completed_at'], 'quest_progress_user_done_idx');

                $table->foreign('provider_quest_id')->references('id')->on('provider_quests')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('academy_courses')) {
            Schema::create('academy_courses', function (Blueprint $table) {
                $table->id();

                $table->string('code', 60)->unique();
                $table->string('title', 160);
                $table->text('summary')->nullable();
                $table->longText('content')->nullable();

                // Le métier concerné, s'il y en a un : une formation « sécurité électrique » ne
                // parle pas au nettoyeur, et la lui proposer dilue le catalogue.
                $table->unsignedBigInteger('trade_id')->nullable();

                $table->unsignedSmallInteger('duration_minutes')->default(15);

                // CE QUE LA RÉUSSITE DÉBLOQUE.
                $table->string('badge_code', 60)->nullable();
                $table->unsignedTinyInteger('specialty_bonus')->default(0);

                $table->boolean('is_published')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['is_published', 'trade_id'], 'academy_courses_pub_trade_idx');
            });
        }

        if (! Schema::hasTable('academy_completions')) {
            Schema::create('academy_completions', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('academy_course_id');
                $table->unsignedBigInteger('user_id');

                $table->timestamp('completed_at');
                $table->unsignedTinyInteger('score_percent')->nullable();
                $table->timestamp('badge_granted_at')->nullable();

                $table->timestamps();

                $table->unique(['academy_course_id', 'user_id'], 'academy_completions_unique');

                $table->foreign('academy_course_id')->references('id')->on('academy_courses')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_completions');
        Schema::dropIfExists('academy_courses');
        Schema::dropIfExists('provider_quest_progress');
        Schema::dropIfExists('provider_quests');
    }
};
