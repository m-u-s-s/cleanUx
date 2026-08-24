<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 1 — Service Options. Variables qu'un client paramètre quand il commande un service. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_catalog_id')
                ->constrained('service_catalogs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('slug', 80);
            $table->string('label', 160);
            $table->text('help_text')->nullable();

            $table->enum('type', ['number', 'boolean', 'select', 'multiselect', 'text'])
                ->default('number');

            $table->json('values')->nullable();      // pour select/multiselect: [{value,label,price_delta}]
            $table->string('unit', 20)->nullable();  // m², h, étage…

            $table->decimal('default_value_num', 12, 2)->nullable();
            $table->string('default_value_str', 255)->nullable();

            $table->boolean('is_required')->default(false);

            // Pricing impact
            $table->enum('price_modifier', ['none', 'fixed', 'percent', 'per_unit'])
                ->default('none');
            $table->decimal('price_modifier_value', 10, 4)->default(0);

            // Bornes de validation pour les number
            $table->decimal('min_value', 12, 2)->nullable();
            $table->decimal('max_value', 12, 2)->nullable();
            $table->decimal('step', 12, 4)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['service_catalog_id', 'slug']);
            $table->index(['service_catalog_id', 'is_active', 'sort_order'], 'svc_opt_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_options');
    }
};
