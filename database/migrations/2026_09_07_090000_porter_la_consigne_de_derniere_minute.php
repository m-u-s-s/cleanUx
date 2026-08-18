<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA CONSIGNE D'ACCÈS DE DERNIÈRE MINUTE.
 *
 * ── POURQUOI UNE COLONNE À PART ──────────────────────────────────────────────────────────────
 *
 * La fiche d'accès lit trois sources, dans cet ordre : le site de la société, le lieu du carnet du
 * client, puis son commentaire de commande. Toutes trois sont écrites AVANT l'intervention, souvent
 * des semaines avant.
 *
 * Ce qui manque est la quatrième : « le digicode a changé ce matin, c'est 4589 ». Elle s'écrit
 * pendant que le prestataire est en route, et elle prime sur tout le reste parce qu'elle est la
 * plus récente. L'écrire dans `access_instructions` du carnet écraserait une consigne durable par
 * une consigne du jour — et la semaine suivante, un autre prestataire lirait un code périmé.
 *
 * ── DEUX COLONNES, PARCE QU'UNE CONSIGNE PÉRIME ──────────────────────────────────────────────
 *
 * L'horodatage n'est pas décoratif : une consigne posée il y a trois semaines sur une réservation
 * reportée ne vaut plus rien, et la fiche doit pouvoir le dire plutôt que d'afficher un code faux
 * avec l'aplomb d'un code juste.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'live_access_note')) {
                $table->text('live_access_note')->nullable()->after('client_absent_instructions');
            }

            if (! Schema::hasColumn('bookings', 'live_access_note_at')) {
                $table->timestamp('live_access_note_at')->nullable()->after('live_access_note');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            foreach (['live_access_note', 'live_access_note_at'] as $colonne) {
                if (Schema::hasColumn('bookings', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
