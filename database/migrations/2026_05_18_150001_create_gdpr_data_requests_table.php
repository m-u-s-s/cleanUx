<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdpr_data_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('type', [
                'export',           // Art. 15 / 20 — accès + portabilité
                'erasure',          // Art. 17 — droit à l'oubli
                'restriction',      // Art. 18 — limitation du traitement
                'rectification',    // Art. 16 — rectification (workflow manuel)
                'objection',        // Art. 21 — opposition
            ]);

            $table->enum('status', [
                'pending',
                'processing',
                'awaiting_confirmation',
                'awaiting_grace_period',
                'fulfilled',
                'rejected',
                'expired',
                'cancelled',
            ])->default('pending');

            $table->string('reference', 32)->unique();

            $table->text('reason')->nullable();
            $table->text('admin_response')->nullable();

            $table->string('export_file_path')->nullable();
            $table->unsignedBigInteger('export_file_size')->nullable();
            $table->string('export_format', 16)->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->foreignId('processed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
            $table->index(['status', 'grace_period_ends_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_data_requests');
    }
};
