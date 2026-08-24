<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Niveau 3 du catalogue — le QUESTIONNAIRE — entièrement en données. */
return new class extends Migration
{
    public function up(): void
    {
        // Découpage optionnel en étapes : au-delà de 7 questions, on scinde plutôt que d'empiler.
        if (! Schema::hasTable('question_steps')) {
            Schema::create('question_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['trade_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('questions')) {
            Schema::create('questions', function (Blueprint $table) {
                $table->id();

                // `trade_id` nullable = question GLOBALE, réutilisable entre métiers (étage, accès, fourniture du matériel).
                $table->foreignId('trade_id')->nullable()->constrained('trades')->cascadeOnDelete();
                $table->foreignId('step_id')->nullable()->constrained('question_steps')->nullOnDelete();

                // `code` est la clé stable, et c'est LUI qui vit dans les réponses — jamais l'id.
                $table->string('code', 80);

                $table->string('label');
                $table->string('help_text')->nullable();
                $table->string('placeholder')->nullable();

                // single_choice, multi_choice, boolean, counter, number, surface, range,
                // date, time, text, textarea, photo, address.
                $table->string('type', 30);

                $table->boolean('is_required')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('default_value')->nullable();

                // { min, max, step, unit, regex } — les bornes sont des données, pas des règles PHP.
                $table->json('validation')->nullable();

                // { mode: none|add|multiply|per_unit, coefficient } — voir le moteur tarifaire.
                $table->json('pricing')->nullable();
                $table->integer('duration_impact_min')->default(0);

                // { layout: cards|chips|dropdown|slider|counter, columns } — la même question se
                // présente autrement selon qu'elle a 2 ou 12 options.
                $table->json('display')->nullable();

                // La porte de sortie.
                $table->boolean('allows_unknown')->default(true);

                // Question ESSENTIELLE : seules celles-ci sont posées en mode immédiat.
                $table->boolean('is_essential')->default(false);

                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                // Un code unique par métier.
                $table->unique(['trade_id', 'code'], 'questions_trade_code_unique');
                $table->index(['trade_id', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('question_options')) {
            Schema::create('question_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();

                $table->string('label');
                $table->string('description')->nullable();
                $table->string('icon', 80)->nullable();
                $table->string('value', 120);

                // Impacts en CENTIMES et en entiers : un prix ne se calcule jamais en flottants.
                $table->integer('price_modifier_cents')->default(0);
                $table->decimal('price_multiplier', 6, 3)->nullable();
                $table->integer('duration_modifier_min')->default(0);

                $table->unsignedInteger('sort_order')->default(0);

                // Le défaut intelligent : la réponse la plus fréquente est pré-sélectionnée, et
                // c'est l'administrateur qui décide laquelle. Le client valide plus qu'il ne remplit.
                $table->boolean('is_default')->default(false);

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['question_id', 'value'], 'question_option_value_unique');
                $table->index(['question_id', 'is_active', 'sort_order']);
            });
        }

        // « Type de pistolet » ne s'affiche que si « Application au pistolet » vaut oui.
        if (! Schema::hasTable('question_conditions')) {
            Schema::create('question_conditions', function (Blueprint $table) {
                $table->id();

                // La question qui s'affiche, se cache ou devient obligatoire.
                $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();

                // Celle dont la réponse décide.
                $table->foreignId('depends_on_question_id')->constrained('questions')->cascadeOnDelete();

                // equals, not_equals, in, gt, lt, is_answered
                $table->string('operator', 20);
                $table->json('value')->nullable();

                // show | hide | require
                $table->string('action', 10)->default('show');

                $table->timestamps();

                $table->index('question_id');
                $table->index('depends_on_question_id');
            });
        }

        // Versionnement du questionnaire d'un métier. Chaque publication fige le schéma complet.
        if (! Schema::hasTable('trade_form_revisions')) {
            Schema::create('trade_form_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->json('schema');
                $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['trade_id', 'version'], 'trade_revision_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_form_revisions');
        Schema::dropIfExists('question_conditions');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_steps');
    }
};
