<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // 🔹 Identité
            $table->string('name');
            $table->string('slug')->unique();

            // 🔹 Informations légales
            $table->string('tva_number')->nullable()->index();
            $table->string('company_number')->nullable();

            // 🔹 Contact
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // 🔹 Adresse
            $table->string('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('BE');

            // 🔹 Google Maps (important pour ton système)
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('google_place_id')->nullable();

            // 🔹 Stripe Connect (si société prestataire)
            $table->string('stripe_connect_account_id')->nullable();
            $table->string('stripe_connect_status')->default('not_connected');

            // 🔹 Business model
            $table->string('type')->default('client'); 
            // client / prestataire / partenaire

            // 🔹 Paramètres CleanUx
            $table->integer('default_slot_duration')->default(90);
            $table->integer('max_daily_missions')->nullable();

            // 🔹 Statut
            $table->boolean('is_active')->default(true);

            // 🔹 Branding
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};