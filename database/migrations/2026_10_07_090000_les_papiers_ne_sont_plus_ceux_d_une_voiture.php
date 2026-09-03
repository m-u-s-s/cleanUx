<?php

use App\Models\PeerVehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LES PAPIERS SUIVENT LE BIEN, PAS LA VOITURE.
 *
 * Un logement se justifie comme un véhicule : une attestation d'assurance, un titre qui prouve
 * le droit de le louer, parfois un numéro d'enregistrement communal. La FILE D'ATTENTE est la
 * même — un administrateur ouvre un fichier, le valide ou le refuse avec un motif.
 *
 * `peer_vehicle_documents` devient donc polymorphe, comme `peer_rentals` et le calendrier avant
 * elle. Le nom de la table reste : la renommer imposerait de toucher tout le module véhicules
 * vivant, pour un gain purement cosmétique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peer_vehicle_documents', function (Blueprint $table) {
            $table->string('documentable_type')->nullable()->after('id');
            $table->unsignedBigInteger('documentable_id')->nullable()->after('documentable_type');

            $table->index(['documentable_type', 'documentable_id'], 'ix_peer_docs_documentable');
        });

        // LE NOM DE CLASSE COMPLET, comme partout ailleurs : aucune carte de morphisme n'est
        // déclarée dans ce dépôt.
        DB::table('peer_vehicle_documents')->whereNull('documentable_type')->update([
            'documentable_type' => PeerVehicle::class,
        ]);

        DB::statement('UPDATE peer_vehicle_documents SET documentable_id = peer_vehicle_id WHERE documentable_id IS NULL');

        // UN PAPIER DE LOGEMENT N'A PAS DE VEHICULE. La colonne reste pour le module vivant qui
        // l'écrit encore, mais elle ne peut plus être obligatoire.
        Schema::table('peer_vehicle_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('peer_vehicle_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // LES PAPIERS SANS VEHICULE PARTENT D'ABORD : sans cela, la colonne ne peut pas
        // redevenir obligatoire.
        DB::table('peer_vehicle_documents')->whereNull('peer_vehicle_id')->delete();

        Schema::table('peer_vehicle_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('peer_vehicle_id')->nullable(false)->change();
            $table->dropIndex('ix_peer_docs_documentable');
            $table->dropColumn(['documentable_type', 'documentable_id']);
        });
    }
};
