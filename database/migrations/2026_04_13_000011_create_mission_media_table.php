<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('media_type'); // before_photo, after_photo, incident_photo
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'media_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_media');
    }
};