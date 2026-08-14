<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE POINT D'ARRIVÉE, ET LA ROUTE ENTRE LES DEUX.
 *
 * ⚠️ `destination_lat` / `destination_lng` RESTE LE POINT A. Le nom trompe et c'est le piège de
 * cette migration : sur `bookings`, « destination » désigne depuis toujours le lieu de
 * l'INTERVENTION — là où le prestataire se rend. Sur une course, c'est le point de PRISE EN CHARGE.
 * Rien n'y est touché : tout ce qui lit ces colonnes — geofence, suivi, dispatch de proximité,
 * preuve de présence — continue de désigner exactement le même endroit qu'avant.
 *
 * Le point de DÉPOSE arrive dans des colonnes qui portent son nom, `dropoff_*`. Réutiliser
 * `destination_*` pour lui aurait fait dire deux choses à une même colonne selon le métier — le
 * défaut dominant de ce dépôt — et la clôture d'une course aurait été refusée pour éloignement du
 * lieu où elle a commencé.
 *
 * `route_distance_m` et `route_duration_s` sont mesurés À LA COMMANDE, pas après. C'est ce qui
 * permet d'annoncer un prix au kilomètre AVANT que le client valide : un tarif découvert à
 * l'arrivée est exactement ce qu'on reproche aux taxis, et ce que les plateformes de VTC ont
 * corrigé en affichant le montant d'avance. `route_source` dit qui a répondu — un fournisseur
 * d'itinéraire, ou la ligne droite de repli — pour qu'une estimation grossière soit reconnaissable
 * après coup plutôt que confondue avec une mesure routière.
 *
 * TOUT EST NULLABLE. Une commande ordinaire n'a pas de point d'arrivée, et ne doit pas en inventer un.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['order_drafts', 'bookings'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'dropoff_address')) {
                    $blueprint->string('dropoff_address')->nullable();
                }

                if (! Schema::hasColumn($table, 'dropoff_lat')) {
                    // Même précision que `destination_lat` : sept décimales situent à un centimètre.
                    $blueprint->decimal('dropoff_lat', 10, 7)->nullable();
                }

                if (! Schema::hasColumn($table, 'dropoff_lng')) {
                    $blueprint->decimal('dropoff_lng', 10, 7)->nullable();
                }

                if (! Schema::hasColumn($table, 'dropoff_postal_code')) {
                    $blueprint->string('dropoff_postal_code', 12)->nullable();
                }

                if (! Schema::hasColumn($table, 'dropoff_place_id')) {
                    $blueprint->string('dropoff_place_id')->nullable();
                }

                if (! Schema::hasColumn($table, 'route_distance_m')) {
                    $blueprint->unsignedInteger('route_distance_m')->nullable();
                }

                if (! Schema::hasColumn($table, 'route_duration_s')) {
                    $blueprint->unsignedInteger('route_duration_s')->nullable();
                }

                if (! Schema::hasColumn($table, 'route_source')) {
                    // `google`, `mapbox`, `haversine`… : une ligne droite ne doit pas se faire
                    // passer pour une mesure routière quand on relit le devis six mois plus tard.
                    $blueprint->string('route_source', 24)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $colonnes = [
            'dropoff_address', 'dropoff_lat', 'dropoff_lng', 'dropoff_postal_code',
            'dropoff_place_id', 'route_distance_m', 'route_duration_s', 'route_source',
        ];

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $colonnes) {
                foreach ($colonnes as $colonne) {
                    if (Schema::hasColumn($table, $colonne)) {
                        $blueprint->dropColumn($colonne);
                    }
                }
            });
        }
    }
};
