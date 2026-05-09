<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 14 — Documents uploadés par le prestataire pour vérification KYC.
 *
 * Workflow type :
 *   1. Provider upload une carte d'identité → ProviderOnboardingDocument
 *      type='identity_card', status='pending_review'
 *   2. Admin valide → status='approved'
 *   3. Si tous les documents requis sont approved → ProviderProfile.verification_status='verified'
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('provider_onboarding_documents')) {
            return;
        }

        Schema::create('provider_onboarding_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // identity_card | passport | residence_permit | tax_id | insurance |
            // diploma | criminal_record | other
            $table->string('document_type', 50);

            // pending_review | approved | rejected
            $table->string('status', 20)->default('pending_review');

            $table->string('file_path', 500);
            $table->string('file_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();

            // Si rejeté, raison (visible par le provider)
            $table->text('rejection_reason')->nullable();

            // Champs de validation
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Validité du document (expiration carte d'identité, etc.)
            $table->date('expires_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_type']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_onboarding_documents');
    }
};
