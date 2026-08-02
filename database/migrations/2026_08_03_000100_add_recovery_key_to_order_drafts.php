<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La clé qui permet de retrouver son panier quand le cookie de session a disparu.
 *
 * La loi 10 demande que l'état survive « session ET localStorage ». Le panier vivait déjà en base,
 * retrouvé par un jeton de session : effacer les cookies, ou une session expirée, le rendait
 * pourtant introuvable — alors qu'il est toujours là.
 *
 * POURQUOI PAS LE JETON DE SESSION DANS `localStorage`. Ce jeton ouvre un panier qui contient
 * l'adresse du domicile de quelqu'un. Le cookie qui le porte est `httpOnly` : aucune XSS ne le
 * lit. Le recopier dans `localStorage` le rendrait lisible par n'importe quel script injecté, et
 * pour toujours.
 *
 * D'où une clé DISTINCTE, avec trois limites que le jeton de session n'a pas :
 *
 *   — elle est stockée HACHÉE ici : une fuite de la base ne donne aucune clé utilisable ;
 *   — elle TOURNE à chaque usage : une clé volée ne sert qu'une fois, et son vol se voit ;
 *   — elle EXPIRE : passé le délai, le panier reste en base mais n'est plus rattrapable ainsi.
 *
 * Le cookie reste la voie normale. Celle-ci est un rattrapage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('order_drafts', 'recovery_key_hash')) {
                $table->string('recovery_key_hash', 64)->nullable()->after('session_token');
                $table->timestamp('recovery_key_expires_at')->nullable()->after('recovery_key_hash');

                $table->index('recovery_key_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_drafts', function (Blueprint $table) {
            if (Schema::hasColumn('order_drafts', 'recovery_key_hash')) {
                $table->dropIndex(['recovery_key_hash']);
                $table->dropColumn(['recovery_key_hash', 'recovery_key_expires_at']);
            }
        });
    }
};
