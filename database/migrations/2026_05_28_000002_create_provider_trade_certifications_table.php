<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_trade_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->string('certification_type'); // diploma, attestation, experience
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending'); // pending, verified, rejected
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'trade_id', 'certification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_trade_certifications');
    }
};
