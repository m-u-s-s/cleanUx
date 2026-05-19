<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->json('trade_codes')->nullable();   // null = all trades
            $table->string('phase', 16);               // pre | during | post | all
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_template_id')->nullable()
                ->constrained('quality_checklists')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['phase', 'is_active']);
        });

        Schema::create('quality_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')
                ->constrained('quality_checklists')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('code', 64);
            $table->string('label', 191);
            $table->text('description')->nullable();
            $table->string('item_type', 24);  // boolean | rating | text | photo | measurement | select
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->json('valid_options')->nullable();
            $table->json('expected_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['checklist_id', 'position']);
        });

        Schema::create('mission_quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mission_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->foreignId('checklist_id')
                ->constrained('quality_checklists')->cascadeOnDelete();
            $table->string('phase', 16);
            $table->string('status', 24)->default('draft');
            // draft | in_progress | submitted | validated_client | validated_admin | disputed | rejected

            $table->foreignId('submitted_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('validated_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->decimal('score_calculated', 6, 2)->nullable();
            $table->unsignedInteger('score_max')->nullable();

            $table->text('dispute_reason')->nullable();
            $table->timestamp('disputed_at')->nullable();

            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'phase']);
            $table->index(['status', 'submitted_at']);
            $table->index('booking_id');
        });

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')
                ->constrained('mission_quality_inspections')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')
                ->constrained('quality_checklist_items')->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->unsignedSmallInteger('photos_count')->default(0);
            $table->text('comment')->nullable();
            $table->boolean('met')->default(false);
            $table->unsignedSmallInteger('score_awarded')->default(0);
            $table->foreignId('recorded_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'checklist_item_id'], 'inspection_items_pair_unique');
        });

        Schema::create('inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')
                ->constrained('mission_quality_inspections')->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->nullable()
                ->constrained('inspection_items')->nullOnDelete();
            $table->string('photo_path', 500);
            $table->string('photo_type', 16);  // before | during | after | defect | signature_proof
            $table->foreignId('uploaded_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->char('ip_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'photo_type']);
        });

        Schema::create('client_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')
                ->constrained('mission_quality_inspections')->cascadeOnDelete();
            $table->foreignId('signer_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('signer_name', 191);
            $table->char('signer_email_hash', 64)->nullable();
            $table->text('signature_data');  // base64 SVG or hash
            $table->timestamp('signed_at');
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_short', 191)->nullable();
            $table->string('terms_version', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_signatures');
        Schema::dropIfExists('inspection_photos');
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('mission_quality_inspections');
        Schema::dropIfExists('quality_checklist_items');
        Schema::dropIfExists('quality_checklists');
    }
};
