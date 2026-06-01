<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_rate_cards')) {
            return;
        }

        Schema::create('contract_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_contract_id')->index();
            $table->unsignedBigInteger('service_catalog_id')->index();
            $table->unsignedBigInteger('negotiated_unit_price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_contract_id', 'service_catalog_id'], 'contract_rate_card_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_rate_cards');
    }
};
