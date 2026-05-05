<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_verification_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->string('code_type'); // start, end
            $table->string('code_hash');
            $table->dateTime('expires_at')->nullable();

            $table->foreignId('validated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('validated_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('is_consumed')->default(false);

            $table->timestamps();

            $table->index('code_type');
            $table->index('expires_at');
            $table->index('validated_at');
            $table->index(['mission_id', 'code_type']);
            $table->index(['mission_id', 'is_consumed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_verification_codes');
    }
};