<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_checklist_id')->constrained('mission_checklists')->cascadeOnDelete();
            $table->string('label');
            $table->string('item_type')->default('checkbox');
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('pending');
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['mission_checklist_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_checklist_items');
    }
};