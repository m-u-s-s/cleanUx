<?php

use App\Support\Domain\TradeRouteRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES MÉTIERS QUI EMMÈNENT QUELQU'UN D'UN POINT À UN AUTRE.
 *
 * La plateforme est mono-adresse de bout en bout : une commande porte UN lieu, une mission s'y rend
 * et s'y termine. Un taxi, un remorquage ou un déménagement n'entrent pas dans ce moule — ils ont
 * un départ et une arrivée, et tout le reste en découle : le prix se compte en kilomètres, la
 * mission se termine ailleurs qu'elle n'a commencé, et le prestataire doit savoir conduire.
 *
 * DEUX COLONNES, ET ELLES NE DISENT PAS LA MÊME CHOSE.
 *
 * `questions.location_role` déclare ce qu'est une localisation dans un parcours : le départ ou
 * l'arrivée. C'est de lui, et de lui seul, que se déduit « ce métier est un trajet » — voir
 * {@see TradeRouteRules}. Aucun drapeau booléen ne double cette information :
 * une colonne tenue à la main finirait par contredire les questions, et la plateforme exigerait un
 * permis de conduire pour un métier qui n'emmène plus personne nulle part.
 *
 * `trades.taxi_rules` est une DÉCISION D'EXPLOITATION, indépendante : elle dit que ce métier obéit
 * aux règles du transport de personnes — véhicule récent, carte grise, assurance. Un remorquage est
 * un trajet sans être un taxi ; une navette d'aéroport peut être les deux. Les confondre reviendrait
 * à réclamer une voiture de moins de quatre ans à une dépanneuse.
 *
 * LES DEUX DATES FONT COURIR LA PÉRIODE DE GRÂCE. Sans elles, cocher « règles taxi » un mardi matin
 * couperait le même jour tous les prestataires déjà inscrits sur ce métier, avant même qu'ils aient
 * pu être prévenus de ce qu'on leur demande. Elles enregistrent QUAND l'exigence est née, et le
 * délai part de là.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('questions') && ! Schema::hasColumn('questions', 'location_role')) {
            Schema::table('questions', function (Blueprint $table) {
                // Nulle pour tout type autre que `location` : le rôle ne veut rien dire sur un
                // compteur ou une photo.
                $table->string('location_role', 20)->nullable()->after('type');
            });
        }

        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasColumn('trades', 'taxi_rules')) {
                // Défaut à faux, comme `allows_asap` : ouvrir une exigence est une décision
                // d'administrateur, jamais un oubli de migration.
                $table->boolean('taxi_rules')->default(false);
            }

            if (! Schema::hasColumn('trades', 'route_rules_since')) {
                $table->timestamp('route_rules_since')->nullable();
            }

            if (! Schema::hasColumn('trades', 'taxi_rules_since')) {
                $table->timestamp('taxi_rules_since')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('questions') && Schema::hasColumn('questions', 'location_role')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropColumn('location_role');
            });
        }

        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            foreach (['taxi_rules', 'route_rules_since', 'taxi_rules_since'] as $colonne) {
                if (Schema::hasColumn('trades', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
