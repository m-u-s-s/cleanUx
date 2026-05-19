<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot Trade ↔ User — quels métiers un employé/prestataire peut exécuter.
 *
 * Convention : table nommée selon l'ordre alphabétique des modèles
 * (trade_user) pour suivre la convention Laravel BelongsToMany.
 *
 * Absence de ligne = l'employé n'a aucun métier rattaché → en phase de
 * transition, le dispatch fallback sur la liste complète pour ne pas
 * casser les déploiements existants où aucun employé n'a encore été
 * tagué (voir AiDispatchService::rankEmployees, filtre trade-aware).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trade_user')) {
            return;
        }

        Schema::create('trade_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('proficiency', 20)->nullable();   // 'basic'|'standard'|'expert'
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'trade_id']);
            $table->index(['trade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_user');
    }
};
