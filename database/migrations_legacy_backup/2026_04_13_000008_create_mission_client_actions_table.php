<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_client_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type'); // presence_confirmed, issue_reported
            $table->string('status')->default('submitted');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'action_type']);
            $table->index(['client_user_id', 'action_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_client_actions');
    }
};