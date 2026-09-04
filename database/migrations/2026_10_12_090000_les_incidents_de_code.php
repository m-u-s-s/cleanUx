<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES INCIDENTS DE CODE, GROUPÉS PAR EMPREINTE.
 *
 * Une même erreur qui frappe deux cents fois n'est PAS deux cents incidents : c'est un incident
 * vu deux cents fois. Un journal qui les empile ligne à ligne est illisible au bout d'une heure —
 * c'est pour ça que personne ne lit les journaux.
 *
 * L'EMPREINTE EST LE REGROUPEMENT : classe d'exception + fichier + ligne. Le message, lui, varie
 * (« utilisateur 42 introuvable », « utilisateur 77 introuvable ») et grouperait mal.
 *
 * CE QUI EST CONSERVÉ EST CE QUI SERT À DÉCIDER : combien de fois, depuis quand, sur quelle page,
 * combien de personnes touchées. Pas la trace d'appel complète — elle est énorme, et le fichier
 * plus la ligne suffisent à retrouver le code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_incidents', function (Blueprint $table) {
            $table->id();

            $table->string('fingerprint', 64)->unique();

            $table->string('exception_class');
            $table->text('message');
            $table->string('file');
            $table->unsignedInteger('line');

            // OÙ ÇA CASSE, pour savoir qui est touché.
            $table->string('route_name')->nullable();
            $table->string('path')->nullable();
            $table->string('method', 10)->nullable();

            // LA FAMILLE, telle que le classeur la reconnaît : schema | acces | donnee_absente…
            $table->string('famille', 40)->default('inconnue');

            $table->unsignedInteger('occurrences')->default(1);
            $table->unsignedInteger('utilisateurs_touches')->default(0);

            $table->timestamp('premiere_fois');
            $table->timestamp('derniere_fois');

            // ouvert | contenu | resolu | ignore
            $table->string('statut', 20)->default('ouvert');
            $table->text('note')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();

            $table->timestamps();

            $table->index(['statut', 'derniere_fois']);
            $table->index(['famille', 'occurrences']);
        });

        // LES COMPTES TOUCHÉS, une ligne par personne et par incident. Compter les occurrences
        // ne dit pas combien de gens ont vu l'erreur : cent fois une personne et une fois cent
        // personnes n'appellent pas la même réaction.
        Schema::create('code_incident_victims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('code_incident_id')->constrained('code_incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['code_incident_id', 'user_id'], 'ux_incident_victime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_incident_victims');
        Schema::dropIfExists('code_incidents');
    }
};
