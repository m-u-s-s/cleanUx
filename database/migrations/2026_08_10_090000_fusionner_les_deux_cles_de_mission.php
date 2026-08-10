<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNE MISSION, UNE RÉSERVATION, UNE CLÉ.
 *
 * `missions` portait DEUX colonnes vers la même table `bookings` — `booking_id` et
 * `rendez_vous_id` — selon le chemin qui avait créé la ligne : le moteur de commande écrivait la
 * première, la synchronisation depuis un rendez-vous la seconde. Aucune ligne ne portait les deux.
 *
 * LE COÛT ÉTAIT PAYÉ PAR CHAQUE LECTEUR. `Mission::booking()` choisissait sa clé à l'exécution —
 * ce que le chargement anticipé de Laravel ne sait pas faire —, d'où une seconde relation
 * `bookingViaBookingId()` créée pour contourner, puis une troisième, `rendezVous()`. Trois chemins
 * pour une question à une réponse, et un appelant sur trois se trompait : la modale d'offre s'est
 * ouverte sur des tirets, le dispatch a cherché des réservations qu'il ne trouvait pas.
 *
 * `booking_id` GAGNE, et le schéma l'avait déjà tranché : elle porte une contrainte de clé
 * étrangère vers `bookings`, `rendez_vous_id` n'en a jamais eu. L'une est une clé, l'autre un
 * entier libre.
 *
 * LE REPORT EST CONSERVATEUR : on ne recopie que les valeurs qui désignent une réservation
 * existante. Une mission orpheline le reste — la faire pointer sur une ligne absente échangerait
 * une donnée manquante contre une donnée fausse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions') || ! Schema::hasColumn('missions', 'rendez_vous_id')) {
            return;
        }

        /*
         * REPORT PORTABLE, pas un `UPDATE ... INNER JOIN`.
         *
         * La suite de tests tourne sur SQLite et l'application sur MySQL strict : la jointure dans
         * un UPDATE n'existe que du second côté. Une migration qui ne s'applique que sur l'un des
         * deux se découvre au moment du déploiement, jamais avant.
         */
        DB::table('missions')
            ->whereNull('booking_id')
            ->whereNotNull('rendez_vous_id')
            ->whereIn('rendez_vous_id', DB::table('bookings')->select('id'))
            ->update(['booking_id' => DB::raw('rendez_vous_id')]);

        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('rendez_vous_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions') || Schema::hasColumn('missions', 'rendez_vous_id')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            $table->unsignedBigInteger('rendez_vous_id')->nullable()->after('booking_id');
        });

        // Le retour arrière rend la colonne, pas l'ambiguïté : les lignes reçoivent la même valeur
        // que `booking_id`, ce qui est exact pour toutes celles que la montée a reportées.
        DB::table('missions')
            ->whereNotNull('booking_id')
            ->update(['rendez_vous_id' => DB::raw('booking_id')]);
    }
};
