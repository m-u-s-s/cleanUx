<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_connections', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->unique();
            $table->string('google_email')->nullable();
            $table->string('google_user_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('calendar_id')->default('primary');
            $table->text('scope')->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'gcal_conn_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('google_calendar_event_links', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('google_calendar_connection_id');
            $table->unsignedBigInteger('booking_id');

            $table->string('google_event_id');
            $table->string('google_calendar_id')->default('primary');
            $table->string('etag')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('google_calendar_connection_id', 'gcal_evt_conn_fk')
                ->references('id')
                ->on('google_calendar_connections')
                ->cascadeOnDelete();

            $table->foreign('booking_id', 'gcal_evt_rdv_fk')
                ->references('id')
                ->on('bookings')
                ->cascadeOnDelete();

            $table->unique(
                ['google_calendar_connection_id', 'booking_id'],
                'gcal_conn_rdv_unique'
            );

            $table->unique(
                ['google_calendar_connection_id', 'google_event_id'],
                'gcal_conn_event_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_event_links');
        Schema::dropIfExists('google_calendar_connections');
    }
};
