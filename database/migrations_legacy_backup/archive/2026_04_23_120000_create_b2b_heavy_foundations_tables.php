<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
            $table->foreignId('default_service_partner_id')->nullable()->constrained('service_partners')->nullOnDelete();
            $table->string('contract_reference')->unique();
            $table->string('status')->default('draft');
            $table->string('pricing_model')->default('catalog');
            $table->string('billing_cycle')->default('monthly');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('approval_mode')->default('auto');
            $table->boolean('requires_purchase_order')->default(false);
            $table->string('default_cost_center')->nullable();
            $table->decimal('negotiated_discount_percent', 5, 2)->nullable();
            $table->unsignedInteger('payment_terms_days')->nullable();
            $table->unsignedInteger('sla_response_hours')->nullable();
            $table->unsignedInteger('sla_resolution_hours')->nullable();
            $table->json('allowed_service_catalog_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('enterprise_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
            $table->foreignId('assigned_service_partner_id')->nullable()->constrained('service_partners')->nullOnDelete();
            $table->string('title');
            $table->string('reference')->unique();
            $table->string('status')->default('draft');
            $table->string('priority')->default('normale');
            $table->string('approval_status')->default('pending');
            $table->string('work_type')->default('site_intervention');
            $table->dateTime('requested_start_at')->nullable();
            $table->dateTime('requested_end_at')->nullable();
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->string('purchase_order_number')->nullable();
            $table->string('cost_center')->nullable();
            $table->decimal('budget_amount', 10, 2)->nullable();
            $table->text('instructions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('work_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit')->default('forfait');
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('line_total', 10, 2)->nullable();
            $table->decimal('surface_value', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('work_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_status')->default('pending');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('mission_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
            $table->foreignId('assigned_service_partner_id')->nullable()->constrained('service_partners')->nullOnDelete();
            $table->string('title');
            $table->string('batch_code')->unique();
            $table->string('status')->default('planned');
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->foreignId('enterprise_work_order_id')->nullable()->after('rendez_vous_id')->constrained('enterprise_work_orders')->nullOnDelete();
            $table->foreignId('mission_batch_id')->nullable()->after('enterprise_work_order_id')->constrained('mission_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mission_batch_id');
            $table->dropConstrainedForeignId('enterprise_work_order_id');
        });

        Schema::dropIfExists('mission_batches');
        Schema::dropIfExists('work_order_approvals');
        Schema::dropIfExists('work_order_lines');
        Schema::dropIfExists('enterprise_work_orders');
        Schema::dropIfExists('organization_contracts');
    }
};
