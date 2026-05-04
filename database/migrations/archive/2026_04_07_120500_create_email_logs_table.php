<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('status', 32)->default('sent')->index();
            $table->string('channel', 32)->default('mail')->index();
            $table->string('recipient_email')->nullable()->index();
            $table->nullableMorphs('notifiable');
            $table->foreignId('previewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('context')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
