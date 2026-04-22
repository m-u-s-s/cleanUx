<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_geocodes', function (Blueprint $table) {
            $table->id();
            $table->string('lookup_hash')->unique();
            $table->string('address_line')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('provider')->default('nominatim');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['postal_code', 'city', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_geocodes');
    }
};