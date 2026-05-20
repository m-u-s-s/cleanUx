<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_badges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('description', 500)->nullable();
            $table->string('icon', 32)->nullable();
            $table->string('tier', 16)->default('bronze');   // bronze/silver/gold/platinum
            $table->string('criterion_type', 32);             // missions_count|rating_avg|tips_received|tenure_days|loyalty_points|streak_5stars
            $table->unsignedInteger('threshold');             // valeur seuil
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['criterion_type', 'is_active']);
        });

        Schema::create('provider_badge_awards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->foreignId('badge_id')
                ->constrained('provider_badges')->cascadeOnDelete();

            $table->unsignedInteger('value_at_award')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamps();

            $table->unique(['provider_user_id', 'badge_id']);
            $table->index(['provider_user_id', 'awarded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_badge_awards');
        Schema::dropIfExists('provider_badges');
    }
};
