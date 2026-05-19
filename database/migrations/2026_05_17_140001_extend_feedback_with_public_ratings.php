<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            if (! Schema::hasColumn('feedback', 'direction')) {
                $table->enum('direction', ['client_to_provider', 'provider_to_client'])
                    ->default('client_to_provider')
                    ->after('employe_id');
            }

            if (! Schema::hasColumn('feedback', 'punctuality_score')) {
                $table->unsignedTinyInteger('punctuality_score')->nullable()->after('rating');
            }
            if (! Schema::hasColumn('feedback', 'quality_score')) {
                $table->unsignedTinyInteger('quality_score')->nullable()->after('punctuality_score');
            }
            if (! Schema::hasColumn('feedback', 'communication_score')) {
                $table->unsignedTinyInteger('communication_score')->nullable()->after('quality_score');
            }
            if (! Schema::hasColumn('feedback', 'value_score')) {
                $table->unsignedTinyInteger('value_score')->nullable()->after('communication_score');
            }

            if (! Schema::hasColumn('feedback', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('status');
            }
            if (! Schema::hasColumn('feedback', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('is_public');
            }
            if (! Schema::hasColumn('feedback', 'hidden_reason')) {
                $table->string('hidden_reason')->nullable()->after('is_hidden');
            }
            if (! Schema::hasColumn('feedback', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('hidden_reason');
            }
            if (! Schema::hasColumn('feedback', 'hidden_by_user_id')) {
                $table->unsignedBigInteger('hidden_by_user_id')->nullable()->after('hidden_at');
            }

            if (! Schema::hasColumn('feedback', 'provider_response')) {
                $table->text('provider_response')->nullable()->after('reponse_admin');
            }
            if (! Schema::hasColumn('feedback', 'provider_responded_at')) {
                $table->timestamp('provider_responded_at')->nullable()->after('provider_response');
            }

            if (! Schema::hasColumn('feedback', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('answered_at');
            }
            if (! Schema::hasColumn('feedback', 'reports_count')) {
                $table->unsignedInteger('reports_count')->default(0)->after('published_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            foreach ([
                'direction',
                'punctuality_score',
                'quality_score',
                'communication_score',
                'value_score',
                'is_public',
                'is_hidden',
                'hidden_reason',
                'hidden_at',
                'hidden_by_user_id',
                'provider_response',
                'provider_responded_at',
                'published_at',
                'reports_count',
            ] as $col) {
                if (Schema::hasColumn('feedback', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
