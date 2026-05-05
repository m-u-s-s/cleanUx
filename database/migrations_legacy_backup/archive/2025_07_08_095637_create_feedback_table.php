<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rendez_vous_id')->constrained('rendez_vous')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->text('commentaire')->nullable();
            $table->tinyInteger('note')->nullable();
            $table->text('reponse_admin')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('rendez_vous_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};