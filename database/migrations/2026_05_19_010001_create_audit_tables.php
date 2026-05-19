<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_redaction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->string('pattern', 512);  // regex or key match
            $table->string('match_type', 16)->default('key');  // key | regex | path
            $table->string('replacement', 191)->default('***');
            $table->string('scope_domain', 32)->nullable();  // null = global
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        Schema::create('audit_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->string('domain', 32);
            $table->unsignedInteger('retention_days');
            $table->json('applies_to_severity')->nullable();  // null = all
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['domain', 'is_active']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();

            $table->string('event_type', 128);
            $table->string('domain', 32);
            $table->string('severity', 16)->default('info');
            // info | warning | error | critical

            $table->string('actor_type', 32)->nullable();  // user | system | webhook | job
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 191)->nullable();

            $table->string('subject_type', 96)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 191)->nullable();

            $table->json('context')->nullable();
            $table->json('context_redacted')->nullable();

            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_short', 191)->nullable();
            $table->string('route_name', 191)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('service_zone_id')->nullable();

            $table->string('retention_policy_code', 64)->nullable();
            $table->boolean('is_pinned')->default(false);

            $table->string('idempotency_key', 191)->nullable()->unique();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['domain', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
            $table->index(['actor_type', 'actor_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['request_id']);
            $table->index(['is_pinned', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('audit_retention_policies');
        Schema::dropIfExists('audit_redaction_rules');
    }
};
