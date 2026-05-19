<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_access_tokens', 'display_name')) {
                $table->string('display_name', 191)->nullable()->after('name');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'description')) {
                $table->text('description')->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'rate_limit_per_minute')) {
                $table->unsignedSmallInteger('rate_limit_per_minute')->nullable()->after('abilities');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'owner_role')) {
                $table->string('owner_role', 32)->nullable()->after('rate_limit_per_minute');
                // api_partner | admin | client | provider | enterprise
            }
            if (! Schema::hasColumn('personal_access_tokens', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
            }
            if (! Schema::hasColumn('personal_access_tokens', 'suspended_reason')) {
                $table->text('suspended_reason')->nullable();
            }
            if (! Schema::hasColumn('personal_access_tokens', 'rotated_from_token_id')) {
                $table->unsignedBigInteger('rotated_from_token_id')->nullable();
            }
            if (! Schema::hasColumn('personal_access_tokens', 'rotated_at')) {
                $table->timestamp('rotated_at')->nullable();
            }
            if (! Schema::hasColumn('personal_access_tokens', 'rotation_grace_until')) {
                $table->timestamp('rotation_grace_until')->nullable();
            }
            if (! Schema::hasColumn('personal_access_tokens', 'last_used_ip_hash')) {
                $table->char('last_used_ip_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('personal_access_tokens', 'usage_count')) {
                $table->unsignedInteger('usage_count')->default(0);
            }
            if (! Schema::hasColumn('personal_access_tokens', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        Schema::create('api_token_scopes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('category', 24);  // read | write | admin
            $table->string('required_role', 32)->nullable();
            // owner_role minimum requis pour pouvoir détenir ce scope (null = libre)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dangerous')->default(false);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('api_token_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('token_id');
            $table->string('route_path', 191)->nullable();
            $table->string('method', 8)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('response_size_bytes')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_short', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['token_id', 'occurred_at']);
            $table->index(['response_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_usages');
        Schema::dropIfExists('api_token_scopes');
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            foreach ([
                'display_name', 'description', 'rate_limit_per_minute', 'owner_role',
                'suspended_at', 'suspended_reason', 'rotated_from_token_id', 'rotated_at',
                'rotation_grace_until', 'last_used_ip_hash', 'usage_count', 'metadata',
            ] as $col) {
                if (Schema::hasColumn('personal_access_tokens', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
