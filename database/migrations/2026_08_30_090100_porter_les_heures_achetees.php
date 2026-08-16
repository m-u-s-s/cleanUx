<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES HEURES ACHETÉES — un engagement, pas une estimation.
 *
 * POURQUOI UNE COLONNE À PART, ALORS QUE `duree_estimee` EXISTE DÉJÀ.
 *
 * Ce sont deux notions différentes, et les fondre serait exactement le défaut qui revient le plus
 * souvent dans ce dépôt :
 *
 *   `duree_estimee`      — combien de temps on PENSE que ça prendra. Existe sur toutes les
 *                          réservations, sert au planning, à l'anti-chevauchement, à l'agenda.
 *   `purchased_minutes`  — combien de temps le client a PAYÉ. N'existe que sur les métiers
 *                          horaires, engage les deux parties, et grandit quand le client prolonge.
 *
 * Sur une réservation forfaitaire, la première a un sens et la seconde n'en a aucun — d'où
 * `nullable`, et `null` veut dire « ce service n'est pas vendu au temps ». Sur une réservation
 * horaire elles partent égales, puis divergent dès la première prolongation : c'est précisément à
 * ce moment-là qu'une colonne unique aurait menti, en laissant croire qu'on avait ré-estimé la
 * mission alors que le client avait acheté du temps en plus.
 *
 * C'est aussi la colonne à laquelle le chronomètre compare le temps réellement passé.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Le panier : une ligne par métier commandé, donc des heures par métier. Un client peut
        // acheter deux heures de ménage et trois heures de repassage dans la même commande.
        if (Schema::hasTable('order_draft_items') && ! Schema::hasColumn('order_draft_items', 'purchased_minutes')) {
            Schema::table('order_draft_items', function (Blueprint $table) {
                $table->unsignedInteger('purchased_minutes')->nullable()->after('duration_min');
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'purchased_minutes')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedInteger('purchased_minutes')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'purchased_minutes')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('purchased_minutes');
            });
        }

        if (Schema::hasTable('order_draft_items') && Schema::hasColumn('order_draft_items', 'purchased_minutes')) {
            Schema::table('order_draft_items', function (Blueprint $table) {
                $table->dropColumn('purchased_minutes');
            });
        }
    }
};
