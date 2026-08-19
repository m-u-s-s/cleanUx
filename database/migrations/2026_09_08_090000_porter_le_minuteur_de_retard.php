<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE RETARD SE MESURE DÉJÀ ; CE QUI MANQUE, C'EST DE LE DIRE.
 *
 * `CancellationAnswerVerifier::leProviderEstEnRetard()` sait depuis le premier jour qu'une heure
 * est passée sans que personne n'arrive. Il ne le sait que pour REFUSER une option d'annulation
 * — c'est-à-dire au moment où le client, ayant attendu seul, a déjà décidé de partir.
 *
 * ── POURQUOI TROIS COLONNES ET PAS UNE ───────────────────────────────────────────────────────
 *
 * `late_notified_at` n'existe que pour ne pas répéter l'annonce : la commande passe toutes les
 * cinq minutes, et un client prévenu six fois du même retard reçoit du bruit, pas une
 * information.
 *
 * Les deux autres portent la RÉPONSE du prestataire, et c'est une notion distincte du retard
 * lui-même : le retard est un fait que le serveur constate, l'heure annoncée est une promesse que
 * quelqu'un prend. Les confondre dans une seule colonne ferait disparaître le fait dès que
 * personne ne promet rien — or c'est exactement le cas qui compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'late_notified_at')) {
                $table->timestamp('late_notified_at')->nullable()->after('live_access_note_at');
            }

            if (! Schema::hasColumn('bookings', 'provider_delay_eta_at')) {
                $table->timestamp('provider_delay_eta_at')->nullable()->after('late_notified_at');
            }

            if (! Schema::hasColumn('bookings', 'provider_delay_reason')) {
                $table->string('provider_delay_reason', 180)->nullable()->after('provider_delay_eta_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['late_notified_at', 'provider_delay_eta_at', 'provider_delay_reason'] as $colonne) {
                if (Schema::hasColumn('bookings', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
