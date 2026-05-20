<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('plate', 24)->unique();
            $table->string('brand', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('vehicle_type', 24);  // van | truck | car | scooter | trailer | other
            $table->string('fuel_type', 16)->nullable();   // diesel | petrol | electric | hybrid | gnv
            $table->unsignedInteger('capacity_kg')->nullable();
            $table->decimal('capacity_volume_m3', 6, 2)->nullable();
            $table->string('status', 16)->default('available');
            // available | in_use | maintenance | retired
            $table->foreignId('current_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('current_location')->nullable();   // {lat, lng, label}
            $table->timestamp('last_seen_at')->nullable();
            $table->string('registered_country', 2)->nullable();
            $table->date('registered_at')->nullable();
            $table->date('insurance_expires_at')->nullable();
            $table->date('control_technique_expires_at')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'vehicle_type']);
            $table->index(['current_provider_id']);
            $table->index(['insurance_expires_at']);
            $table->index(['control_technique_expires_at']);
        });

        Schema::create('fleet_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->string('equipment_type', 24);  // tool | machine | consumable | protection
            $table->string('category', 32)->nullable();
            // cleaning | painting | plumbing | electrical | roofing | misc
            $table->string('serial_number', 64)->nullable();
            $table->string('brand', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('status', 16)->default('available');
            // available | in_use | maintenance | retired | lost
            $table->foreignId('current_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('value_cents')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->date('purchased_at')->nullable();
            $table->date('warranty_expires_at')->nullable();
            $table->json('current_location')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'equipment_type']);
            $table->index(['category']);
            $table->index(['current_provider_id']);
        });

        Schema::create('fleet_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('vehicle_id')->nullable()->constrained('fleet_vehicles')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('fleet_equipment')->nullOnDelete();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->foreignId('provider_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('active');
            // active | completed | cancelled
            $table->timestamp('assigned_at');
            $table->timestamp('expected_return_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('returned_condition', 16)->nullable();   // ok | damaged | lost | needs_maintenance
            $table->text('damage_notes')->nullable();
            $table->unsignedInteger('start_odometer_km')->nullable();
            $table->unsignedInteger('end_odometer_km')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['provider_user_id', 'status']);
            $table->index(['booking_id']);
            $table->index(['vehicle_id', 'status']);
            $table->index(['equipment_id', 'status']);
        });

        Schema::create('fleet_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('fleet_equipment')->cascadeOnDelete();
            $table->string('maintenance_type', 24);  // preventive | corrective | inspection
            $table->timestamp('performed_at');
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_name', 191)->nullable();   // garage / workshop external
            $table->unsignedInteger('cost_cents')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('next_due_at')->nullable();
            $table->unsignedInteger('odometer_at_service_km')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'performed_at']);
            $table->index(['equipment_id', 'performed_at']);
            $table->index(['next_due_at']);
        });

        Schema::create('fleet_certifications', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 24);   // vehicle | equipment | provider
            $table->unsignedBigInteger('subject_id');
            $table->string('certification_type', 48);
            // insurance | control_technique | driver_license | professional_qualification
            // asbestos_training | height_work_authorization | first_aid | other
            $table->string('reference', 191)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('issuing_authority', 191)->nullable();
            $table->string('document_path', 500)->nullable();
            $table->string('status', 24)->default('active');
            // active | expiring_soon | expired | revoked
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['expires_at', 'status']);
            $table->index(['certification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_certifications');
        Schema::dropIfExists('fleet_maintenance_logs');
        Schema::dropIfExists('fleet_assignments');
        Schema::dropIfExists('fleet_equipment');
        Schema::dropIfExists('fleet_vehicles');
    }
};
