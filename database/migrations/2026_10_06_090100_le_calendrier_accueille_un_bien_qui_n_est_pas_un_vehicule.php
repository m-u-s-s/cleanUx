<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LES TABLES PARTAGÉES CESSENT D'EXIGER UN VÉHICULE.
 *
 * `peer_rentals` et `peer_vehicle_availability` portent désormais n'importe quel bien louable,
 * par leurs colonnes polymorphes. Leur colonne `peer_vehicle_id` restait NOT NULL : un logement ne
 * pouvait donc entrer ni dans une location, ni dans un calendrier.
 *
 * ELLE RESTE EN PLACE, simplement facultative. Tout le module véhicules écrit par elle, et une
 * colonne redondante coûte moins cher qu'une réécriture de ce module.
 *
 * DETTE DE NOM ASSUMÉE : la table s'appelle encore `peer_vehicle_availability` alors qu'elle
 * accueille aussi des logements. La renommer toucherait le module vivant — sept routes, huit
 * services et leurs tests — pour un gain purement cosmétique. Le jour où ce module sera rouvert
 * pour une autre raison, ce sera le moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peer_vehicle_availability', function (Blueprint $table) {
            $table->unsignedBigInteger('peer_vehicle_id')->nullable()->change();
        });

        Schema::table('peer_rentals', function (Blueprint $table) {
            $table->unsignedBigInteger('peer_vehicle_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // LES LIGNES SANS VÉHICULE PARTENT D'ABORD : sans cela, remettre la contrainte échoue sur
        // les indisponibilités de logements, et la migration inverse laisse la base à mi-chemin.
        DB::table('peer_vehicle_availability')->whereNull('peer_vehicle_id')->delete();

        DB::table('peer_rentals')->whereNull('peer_vehicle_id')->delete();

        Schema::table('peer_vehicle_availability', function (Blueprint $table) {
            $table->unsignedBigInteger('peer_vehicle_id')->nullable(false)->change();
        });

        Schema::table('peer_rentals', function (Blueprint $table) {
            $table->unsignedBigInteger('peer_vehicle_id')->nullable(false)->change();
        });
    }
};
