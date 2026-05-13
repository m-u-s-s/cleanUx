<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('country_billing_profiles')) {
            return;
        }

        Schema::create('country_billing_profiles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('country_id')->nullable()->unique();

            $table->string('currency_code', 3)->default('EUR');
            $table->string('currency_symbol', 8)->default('€');

            $table->decimal('default_tax_rate', 5, 2)->default(21.00);
            $table->string('tax_label')->default('TVA');

            $table->boolean('requires_vat_number')->default(true);
            $table->boolean('supports_invoicing')->default(true);
            $table->boolean('supports_credit_notes')->default(true);

            $table->string('invoice_prefix')->default('CUX');
            $table->string('quote_prefix')->default('DEVIS');

            $table->json('invoice_settings')->nullable();
            $table->json('payment_settings')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_billing_profiles');
    }
};