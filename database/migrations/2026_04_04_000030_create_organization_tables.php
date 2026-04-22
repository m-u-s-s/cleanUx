<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('postal_code_id')->nullable()->constrained('postal_codes')->nullOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->enum('type', ['individual', 'business', 'entreprise', 'partner'])->default('individual');
            $table->string('tva_number')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('billing_email')->nullable();
            $table->enum('status', ['active', 'inactive', 'prospect', 'suspended'])->default('active');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_multisite')->default(false);
            $table->boolean('is_key_account')->default(false);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('organization_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('postal_code_id')->nullable()->constrained('postal_codes')->nullOnDelete();
            $table->string('name');
            $table->string('site_code')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('access_instructions')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_account_id', 'is_active']);
            $table->unique(['organization_account_id', 'site_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_sites');
        Schema::dropIfExists('organization_accounts');
    }
};
