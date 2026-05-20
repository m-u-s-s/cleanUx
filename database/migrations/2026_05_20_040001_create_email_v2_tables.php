<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('provider', 32);   // mock | mailgun | ses | sendgrid | smtp
            $table->string('provider_message_id', 191)->nullable();
            $table->string('to_email', 191);
            $table->string('to_name', 191)->nullable();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_email', 191);
            $table->string('from_name', 191)->nullable();
            $table->string('reply_to', 191)->nullable();
            $table->string('subject', 500);
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('attachments')->nullable();
            $table->json('headers')->nullable();
            $table->string('category', 32)->default('transactional');
            // transactional | marketing | notification | system
            $table->string('template_code', 64)->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('status', 16)->default('pending');
            // pending | queued | sent | failed | bounced | complained | delivered | opened | clicked
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['to_user_id']);
            $table->index(['category']);
            $table->index(['provider_message_id']);
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('category', 32)->default('transactional');
            $table->string('subject_pattern', 500);
            $table->longText('body_html_pattern');
            $table->longText('body_text_pattern')->nullable();
            $table->json('locale_overrides')->nullable();
            $table->json('required_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('email_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_event_id', 191)->unique();
            $table->string('provider_message_id', 191)->nullable();
            $table->foreignId('email_message_id')->nullable()->constrained('email_messages')->nullOnDelete();
            $table->string('event_type', 32);
            // delivered | opened | clicked | bounced | complained | failed | unsubscribed
            $table->timestamp('occurred_at');
            $table->json('payload');
            $table->timestamps();

            $table->index(['email_message_id', 'event_type']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_webhook_events');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_messages');
    }
};
