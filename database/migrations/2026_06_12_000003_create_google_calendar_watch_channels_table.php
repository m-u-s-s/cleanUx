<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GCal bidirectionnel — canaux de notification push (Google watch). Permet de
 * router une notification entrante vers la bonne connexion/prestataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_watch_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('google_calendar_connections')->nullOnDelete();
            $table->string('channel_id')->unique();
            $table->string('resource_id')->index();
            $table->string('calendar_id')->default('primary');
            $table->string('sync_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_watch_channels');
    }
};
