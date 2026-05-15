<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_calendar_event_links')) {
            Schema::create('google_calendar_event_links', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('google_calendar_connection_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('rendez_vous_id')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedBigInteger('mission_id')->nullable();

                $table->string('calendar_id')->nullable();
                $table->string('google_event_id')->nullable();
                $table->string('sync_status')->default('pending');
                $table->text('last_sync_error')->nullable();
                $table->timestamp('last_synced_at')->nullable();

                $table->json('payload')->nullable();
                $table->json('meta')->nullable();

                $table->timestamps();

                // Noms courts pour MySQL
                $table->index('google_calendar_connection_id', 'gcal_evt_conn_idx');
                $table->index('user_id', 'gcal_evt_user_idx');
                $table->index('rendez_vous_id', 'gcal_evt_rdv_idx');
                $table->index('booking_id', 'gcal_evt_booking_idx');
                $table->index('mission_id', 'gcal_evt_mission_idx');
                $table->index('sync_status', 'gcal_evt_status_idx');
                $table->index('google_event_id', 'gcal_evt_google_idx');
            });

            return;
        }

        Schema::table('google_calendar_event_links', function (Blueprint $table) {
            if (! Schema::hasColumn('google_calendar_event_links', 'google_calendar_connection_id')) {
                $table->unsignedBigInteger('google_calendar_connection_id')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'rendez_vous_id')) {
                $table->unsignedBigInteger('rendez_vous_id')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'booking_id')) {
                $table->unsignedBigInteger('booking_id')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'mission_id')) {
                $table->unsignedBigInteger('mission_id')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'calendar_id')) {
                $table->string('calendar_id')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'google_event_id')) {
                $table->string('google_event_id')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'sync_status')) {
                $table->string('sync_status')->default('pending');
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'last_sync_error')) {
                $table->text('last_sync_error')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'payload')) {
                $table->json('payload')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'meta')) {
                $table->json('meta')->nullable();
            }

            if (! Schema::hasColumn('google_calendar_event_links', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_event_links');
    }
};