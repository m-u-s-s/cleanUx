<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne le schéma `customer_credits` avec les attentes du model + Livewire admin.
 *
 * Bug latent : la table avait été créée avec un schéma "wallet balance" simple
 * (customer_user_id, balance, currency) mais le model `CustomerCredit` et
 * `CustomerCreditsManager` s'attendent à un schéma "credit per booking" plus
 * complexe (client_id, rendez_vous_id, amount, remaining_amount, status, ...).
 * Cette migration ajoute les colonnes manquantes en mode additif (compatible
 * backwards — l'ancien usage balance/currency reste fonctionnel).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_credits')) {
            return;
        }

        Schema::table('customer_credits', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_credits', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('customer_credits', 'rendez_vous_id')) {
                $table->unsignedBigInteger('rendez_vous_id')->nullable()->after('client_id');
            }
            if (! Schema::hasColumn('customer_credits', 'type')) {
                $table->string('type', 32)->default('commercial_gesture')->after('rendez_vous_id');
            }
            if (! Schema::hasColumn('customer_credits', 'amount')) {
                $table->decimal('amount', 10, 2)->default(0)->after('type');
            }
            if (! Schema::hasColumn('customer_credits', 'remaining_amount')) {
                $table->decimal('remaining_amount', 10, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('customer_credits', 'status')) {
                $table->string('status', 24)->default('active')->after('remaining_amount');
                // active | used | cancelled | expired
            }
            if (! Schema::hasColumn('customer_credits', 'reason')) {
                $table->string('reason', 191)->nullable()->after('status');
            }
            if (! Schema::hasColumn('customer_credits', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('customer_credits', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('notes');
            }
        });

        // Backfill client_id depuis customer_user_id si l'ancienne colonne existe et la nouvelle est null
        if (Schema::hasColumn('customer_credits', 'customer_user_id')
            && Schema::hasColumn('customer_credits', 'client_id')) {
            DB::table('customer_credits')
                ->whereNull('client_id')
                ->whereNotNull('customer_user_id')
                ->update(['client_id' => DB::raw('customer_user_id')]);
        }
        // Backfill amount depuis balance si dispo
        if (Schema::hasColumn('customer_credits', 'balance')
            && Schema::hasColumn('customer_credits', 'amount')) {
            DB::table('customer_credits')
                ->where('amount', 0)
                ->whereNotNull('balance')
                ->update(['amount' => DB::raw('balance'), 'remaining_amount' => DB::raw('balance')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_credits')) {
            return;
        }
        Schema::table('customer_credits', function (Blueprint $table) {
            foreach (['client_id', 'rendez_vous_id', 'type', 'amount', 'remaining_amount', 'status', 'reason', 'notes', 'expires_at'] as $col) {
                if (Schema::hasColumn('customer_credits', $col)) {
                    if ($col === 'client_id') {
                        // drop FK first (SQLite ne support pas mais idempotent via try)
                        try { $table->dropForeign(['client_id']); } catch (\Throwable) {}
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
