<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE CLIENT N'EST PAS TOUJOURS LÀ (F14), ET ON LUI DEMANDE SI TOUT VA BIEN (F15).
 *
 * F14 — LA PREUVE DE PRÉSENCE SUPPOSE UN CLIENT PRÉSENT, et c'est le défaut du dispositif actuel.
 * Le code à six chiffres est affiché par le client et saisi par le prestataire : il atteste que les
 * deux personnes sont face à face. Excellent quand c'est vrai — impossible quand le client travaille
 * et laisse la clé chez la voisine, ce qui est le cas ordinaire du ménage à domicile.
 *
 * Aujourd'hui, ces interventions se déroulent hors du dispositif : le prestataire ne peut pas
 * démarrer, ou quelqu'un contourne. Déclarer son absence À L'AVANCE bascule la preuve sur une PHOTO
 * D'ARRIVÉE horodatée et géolocalisée — moins forte qu'une confrontation, mais infiniment plus
 * qu'un contournement non tracé.
 *
 * LE CONTACT DE SECOURS N'EST PAS DÉCORATIF. Un prestataire devant une porte fermée, avec une clé
 * qui n'est pas où on lui a dit, n'a aujourd'hui que le numéro d'un client en réunion. Le voisin qui
 * détient le double est la seule information qui débloque la situation.
 *
 * F15 — LE PING DE MI-MISSION est une question, pas un formulaire : « tout va bien ? », une réponse
 * en un geste. Il vaut par son MOMENT — au milieu, quand il est encore temps de corriger — et pas
 * après, quand il ne reste que l'avis à écrire et le litige à ouvrir. Deux colonnes suffisent :
 * quand on a demandé, et ce qui a été répondu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'client_absent')) {
                // Défaut à faux : la présence reste le cas normal, et le code à six chiffres la
                // preuve par défaut. Basculer l'ensemble sur la photo affaiblirait le dispositif
                // pour tout le monde afin de servir une minorité.
                $table->boolean('client_absent')->default(false)->after('client_presence_confirmed_at');
            }

            if (! Schema::hasColumn('bookings', 'client_absent_instructions')) {
                $table->text('client_absent_instructions')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'backup_contact_name')) {
                $table->string('backup_contact_name')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'backup_contact_phone')) {
                $table->string('backup_contact_phone', 32)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'checkin_ping_sent_at')) {
                $table->timestamp('checkin_ping_sent_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'checkin_ping_answer')) {
                // `ok` ou `probleme` : deux réponses, parce qu'une échelle de 1 à 10 au milieu
                // d'une intervention ne se remplit pas. La nuance viendra de l'avis, plus tard.
                $table->string('checkin_ping_answer', 16)->nullable();
            }

            if (! Schema::hasColumn('bookings', 'checkin_ping_answered_at')) {
                $table->timestamp('checkin_ping_answered_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            foreach ([
                'client_absent',
                'client_absent_instructions',
                'backup_contact_name',
                'backup_contact_phone',
                'checkin_ping_sent_at',
                'checkin_ping_answer',
                'checkin_ping_answered_at',
            ] as $colonne) {
                if (Schema::hasColumn('bookings', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
