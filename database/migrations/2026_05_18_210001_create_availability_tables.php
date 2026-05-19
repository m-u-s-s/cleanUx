<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('weekday');  // 0=Sun..6=Sat (ISO date('w') compat)

            $table->time('start_time');
            $table->time('end_time');

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->string('timezone', 64)->default('Europe/Brussels');
            $table->boolean('is_active')->default(true);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['provider_user_id', 'weekday', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
        });

        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->date('date');
            $table->string('exception_type', 24);  // closed | open_override | partial

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('reason', 191)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['provider_user_id', 'date']);
        });

        Schema::create('availability_holds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->unsignedBigInteger('booking_id')->nullable();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            $table->string('reason', 64)->default('booking_flow');
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();

            $table->string('idempotency_key', 128)->nullable()->unique();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['provider_user_id', 'starts_at', 'ends_at']);
            $table->index(['expires_at', 'released_at']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_holds');
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_slots');
    }
};
