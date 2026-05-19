<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);  // mock | google | mapbox
            $table->char('query_hash', 64);  // sha256 of normalized query + country
            $table->string('query', 191);
            $table->string('country_code', 8)->nullable();
            $table->json('results');
            $table->unsignedSmallInteger('result_count')->default(0);
            $table->timestamp('queried_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'query_hash'], 'address_lookups_provider_query_unique');
            $table->index(['expires_at']);
        });

        Schema::create('geocoding_results', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->char('address_hash', 64);
            $table->string('address_input', 500);
            $table->string('country_code', 8)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('formatted_address', 500)->nullable();
            $table->string('place_id', 191)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('locality', 191)->nullable();
            $table->json('components')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'address_hash'], 'geocoding_results_provider_addr_unique');
            $table->index(['place_id']);
            $table->index(['postal_code']);
        });

        Schema::create('distance_calculations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->char('signature_hash', 64);
            $table->decimal('origin_lat', 10, 7);
            $table->decimal('origin_lng', 10, 7);
            $table->decimal('dest_lat', 10, 7);
            $table->decimal('dest_lng', 10, 7);
            $table->string('mode', 16)->default('driving');  // driving | walking | bicycling | transit
            $table->unsignedInteger('distance_meters');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_fallback_haversine')->default(false);
            $table->json('raw')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'signature_hash'], 'distance_calc_provider_sig_unique');
            $table->index(['mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distance_calculations');
        Schema::dropIfExists('geocoding_results');
        Schema::dropIfExists('address_lookups');
    }
};
