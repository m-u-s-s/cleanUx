<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_reevaluations', function (Blueprint $table) {
            $table->id();
            $table->string('evenement');
            $table->string('entite_type');
            $table->unsignedBigInteger('entite_id');
            $table->timestamp('depose_le');

            $table->unique(['evenement', 'entite_type', 'entite_id'], 'automation_reevaluations_unicite');
        });

        Schema::create('business_alertes', function (Blueprint $table) {
            $table->id();
            $table->string('cle');
            $table->string('niveau');
            $table->text('message');
            $table->json('contexte')->nullable();
            $table->string('entite_type')->nullable();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->timestamp('levee_le');

            $table->index(['cle', 'levee_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_alertes');
        Schema::dropIfExists('automation_reevaluations');
    }
};
