<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employe_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('date');
            $table->time('heure');

            $table->string('motif')->nullable();

            $table->string('status', 50)->default('en_attente');
            $table->integer('duree')->default(90);
            $table->integer('duree_estimee')->nullable();
            $table->decimal('devis_estime', 10, 2)->nullable();

            $table->string('service_type')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('code_postal', 20)->nullable();
            $table->string('type_lieu')->nullable();
            $table->string('surface')->nullable();
            $table->string('frequence')->nullable();
            $table->boolean('is_recurrent')->default(false);
            $table->string('recurrence_rule')->nullable();
            $table->boolean('is_favorite_slot')->default(false);

            $table->text('commentaire_client')->nullable();
            $table->string('telephone_client', 30)->nullable();
            $table->boolean('presence_animaux')->default(false);
            $table->boolean('acces_parking')->default(false);
            $table->boolean('materiel_fournit')->default(false);
            $table->string('priorite')->nullable();

            $table->json('photos_reference')->nullable();

            $table->json('options_prestation')->nullable();
            $table->json('zones_specifiques')->nullable();
            $table->json('materiel_specifique')->nullable();

            $table->text('commentaire_fin_mission')->nullable();
            $table->integer('duree_reelle')->nullable();
            $table->json('photos_apres')->nullable();

            $table->timestamp('mission_started_at')->nullable();
            $table->timestamp('mission_finished_at')->nullable();

            $table->timestamp('rappel_24h_envoye_at')->nullable();
            $table->timestamp('rappel_2h_envoye_at')->nullable();
            $table->timestamp('feedback_demande_envoye_at')->nullable();
            $table->timestamp('alerte_urgence_envoyee_at')->nullable();

            $table->timestamps();

            $table->index(['date', 'heure']);
            $table->index('status');
            $table->index('priorite');
            $table->index('service_type');
            $table->index('ville');
            $table->index(['employe_id', 'date']);
            $table->index(['client_id', 'date']);
            $table->index('devis_estime');
            $table->index('is_recurrent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};
