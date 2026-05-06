<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Étend service_catalogs.
 *
 * Ajoute trade_id (FK vers trades) + colonnes nécessaires pour rendre la
 * marketplace réellement multi-métier :
 *   - billing_unit (hour | sqm | flat | quote)
 *   - vat_rate, min_lead_time_hours, requires_site_visit
 *   - icon, color, cover_image_path (UI)
 *   - tags, skills_required (matching prestataire)
 *
 * trade_id est NULLABLE ici. Le backfill (seed ServiceCatalogTradeBackfillSeeder)
 * remplit trade_id pour tous les services existants vers le Trade "Nettoyage".
 * Une seconde migration (à venir si tu veux l'enforcer) pourra le passer NOT NULL.
 *
 * Toutes les opérations utilisent Schema::hasColumn pour rester ré-exécutables sans casser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('service_catalogs', 'trade_id')) {
                $table->foreignId('trade_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('trades')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('service_catalogs', 'billing_unit')) {
                $table->enum('billing_unit', ['hour', 'sqm', 'flat', 'quote'])
                    ->default('hour')
                    ->after('base_price');
            }

            if (! Schema::hasColumn('service_catalogs', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(21.00)->after('currency'); // TVA BE
            }

            if (! Schema::hasColumn('service_catalogs', 'min_lead_time_hours')) {
                $table->unsignedInteger('min_lead_time_hours')->default(24)->after('vat_rate');
            }

            if (! Schema::hasColumn('service_catalogs', 'requires_site_visit')) {
                $table->boolean('requires_site_visit')->default(false)->after('min_lead_time_hours');
            }

            if (! Schema::hasColumn('service_catalogs', 'icon')) {
                $table->string('icon', 60)->nullable()->after('description');
            }

            if (! Schema::hasColumn('service_catalogs', 'color')) {
                $table->string('color', 16)->nullable()->after('icon');
            }

            if (! Schema::hasColumn('service_catalogs', 'cover_image_path')) {
                $table->string('cover_image_path')->nullable()->after('color');
            }

            if (! Schema::hasColumn('service_catalogs', 'tags')) {
                $table->json('tags')->nullable();      // ["interieur","tertiaire","recurrent"]
            }

            if (! Schema::hasColumn('service_catalogs', 'skills_required')) {
                $table->json('skills_required')->nullable(); // ["habilitation_hauteur","caces_r486"]
            }

            if (! Schema::hasColumn('service_catalogs', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }

            // Compatibilité avec le model ServiceCatalog actuel (qui fillable
            // service_type / requires_quote / is_entreprise / sort_order)
            // Si ces colonnes manquent dans la table mais sont dans le fillable,
            // on les ajoute défensivement.
            if (! Schema::hasColumn('service_catalogs', 'service_type')) {
                $table->string('service_type', 60)->default('standard')->after('category');
            }
            if (! Schema::hasColumn('service_catalogs', 'requires_quote')) {
                $table->boolean('requires_quote')->default(false);
            }
            if (! Schema::hasColumn('service_catalogs', 'is_entreprise')) {
                $table->boolean('is_entreprise')->default(false);
            }
            if (! Schema::hasColumn('service_catalogs', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0);
            }
        });

        // Index ajoutés a posteriori (séparé pour ne pas planter si réexécuté)
        Schema::table('service_catalogs', function (Blueprint $table) {
            try {
                $table->index(['trade_id', 'is_active', 'sort_order'], 'svc_trade_active_sort_idx');
            } catch (\Throwable $e) {
                // index probablement déjà présent
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            try { $table->dropIndex('svc_trade_active_sort_idx'); } catch (\Throwable $e) {}
            try { $table->dropConstrainedForeignId('trade_id'); } catch (\Throwable $e) {}

            $cols = [
                'billing_unit', 'vat_rate', 'min_lead_time_hours', 'requires_site_visit',
                'icon', 'color', 'cover_image_path', 'tags', 'skills_required', 'is_featured',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('service_catalogs', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
