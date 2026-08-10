<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES APPELS PASSÉS DEPUIS UN CANAL D'ÉQUIPE.
 *
 * La note vocale du lot 7 couvre la consigne qu'on laisse ; un appel couvre la question qui
 * n'attend pas — « je suis devant la porte, quel est le code ? ». Rien ne portait cela :
 * `VideoCallService` était un squelette qui levait sur chaque méthode ; il a été supprimé.
 *
 * POURQUOI PERSISTER UN APPEL PLUTÔT QUE DE LAISSER LIVEKIT S'EN CHARGER. Le serveur de médias sait
 * qui est dans une salle À L'INSTANT T ; il ne dit pas qu'un appel a sonné dans le vide à 7 h du
 * matin, ni combien de temps il a duré. C'est cet historique qui permet de rappeler quelqu'un, et
 * de compter un appel MANQUÉ — l'état le plus utile des quatre, et le seul que le média ne connaît
 * pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calls')) {
            return;
        }

        Schema::create('calls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_id')
                ->constrained('channels')
                ->cascadeOnDelete();

            $table->foreignId('initiator_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // audio | video — un appel vidéo depuis un chantier est rare, mais montrer une fuite
            // vaut mille mots.
            $table->string('type', 10)->default('audio');

            // ringing | active | ended | missed
            $table->string('status', 10)->default('ringing');

            /*
             * Le nom de la salle est DÉTERMINISTE et porte l'identifiant de l'appel, pas celui du
             * canal : deux appels successifs dans le même canal ne doivent pas se retrouver dans la
             * même salle, sinon un participant en retard rejoindrait la conversation précédente.
             */
            $table->string('room_name', 120);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            /*
             * Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64 caractères,
             * limite que SQLite ignore — la migration passerait la suite de tests et casserait en
             * production.
             *
             * La lecture chaude est « l'historique de ce canal », d'abord le plus récent.
             */
            $table->index(['channel_id', 'created_at'], 'calls_channel_date_idx');
            $table->index('status', 'calls_status_idx');
        });
    }

    /** `down()` volontairement vide : migrations non destructives uniquement. */
    public function down(): void
    {
        // Retirer la table effacerait l'historique des appels — dont les manqués, qui sont
        // précisément ce qu'on cherche quand on revient sur son téléphone.
    }
};
