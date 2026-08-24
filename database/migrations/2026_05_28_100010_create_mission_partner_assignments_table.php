<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schema-drift repair: create the `mission_partner_assignments` table. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_partner_assignments')) {
            Schema::create('mission_partner_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_id')->nullable()->index();
                $table->unsignedBigInteger('service_partner_id')->nullable()->index();
                $table->string('assignment_status')->nullable()->index();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->decimal('agreed_rate', 10, 2)->nullable();
                $table->json('sla_snapshot')->nullable();
                $table->json('instructions_snapshot')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_partner_assignments');
    }
};
