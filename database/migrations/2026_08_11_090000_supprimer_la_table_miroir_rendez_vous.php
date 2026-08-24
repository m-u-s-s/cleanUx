<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SUPPRIMER `rendez_vous`, LA COPIE DE `bookings`. DEUX TABLES DÉCRIVAIENT LA MÊME RÉSERVATION. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rendez_vous');
    }

    public function down(): void
    {
        if (Schema::hasTable('rendez_vous')) {
            return;
        }

        Schema::create('rendez_vous', function (Blueprint $table) {
            // Pas d'auto-incrément : l'identifiant était celui de la réservation d'origine, jamais
            // une clé propre. C'est précisément ce qui faisait de cette table une copie.
            $table->unsignedBigInteger('id')->primary();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('employe_id')->nullable()->index();
            $table->unsignedBigInteger('service_catalog_id')->nullable()->index();
            $table->unsignedBigInteger('service_zone_id')->nullable()->index();
            $table->unsignedBigInteger('postal_code_id')->nullable()->index();

            $table->string('status')->index();
            $table->string('booking_reference')->nullable()->index();

            // Les paires françaises et anglaises coexistaient, tenues synchronisées par un trait
            // d'alias. La duplication de vocabulaire est reproduite telle quelle : un retour arrière
            // doit rendre la table que le code d'alors écrivait, pas une version assainie.
            $table->string('type_lieu')->nullable();
            $table->string('place_type')->nullable();
            $table->string('frequence')->nullable();
            $table->string('frequency')->nullable();
            $table->string('priorite')->nullable();
            $table->string('priority')->nullable();
            $table->string('adresse')->nullable();
            $table->string('address')->nullable();
            $table->string('ville')->nullable();
            $table->string('city')->nullable();
            $table->string('code_postal')->nullable();
            $table->string('postal_code')->nullable();

            $table->date('date')->nullable();
            $table->time('heure')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            $table->unsignedInteger('surface_m2')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('estimated_price', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2)->nullable();

            $table->json('metadata')->nullable();
            $table->json('zone_snapshot')->nullable();
            $table->json('pricing_snapshot')->nullable();

            $table->timestamps();
        });
    }
};
