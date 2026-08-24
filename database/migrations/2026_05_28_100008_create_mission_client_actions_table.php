<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schema-drift repair: create the `mission_client_actions` table. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_client_actions')) {
            Schema::create('mission_client_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_id')->nullable()->index();
                $table->unsignedBigInteger('client_user_id')->nullable()->index();
                $table->string('action_type')->nullable()->index();
                $table->string('status')->nullable()->index();
                $table->text('message')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_client_actions');
    }
};
