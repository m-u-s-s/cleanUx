<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->string('slug', 64)->unique();
            $table->string('plan_code', 32)->default('basic');
            $table->string('status', 16)->default('active');  // active | trial | suspended | archived
            $table->string('primary_domain', 191)->nullable();
            $table->string('contact_email', 191)->nullable();
            $table->foreignId('billing_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('default_locale', 8)->default('fr');
            $table->string('default_currency', 3)->default('EUR');
            $table->string('default_country_code', 2)->default('BE');
            $table->json('settings')->nullable();
            $table->json('theming')->nullable();
            $table->json('features')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason', 500)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'plan_code']);
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain', 191)->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('ssl_status', 16)->default('pending');  // pending | ready | failed
            $table->timestamp('certificate_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_primary']);
            $table->index(['ssl_status']);
        });

        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24);  // owner | admin | member | guest
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id'], 'tenant_users_tenant_user_unique');
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
