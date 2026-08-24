<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Niveau 1 du catalogue — le SECTEUR — et raccordement des métiers existants. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sectors')) {
            Schema::create('sectors', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('name');

                // Accroche courte affichée sur la carte du carrousel (loi 6.1).
                $table->string('tagline')->nullable();

                // Nom d'icône, jamais un chemin : le rendu reste maîtrisé côté design system.
                $table->string('icon', 80)->nullable();
                $table->string('cover_image_path')->nullable();

                // Couleur d'accent du secteur.
                $table->string('accent_color', 9)->nullable();

                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);

                // Brouillon / publié.
                $table->timestamp('published_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'sort_order']);
            });
        }

        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasColumn('trades', 'sector_id')) {
                $table->foreignId('sector_id')->nullable()->after('id')
                    ->constrained('sectors')->nullOnDelete();
            }

            // Prix plancher du métier, en CENTIMES. Les montants ne sont jamais des flottants :
            // `trades.default_hourly_rate` est un décimal hérité, la commande travaille en entier.
            if (! Schema::hasColumn('trades', 'base_price_cents')) {
                $table->unsignedInteger('base_price_cents')->nullable()->after('default_hourly_rate');
            }

            // fixed | per_hour | per_m2 | per_unit | quote_only — chaîne plutôt qu'enum : ajouter
            // une unité ne doit pas demander une migration de type sur une table déjà peuplée.
            if (! Schema::hasColumn('trades', 'pricing_unit')) {
                $table->string('pricing_unit', 20)->nullable()->after('base_price_cents');
            }

            if (! Schema::hasColumn('trades', 'estimated_duration_min')) {
                $table->unsignedInteger('estimated_duration_min')->nullable()->after('pricing_unit');
            }
            if (! Schema::hasColumn('trades', 'min_duration_min')) {
                $table->unsignedInteger('min_duration_min')->nullable()->after('estimated_duration_min');
            }

            // Tous les métiers ne se prêtent pas aux trois modes : un ravalement de façade n'est pas un service immédiat.
            if (! Schema::hasColumn('trades', 'allows_scheduled')) {
                $table->boolean('allows_scheduled')->default(true)->after('min_duration_min');
            }
            if (! Schema::hasColumn('trades', 'allows_asap')) {
                $table->boolean('allows_asap')->default(false)->after('allows_scheduled');
            }
            if (! Schema::hasColumn('trades', 'allows_bundle')) {
                $table->boolean('allows_bundle')->default(true)->after('allows_asap');
            }

            if (! Schema::hasColumn('trades', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('is_active');
            }
        });

        // « Souvent commandé avec » du mode multi-services : les associations sont des DONNÉES, configurables par l'administrateur sur chaque métier, jamais une liste écrite en dur.
        if (! Schema::hasTable('trade_bundle_suggestions')) {
            Schema::create('trade_bundle_suggestions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();
                $table->foreignId('suggested_trade_id')->constrained('trades')->cascadeOnDelete();

                // Le carreleur passe APRÈS le plombier, et le temps de séchage est un tampon.
                $table->unsignedInteger('default_sequence_gap_min')->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['trade_id', 'suggested_trade_id'], 'trade_suggestion_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_bundle_suggestions');

        Schema::table('trades', function (Blueprint $table) {
            if (Schema::hasColumn('trades', 'sector_id')) {
                $table->dropConstrainedForeignId('sector_id');
            }
            foreach ([
                'base_price_cents', 'pricing_unit', 'estimated_duration_min', 'min_duration_min',
                'allows_scheduled', 'allows_asap', 'allows_bundle', 'published_at',
            ] as $column) {
                if (Schema::hasColumn('trades', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('sectors');
    }
};
