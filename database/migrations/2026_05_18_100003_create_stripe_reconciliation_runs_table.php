<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_reconciliation_runs', function (Blueprint $table) {
            $table->id();

            $table->enum('scope', ['payment_intents', 'transfers', 'payouts', 'all'])
                ->default('all');

            $table->date('period_start');
            $table->date('period_end');

            $table->enum('status', ['running', 'completed', 'failed'])->default('running');

            $table->unsignedInteger('items_checked')->default(0);
            $table->unsignedInteger('mismatches_found')->default(0);
            $table->unsignedInteger('auto_fixed')->default(0);
            $table->unsignedInteger('requires_attention')->default(0);

            $table->json('summary')->nullable();
            $table->json('mismatches')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('triggered_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index(['scope', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_reconciliation_runs');
    }
};
