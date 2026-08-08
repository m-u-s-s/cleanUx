<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QUI A DÉPLACÉ CE RENDEZ-VOUS, ET VERS OÙ ?
 *
 * `booking_reschedule_history` savait dire qu'une date avait changé, et par quel utilisateur. Elle
 * ne disait ni À QUEL TITRE — le client qui s'arrange, l'admin qui corrige, le prestataire qui
 * réorganise sa tournée ne sont pas la même chose — ni que le LIEU avait bougé, parce que le lieu ne
 * bougeait jamais : le service était strictement client/admin, et ne touchait que la date et
 * l'heure.
 *
 * L'exigence 3 dit « l'owner peut changer date, heure ET LIEU ». Une intervention déplacée du
 * bâtiment A au bâtiment B sans trace est une réclamation client impossible à instruire : personne
 * ne peut dire si l'équipe s'est trompée d'adresse ou si on la lui a changée.
 *
 * COLONNES ADDITIVES ET NULLABLES. L'historique existant reste lisible tel quel — un `actor_context`
 * vide signifie « avant que la question soit posée », ce qui est la vérité.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_reschedule_history')) {
            return;
        }

        Schema::table('booking_reschedule_history', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_reschedule_history', 'actor_context')) {
                // client | admin | provider — à quel titre la personne a agi.
                $table->string('actor_context', 20)->nullable();
            }

            if (! Schema::hasColumn('booking_reschedule_history', 'old_site_id')) {
                $table->unsignedBigInteger('old_site_id')->nullable();
            }

            if (! Schema::hasColumn('booking_reschedule_history', 'new_site_id')) {
                $table->unsignedBigInteger('new_site_id')->nullable();
            }

            if (! Schema::hasColumn('booking_reschedule_history', 'old_address')) {
                $table->string('old_address', 255)->nullable();
            }

            if (! Schema::hasColumn('booking_reschedule_history', 'new_address')) {
                $table->string('new_address', 255)->nullable();
            }
        });
    }

    /** `down()` volontairement vide : retirer ces colonnes effacerait l'historique qu'elles portent. */
    public function down(): void
    {
        // Migrations non destructives uniquement.
    }
};
