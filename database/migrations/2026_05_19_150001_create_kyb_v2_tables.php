<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('legal_name', 255);
            $table->string('trade_name', 255)->nullable();
            $table->string('country_code', 2);
            $table->string('identifier_type', 24);  // siret | siren | kbo | companies_house | kvk | other
            $table->string('identifier_value', 64);
            $table->string('vat_id', 32)->nullable();
            $table->string('legal_form', 64)->nullable();
            $table->json('registered_address')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('status', 16)->default('pending');
            // pending | verified | rejected | suspended | needs_review
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->string('risk_level', 16)->nullable();   // low | medium | high | critical
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_email', 191)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'identifier_type', 'identifier_value'], 'business_entities_country_id_unique');
            $table->index(['status', 'risk_level']);
            $table->index(['owner_user_id']);
            $table->index(['vat_id']);
        });

        Schema::create('business_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('business_entities')->cascadeOnDelete();
            $table->string('document_type', 32);
            // kbis | certificate_incorp | articles | bank_statement | id_card_director | tax_certificate | proof_address | other
            $table->string('file_path', 500);
            $table->string('mime_type', 96);
            $table->unsignedInteger('size_bytes');
            $table->timestamp('uploaded_at');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            // pending | approved | rejected | expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'status']);
            $table->index(['document_type', 'status']);
        });

        Schema::create('business_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('business_entities')->cascadeOnDelete();
            $table->string('provider', 32);   // mock | insee | companies_house | vies | kvk
            $table->string('check_type', 32);  // identity | legal_form | tax_validity | active_status | beneficial_owners
            $table->string('status', 16)->default('pending');
            // pending | success | failed | error
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('matched_value', 191)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'check_type']);
            $table->index(['provider', 'status']);
        });

        Schema::create('business_sanctions_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('business_entities')->cascadeOnDelete();
            $table->string('list_name', 32);   // eu | us_ofac | un | interpol | uk_hmt | combined
            $table->string('status', 16)->default('pending');
            // pending | clear | match | review_required | error
            $table->unsignedSmallInteger('match_count')->default(0);
            $table->json('match_payload')->nullable();
            $table->string('provider', 32)->default('mock');
            $table->timestamp('checked_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'list_name']);
            $table->index(['status']);
        });

        Schema::create('business_beneficial_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('business_entities')->cascadeOnDelete();
            $table->string('full_name', 191);
            $table->date('date_of_birth')->nullable();
            $table->string('country_of_residence', 2)->nullable();
            $table->string('nationality', 2)->nullable();
            $table->decimal('ownership_percent', 5, 2)->nullable();
            $table->boolean('is_director')->default(false);
            $table->boolean('is_pep')->default(false);   // politically exposed person
            $table->boolean('is_sanctioned')->default(false);
            $table->string('aml_status', 16)->default('pending');
            // pending | clear | flagged | review
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'is_pep']);
            $table->index(['entity_id', 'is_sanctioned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_beneficial_owners');
        Schema::dropIfExists('business_sanctions_checks');
        Schema::dropIfExists('business_verifications');
        Schema::dropIfExists('business_documents');
        Schema::dropIfExists('business_entities');
    }
};
