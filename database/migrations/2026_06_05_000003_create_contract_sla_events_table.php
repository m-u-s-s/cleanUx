<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_sla_events')) {
            return;
        }

        Schema::create('contract_sla_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mission_id')->index();
            $table->unsignedBigInteger('organization_contract_id')->index();
            $table->string('kind', 16); // response | resolution
            $table->dateTime('due_at');
            $table->dateTime('breached_at')->nullable();
            $table->dateTime('escalated_at')->nullable();
            $table->string('status', 16)->default('pending'); // pending | met | breached | escalated
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['mission_id', 'kind'], 'contract_sla_event_unique');
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_sla_events');
    }
};
