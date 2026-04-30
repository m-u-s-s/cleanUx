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


            $table->index('role');
            $table->index('plan_type');
            $table->index('plan_status');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->string('stripe_connect_account_id')->nullable();
            $table->string('stripe_connect_status')->default('not_connected');
            $table->timestamp('stripe_connect_onboarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
