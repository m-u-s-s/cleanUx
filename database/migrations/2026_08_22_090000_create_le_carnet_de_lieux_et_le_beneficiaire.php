<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE CARNET DE LIEUX D'UN CLIENT (E2), ET LE BÉNÉFICIAIRE D'UNE COMMANDE (E1).
 *
 * E2 — UN CLIENT A PLUSIEURS LIEUX, et la plateforme n'en connaissait qu'un : `customer_profiles`
 * porte UNE adresse par défaut. Quelqu'un qui fait nettoyer son appartement et la maison de sa mère
 * retape l'adresse, l'étage et le code à chaque commande — et se trompe une fois sur cinq, ce qui
 * envoie un prestataire à la mauvaise porte.
 *
 * CE QUI COMPTE N'EST PAS L'ADRESSE, ce sont les CONSIGNES qui l'accompagnent. L'étage, le digicode,
 * la clé chez la voisine, le chien qui aboie, l'allergie aux produits chlorés : ces informations se
 * redonnent oralement à chaque nouveau prestataire, ou se perdent. Elles vivent donc ici, et la
 * fiche d'accès sur place (F5) les lit — c'est ce lien qui fait la différence entre un carnet
 * d'adresses et un vrai carnet de lieux.
 *
 * LES CONSIGNES D'ACCÈS SONT DES CLÉS DE DOMICILE. Elles ne se révèlent au prestataire qu'à
 * l'arrivée confirmée sur place, exactement comme celles d'un site d'entreprise : c'est
 * `MissionAccessSheetService` qui garde cette porte, et rien ici ne l'affaiblit.
 *
 * E1 — RÉSERVER POUR UN PROCHE. Le client paye, quelqu'un d'autre reçoit. Aujourd'hui ce cas se
 * bricole dans le commentaire libre : le prestataire arrive en demandant M. Dupont et trouve sa
 * mère, qui n'attendait personne. Trois colonnes ADDITIVES sur le panier et sur la réservation
 * suffisent — et le suivi partagé (E3) s'adresse alors à la bonne personne.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_places')) {
            Schema::create('client_places', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('user_id');

                // « Chez moi », « Maison de maman », « Bureau ». C'est ce que le client reconnaît —
                // pas une adresse qu'il faut relire pour identifier.
                $table->string('label', 80);

                $table->string('address', 255);
                $table->string('city', 120)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('country', 2)->nullable();

                // La géographie RÉSOLUE : c'est elle qui donne au prix sa grille de zone et au
                // dispatch un point de départ, au lieu d'une adresse à redeviner.
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->unsignedBigInteger('service_zone_id')->nullable();

                // Ce qui fait la différence avec un simple carnet d'adresses.
                $table->string('floor', 60)->nullable();
                $table->text('access_instructions')->nullable();
                $table->boolean('alarm_code_required')->default(false);
                $table->string('access_start_time', 5)->nullable();
                $table->string('access_end_time', 5)->nullable();

                /*
                 * PRODUITS, ALLERGIES, ANIMAUX. En JSON parce que la liste s'allonge avec les
                 * métiers : une colonne par préférence obligerait à migrer la table chaque fois
                 * qu'un métier arrive.
                 */
                $table->json('preferences')->nullable();

                $table->boolean('is_default')->default(false);
                // Archivé, jamais supprimé : les missions passées portent ce lieu.
                $table->timestamp('archived_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                // Nom court : MySQL refuse un index au-delà de 64 caractères.
                $table->index(['user_id', 'archived_at'], 'client_places_user_archived_idx');

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        /*
         * LE BÉNÉFICIAIRE — trois colonnes ADDITIVES, sur les deux tables du parcours.
         *
         * Sur le panier ET sur la réservation : le panier parce que l'information se saisit là, la
         * réservation parce qu'elle doit survivre à la conversion — un bénéficiaire qui ne
         * franchirait pas la confirmation ne servirait à personne.
         */
        foreach (['order_drafts', 'bookings'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'beneficiary_name')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('beneficiary_name', 120)->nullable();
                $blueprint->string('beneficiary_phone', 40)->nullable();
                $blueprint->text('beneficiary_note')->nullable();
            });
        }

        // Le lieu retenu pour une commande, quand il vient du carnet.
        if (Schema::hasTable('order_drafts') && ! Schema::hasColumn('order_drafts', 'client_place_id')) {
            Schema::table('order_drafts', function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('client_place_id')->nullable();
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'client_place_id')) {
            Schema::table('bookings', function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('client_place_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['order_drafts', 'bookings'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $colonnes = array_values(array_filter(
                ['beneficiary_name', 'beneficiary_phone', 'beneficiary_note', 'client_place_id'],
                fn (string $colonne) => Schema::hasColumn($table, $colonne),
            ));

            if ($colonnes !== []) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($colonnes));
            }
        }

        Schema::dropIfExists('client_places');
    }
};
