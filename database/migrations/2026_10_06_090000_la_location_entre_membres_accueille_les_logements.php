<?php

use App\Models\PeerVehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LA LOCATION ENTRE MEMBRES S'OUVRE AUX LOGEMENTS.
 *
 * Une voiture et un logement n'ont presque rien en commun — l'une a une boîte de vitesses,
 * l'autre des chambres. Mais LE CONTRAT DE LOCATION EST LE MÊME : empreinte bancaire, caution
 * prise puis libérée, commission, versement au propriétaire, remise et retour confirmés des deux
 * côtés, avis en révélation aveugle, litige arbitré.
 *
 * Ce chemin-là est celui de l'argent. L'écrire deux fois, c'est accepter qu'un défaut se corrige
 * à un seul des deux endroits. `peer_rentals` devient donc POLYMORPHE : elle porte n'importe quel
 * bien louable, et `peer_reviews` comme `peer_claims`, qui en dépendent déjà, sont partagés sans
 * qu'on y touche.
 *
 * Les INDISPONIBILITÉS deviennent polymorphes pour la même raison : un calendrier bloqué se lit
 * de la même façon, qu'il s'agisse d'un garage ou d'un studio.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. La location porte n'importe quel bien ────────────────────────
        Schema::table('peer_rentals', function (Blueprint $table) {
            $table->string('rentable_type')->nullable()->after('reference');
            $table->unsignedBigInteger('rentable_id')->nullable()->after('rentable_type');

            $table->index(['rentable_type', 'rentable_id'], 'ix_peer_rentals_rentable');
        });

        // LES LIGNES EXISTANTES REJOIGNENT LE NOUVEAU CHEMIN. `peer_vehicle_id` reste en place :
        // la retirer casserait le module vivant, et une colonne redondante coûte moins qu'une panne.
        //
        // LE NOM DE CLASSE COMPLET, comme partout ailleurs dans ce dépôt : aucune carte de
        // morphisme n'y est déclarée, et en introduire une pour deux modèles créerait une seconde
        // convention — deux façons de nommer la même chose.
        DB::table('peer_rentals')->whereNull('rentable_type')->update([
            'rentable_type' => PeerVehicle::class,
        ]);

        DB::statement('UPDATE peer_rentals SET rentable_id = peer_vehicle_id WHERE rentable_id IS NULL');

        // ── 2. Le calendrier se lit de la même façon pour tout bien ─────────
        Schema::table('peer_vehicle_availability', function (Blueprint $table) {
            $table->string('rentable_type')->nullable()->after('id');
            $table->unsignedBigInteger('rentable_id')->nullable()->after('rentable_type');

            $table->index(['rentable_type', 'rentable_id'], 'ix_peer_dispo_rentable');
        });

        DB::table('peer_vehicle_availability')->whereNull('rentable_type')->update([
            'rentable_type' => PeerVehicle::class,
        ]);

        DB::statement('UPDATE peer_vehicle_availability SET rentable_id = peer_vehicle_id WHERE rentable_id IS NULL');

        // ── 3. Le logement ─────────────────────────────────────────────────
        Schema::create('peer_stays', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // TOUT COMPTE ACTIF PUBLIE — client ou prestataire, particulier ou société — comme
            // pour les véhicules. L'organisation reste facultative : un particulier n'en a pas.
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_account_id')->nullable()
                ->constrained('organization_accounts')->nullOnDelete();

            // draft | pending_review | published | rejected | suspended
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // ── Le logement lui-même ───────────────────────────────────
            $table->string('title');
            $table->text('description')->nullable();
            // appartement | maison | studio | chambre | loft | autre
            $table->string('property_type')->default('appartement');
            // entire | private_room | shared_room — ce que le voyageur occupe vraiment.
            $table->string('space_type')->default('entire');

            $table->unsignedSmallInteger('max_guests')->default(2);
            $table->unsignedSmallInteger('bedrooms')->default(1);
            $table->unsignedSmallInteger('beds')->default(1);
            $table->decimal('bathrooms', 3, 1)->default(1);
            $table->unsignedInteger('surface_m2')->nullable();
            $table->json('amenities')->nullable();
            $table->text('house_rules')->nullable();

            // ── Prix ───────────────────────────────────────────────────
            // `nightly_price_cents` est le PRIX D'UNE NUIT. Le contrat partagé parle de « journée »
            // parce qu'une voiture se loue au jour ; pour un logement, la nuit EST la journée.
            $table->unsignedInteger('nightly_price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('cleaning_fee_cents')->default(0);
            $table->unsignedSmallInteger('guests_included')->default(1);
            $table->unsignedInteger('extra_guest_price_cents')->default(0);

            $table->unsignedTinyInteger('discount_3_days_percent')->default(0);
            $table->unsignedTinyInteger('discount_7_days_percent')->default(0);
            $table->unsignedTinyInteger('discount_28_days_percent')->default(0);
            $table->unsignedInteger('deposit_cents')->default(0);

            // ── Séjour ─────────────────────────────────────────────────
            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->unsignedSmallInteger('max_nights')->default(90);
            $table->time('check_in_from')->nullable();
            $table->time('check_out_before')->nullable();
            $table->boolean('instant_booking')->default(false);
            $table->string('cancellation_policy')->default('flexible');

            // ── Où ─────────────────────────────────────────────────────
            $table->string('address_line')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country_code', 2)->default('BE');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'city']);
            $table->index(['owner_id', 'status']);
            $table->index(['country_code', 'postal_code']);
        });

        // ── 4. Les photos du logement ──────────────────────────────────────
        Schema::create('peer_stay_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_stay_id')->constrained('peer_stays')->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            // LA COUVERTURE EST UNE POSITION, PAS UN DRAPEAU : deux « photos principales »
            // s'excluent mal, un ordre ne s'exclut jamais.
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['peer_stay_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_stay_media');
        Schema::dropIfExists('peer_stays');

        Schema::table('peer_vehicle_availability', function (Blueprint $table) {
            $table->dropIndex('ix_peer_dispo_rentable');
            $table->dropColumn(['rentable_type', 'rentable_id']);
        });

        Schema::table('peer_rentals', function (Blueprint $table) {
            $table->dropIndex('ix_peer_rentals_rentable');
            $table->dropColumn(['rentable_type', 'rentable_id']);
        });
    }
};
