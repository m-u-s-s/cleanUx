<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 64);
            $table->string('symbol', 8)->nullable();
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->string('source', 24);  // mock|ecb|openexchange|manual|fallback
            $table->timestamp('fetched_at');
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['base_currency', 'quote_currency', 'fetched_at']);
            $table->index(['source', 'fetched_at']);
        });

        Schema::create('currency_conversions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_amount_cents');
            $table->string('source_currency', 3);
            $table->unsignedBigInteger('target_amount_cents');
            $table->string('target_currency', 3);
            $table->foreignId('exchange_rate_id')->nullable()
                ->constrained('exchange_rates')->nullOnDelete();
            $table->decimal('rate_used', 18, 8);
            $table->decimal('fee_percent', 6, 4)->default(0);
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->timestamp('converted_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_currency', 'target_currency']);
            $table->index(['source_type', 'source_id']);
            $table->index('converted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_conversions');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
