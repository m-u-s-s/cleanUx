<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('complaint_case_id')
                ->constrained('complaint_cases')->cascadeOnDelete();

            $table->enum('type', [
                'opened',
                'message',
                'admin_message',
                'provider_response',
                'status_changed',
                'assigned',
                'escalated',
                'sla_warning',
                'resolved',
                'closed',
                'reopened',
                'attachment_added',
                'note',
            ]);

            $table->enum('visibility', ['private', 'client', 'provider', 'all'])
                ->default('all');

            $table->foreignId('author_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->enum('author_role', ['client', 'provider', 'admin', 'system'])
                ->default('system');

            $table->text('body')->nullable();
            $table->json('attachments')->nullable();
            $table->json('payload')->nullable();

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();

            $table->timestamps();

            $table->index(['complaint_case_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_events');
    }
};
