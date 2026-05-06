<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5.1 — Logging des appels API LLM.
 *
 * Permet :
 *   - tracking des coûts (input + output tokens)
 *   - mesure de latence
 *   - debug en cas d'erreur (provider, prompt, tools call)
 *   - stats d'usage par utilisateur / par période
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assistant_api_logs')) {
            return;
        }

        Schema::create('assistant_api_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assistant_conversation_id')
                ->nullable()
                ->constrained('assistant_conversations')
                ->cascadeOnDelete();

            $table->string('provider', 32)->default('anthropic');  // anthropic, openai, mock
            $table->string('model', 64)->nullable();

            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();

            // En USD, micro-précision (6 décimales = 0,000001 USD)
            $table->decimal('cost_usd', 10, 6)->nullable();

            // Latence totale en millisecondes
            $table->unsignedInteger('latency_ms')->nullable();

            // success | error | timeout
            $table->string('status', 16)->default('success');
            $table->string('stop_reason', 32)->nullable();        // end_turn, tool_use, max_tokens, etc.
            $table->text('error_message')->nullable();

            // Nombre de tool_use blocs dans la réponse
            $table->unsignedInteger('tool_use_count')->default(0);

            // Liste des noms de tools appelés (pour stats)
            $table->json('tools_used')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_api_logs');
    }
};
