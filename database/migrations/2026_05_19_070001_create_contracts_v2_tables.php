<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('type', 32);  // tos | sla | client_agreement | provider_agreement | nda | other
            $table->string('role', 24);  // client | provider | enterprise | all
            $table->string('version', 32);  // e.g. "2026-05-v1"
            $table->longText('body_markdown');
            $table->json('body_locale_overrides')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('supersedes_template_id')->nullable()
                ->constrained('contract_templates')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'role', 'is_active']);
            $table->unique(['code', 'version'], 'contract_templates_code_version_unique');
        });

        Schema::create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('contract_templates')->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->longText('body_rendered_html');
            $table->string('pdf_path', 500)->nullable();
            $table->string('status', 24)->default('draft');
            // draft | pending_signature | signed | cancelled | expired
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('contract_documents')->cascadeOnDelete();
            $table->foreignId('signer_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('signer_name', 191);
            $table->char('signer_email_hash', 64)->nullable();
            $table->longText('signature_data');
            $table->char('signature_hash', 64);
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_short', 191)->nullable();
            $table->string('terms_version', 32);
            $table->string('country_code', 8)->nullable();
            $table->json('geolocation')->nullable();
            $table->timestamp('signed_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_invalidated')->default(false);
            $table->foreignId('invalidated_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('invalidated_at')->nullable();
            $table->text('invalidation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'is_invalidated']);
            $table->index(['signer_user_id', 'signed_at']);
        });

        Schema::create('contract_signature_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_id')->nullable()
                ->constrained('contract_signatures')->cascadeOnDelete();
            $table->foreignId('document_id')
                ->constrained('contract_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('event', 32);  // view | sent | opened | signed | invalidated
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_short', 191)->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'occurred_at']);
            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signature_audits');
        Schema::dropIfExists('contract_signatures');
        Schema::dropIfExists('contract_documents');
        Schema::dropIfExists('contract_templates');
    }
};
