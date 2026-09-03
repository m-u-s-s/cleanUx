<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES COMMISSIONS SE RÈGLENT, ELLES NE SE DÉPLOIENT PLUS.
 *
 * Cinq taux vivaient en dur dans `config/` — prestation 15 %, location entre membres 25 % et par
 * type de bien, deux réglages de pourboire dont AUCUN n'était lu. Changer l'un d'eux demandait un
 * déploiement, et régler « course 8 % / dépannage 18 % » était tout simplement impossible.
 *
 * UNE RÈGLE = UN TAUX + LE CAS OÙ IL S'APPLIQUE. Les discriminants sont tous facultatifs : une
 * règle sans aucun d'eux est le taux général, une règle qui en porte quatre est la plus précise.
 * LA PLUS PRÉCISE GAGNE — pas la plus récente, pas la première trouvée. Sans cet ordre, poser un
 * taux de zone effacerait par accident un taux de métier.
 *
 * LE TAUX SE FIGE AU DEVIS. Une réservation conclue à 15 % le reste, quoi qu'il arrive ensuite à
 * la règle : changer un taux ne doit jamais rouvrir une facture déjà émise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();

            $table->string('label');
            $table->text('note')->nullable();

            // ── LE CAS OÙ ELLE S'APPLIQUE — tout est facultatif ─────────────
            // Le MODULE : prestation, peer_rental, tips, rental… `null` = tous.
            $table->string('module', 40)->nullable();
            // Le TYPE de bien à l'intérieur d'un module (vehicle, stay…).
            $table->string('asset_type', 40)->nullable();

            $table->foreignId('trade_id')->nullable()->constrained('trades')->cascadeOnDelete();
            $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->cascadeOnDelete();

            // LA DURÉE : « location de voiture 20 %, puis 5 % après deux semaines ». La règle
            // longue porte `min_duration_days = 14` et gagne dès que le séjour l'atteint.
            $table->unsignedSmallInteger('min_duration_days')->nullable();

            // LA SAISON : une campagne « 0 % en janvier » se pose et s'enlève par des dates.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            // ── CE QU'ELLE IMPOSE ───────────────────────────────────────────
            // DE 0 À 100 : gratuit comme confiscatoire, c'est une décision, pas une erreur.
            $table->decimal('percent', 5, 2);

            // Le plancher, en centimes. `null` = celui du module. `0` = pas de plancher, et c'est
            // le SEUL moyen de rendre une prestation réellement gratuite : un plancher de 2 €
            // prélèverait sinon 2 € sur une commission à 0 %.
            $table->unsignedInteger('min_cents')->nullable();

            $table->boolean('is_active')->default(true);

            // L'ARBITRE DES ÉGALITÉS. Deux règles aussi précises l'une que l'autre ne doivent pas
            // se départager par leur `id` : celle qui porte la priorité la plus haute gagne.
            $table->unsignedSmallInteger('priority')->default(0);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['is_active', 'module']);
            $table->index(['trade_id', 'service_zone_id']);
        });

        // ── L'HISTORIQUE, PARCE QU'UN TAUX EST UNE DÉCISION ────────────────
        Schema::create('commission_rule_revisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('commission_rule_id')->nullable()
                ->constrained('commission_rules')->nullOnDelete();

            $table->string('action', 20); // created | updated | deleted
            $table->decimal('percent_before', 5, 2)->nullable();
            $table->decimal('percent_after', 5, 2)->nullable();
            $table->json('snapshot')->nullable();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_ip', 45)->nullable();

            $table->timestamps();

            $table->index(['commission_rule_id', 'created_at']);
        });

        // ── LE TAUX FIGÉ SUR LA RÉSERVATION ────────────────────────────────
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'commission_rate')) {
                // LE TAUX APPLIQUÉ, ET LA RÈGLE QUI L'A DÉCIDÉ. Sans la seconde, expliquer six
                // mois plus tard pourquoi cette mission a payé 8 % devient impossible.
                $table->decimal('commission_rate', 6, 4)->nullable()->after('platform_fee_cents');
                $table->foreignId('commission_rule_id')->nullable()->after('commission_rate')
                    ->constrained('commission_rules')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'commission_rule_id')) {
                $table->dropConstrainedForeignId('commission_rule_id');
            }

            if (Schema::hasColumn('bookings', 'commission_rate')) {
                $table->dropColumn('commission_rate');
            }
        });

        Schema::dropIfExists('commission_rule_revisions');
        Schema::dropIfExists('commission_rules');
    }
};
