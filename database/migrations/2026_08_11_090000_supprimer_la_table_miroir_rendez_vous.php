<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUPPRIMER `rendez_vous`, LA COPIE DE `bookings`.
 *
 * DEUX TABLES DÉCRIVAIENT LA MÊME RÉSERVATION. `bookings` en portait 143 colonnes ; `rendez_vous`
 * en recopiait 34, réécrites à chaque enregistrement par un crochet de modèle dont l'écriture était
 * enveloppée dans un `catch` muet. Un échec de recopie ne laissait donc AUCUNE trace : la copie
 * divergeait en silence, et la plateforme continuait de décider dessus. `SmartDispatchService` y
 * comptait les missions du jour d'un prestataire pour choisir à qui confier la suivante, et
 * `GestionUtilisateurs` y filtrait les comptes par zone. Une donnée dont on ignore si elle est à
 * jour est pire qu'une donnée absente : l'absence se voit.
 *
 * CE QUI A ÉTÉ VÉRIFIÉ AVANT DE SUPPRIMER, et qui rend le geste sûr :
 *
 *  - AUCUNE clé étrangère du schéma ne désigne `rendez_vous`. Rien ne s'y accroche.
 *  - Ses 34 colonnes sont un sous-ensemble strict de `bookings` : la copie n'a jamais rien porté
 *    que l'original n'ait déjà.
 *  - Chaque ligne du miroir existe dans `bookings` sous le même identifiant. Rien à sauver.
 *  - Plus aucun code ne la lit ni ne l'écrit : le crochet du modèle, celui du seeder, le modèle
 *    `RendezVous` lui-même et les deux dernières lectures (marge du tableau de bord, état de
 *    service) ont été rebranchés sur `bookings` ou retirés.
 *
 * LE RETOUR ARRIÈRE RECRÉE LA FORME, PAS L'HISTOIRE. Il rebâtit la table vide : comme le miroir
 * n'a jamais rien contenu d'unique, une recopie depuis `bookings` la reconstituerait à l'identique
 * si le besoin renaissait. Les index sont posés sur les colonnes qui les portaient.
 */
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
