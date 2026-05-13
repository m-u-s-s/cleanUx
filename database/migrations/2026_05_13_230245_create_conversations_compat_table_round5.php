<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('rendez_vous_id')->nullable()->index();
                $table->unsignedBigInteger('booking_id')->nullable()->index();

                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->unsignedBigInteger('employe_id')->nullable()->index();
                $table->unsignedBigInteger('provider_user_id')->nullable()->index();
                $table->unsignedBigInteger('organization_account_id')->nullable()->index();
                $table->unsignedBigInteger('organization_site_id')->nullable()->index();

                $table->string('type')->nullable()->index();
                $table->string('status')->default('active')->index();
                $table->string('title')->nullable();

                $table->timestamp('last_message_at')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(['rendez_vous_id', 'type'], 'conversations_rdv_type_unique');
            });

            return;
        }

        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'rendez_vous_id')) {
                $table->unsignedBigInteger('rendez_vous_id')->nullable()->index();
            }

            if (! Schema::hasColumn('conversations', 'booking_id')) {
                $table->unsignedBigInteger('booking_id')->nullable()->index();
            }

            if (! Schema::hasColumn('conversations', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }

            if (! Schema::hasColumn('conversations', 'employe_id')) {
                $table->unsignedBigInteger('employe_id')->nullable()->index();
            }

            if (! Schema::hasColumn('conversations', 'provider_user_id')) {
                $table->unsignedBigInteger('provider_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('conversations', 'organization_account_id')) {
                $table->unsignedBigInteger('organization_account_id')->nullable()->index();
            }

            if (! Schema::hasColumn('conversations', 'organization_site_id')) {
                $table->unsignedBigInteger('organization_site_id')->nullable()->index();
            }

            if (! Schema::hasColumn('conversations', 'type')) {
                $table->string('type')->nullable()->index();
            }

            if (! Schema::hasColumn('conversations', 'status')) {
                $table->string('status')->default('active')->index();
            }

            if (! Schema::hasColumn('conversations', 'title')) {
                $table->string('title')->nullable();
            }

            if (! Schema::hasColumn('conversations', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable();
            }

            if (! Schema::hasColumn('conversations', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};