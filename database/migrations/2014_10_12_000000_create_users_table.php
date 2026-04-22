<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->enum('role', ['client', 'employe', 'entreprise', 'admin'])->default('client');
            $table->string('tva_number')->nullable();
            $table->integer('duree_creneau')->default(90);

            $table->string('plan_type')->default('standard');
            $table->string('plan_status')->default('inactive');
            $table->timestamp('premium_started_at')->nullable();
            $table->timestamp('premium_renewal_at')->nullable();

            $table->rememberToken();
            $table->foreignId('current_team_id')->nullable();
            $table->string('profile_photo_path', 2048)->nullable();

            $table->timestamps();

            $table->index('role');
            $table->index('plan_type');
            $table->index('plan_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};