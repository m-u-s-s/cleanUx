<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masked_call_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();

            $table->foreignId('booking_id')->nullable()
                ->constrained('bookings')->nullOnDelete();
            $table->foreignId('client_user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            // Twilio Proxy session reference
            $table->string('twilio_session_sid', 128)->nullable();
            $table->string('proxy_phone_number', 32)->nullable();   // numéro Twilio Proxy attribué

            // Participants (snapshot pour audit RGPD)
            $table->string('client_phone_masked', 32);     // dernier 4 digits seulement
            $table->string('provider_phone_masked', 32);

            $table->enum('status', ['active', 'closed', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->unsignedInteger('calls_count')->default(0);
            $table->unsignedInteger('sms_count')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['twilio_session_sid']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masked_call_sessions');
    }
};
