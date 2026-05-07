<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 — Table de stockage des taux de change.
 *
 * Une ligne = un taux à une date donnée :
 *   base_currency  → quote_currency  rate  effective_at
 *   EUR            → USD             1.087 2026-05-07 12:00
 *
 * Lookup : SELECT rate FROM currency_rates
 *          WHERE base_currency = 'EUR' AND quote_currency = 'USD'
 *          ORDER BY effective_at DESC LIMIT 1
 *
 * Mise à jour via job artisan `currencies:refresh` (à brancher sur ECB ou
 * un fournisseur tiers de ton choix : ExchangeRate-API, Fixer, Open Exchange Rates).
 *
 * Index unique sur (base, quote, effective_at) pour permettre l'historique
 * tout en empêchant les doublons exacts.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('currency_rates')) {
            return;
        }

        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);   // ex: EUR
            $table->string('quote_currency', 3);  // ex: USD
            $table->decimal('rate', 18, 8);        // précision élevée
            $table->timestamp('effective_at');     // date du taux
            $table->string('source', 64)->nullable(); // 'ECB', 'manual', 'fixer.io', etc.
            $table->timestamps();

            $table->index(['base_currency', 'quote_currency', 'effective_at'], 'currency_rates_lookup_idx');
            $table->unique(['base_currency', 'quote_currency', 'effective_at'], 'currency_rates_unique');
        });

        // Seed initial : quelques taux de référence pour ne pas crasher en dev
        $now = now();
        \DB::table('currency_rates')->insert([
            // EUR base
            ['base_currency' => 'EUR', 'quote_currency' => 'USD', 'rate' => 1.087, 'effective_at' => $now, 'source' => 'seed', 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'EUR', 'quote_currency' => 'GBP', 'rate' => 0.857, 'effective_at' => $now, 'source' => 'seed', 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'EUR', 'quote_currency' => 'CHF', 'rate' => 0.945, 'effective_at' => $now, 'source' => 'seed', 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'EUR', 'quote_currency' => 'CAD', 'rate' => 1.495, 'effective_at' => $now, 'source' => 'seed', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
