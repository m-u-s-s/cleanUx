<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * LA LOCATION ENTRE MEMBRES — un module NEUF, etranger a « Nos locations ».
 *
 * « Nos locations » (`rental_*`) loue la flotte DE LA PLATEFORME a ses clients : un seul
 * proprietaire, un parcours invite, une empreinte de caution. Ici, ce sont les membres qui
 * louent leur propre vehicule a d'autres membres : deux comptes, une commission, un paiement
 * bloque puis capture a la remise des cles. Rien n'est partage entre les deux, pas meme une
 * table — les confondre se paierait en donnees, pas en style.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();

            // Le proprietaire est un COMPTE, client ou prestataire. L'organisation n'est
            // renseignee que si le vehicule appartient a une societe.
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('organization_account_id')->nullable();

            $table->string('status', 24)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->string('brand', 60);
            $table->string('model', 60);
            $table->unsignedSmallInteger('year');
            $table->string('color', 30)->nullable();
            $table->string('plate', 20);
            $table->string('vin', 32)->nullable();

            $table->string('category', 30);
            $table->string('transmission', 20);
            $table->string('fuel', 20);
            $table->unsignedTinyInteger('seats')->default(5);
            $table->unsignedTinyInteger('doors')->default(5);
            $table->unsignedTinyInteger('luggage')->default(2);
            $table->json('features')->nullable();
            $table->text('description')->nullable();

            // LE PRIX. `pricing_rules` porte les multiplicateurs de saison et de week-end ;
            // les degressifs restent des colonnes, ils se lisent dans les filtres de recherche.
            $table->unsignedInteger('daily_price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->json('pricing_rules')->nullable();
            $table->unsignedTinyInteger('discount_3_days_percent')->default(0);
            $table->unsignedTinyInteger('discount_7_days_percent')->default(0);
            $table->unsignedTinyInteger('discount_28_days_percent')->default(0);

            $table->unsignedInteger('deposit_cents')->default(0);
            $table->unsignedSmallInteger('included_km_per_day')->default(200);
            $table->unsignedInteger('extra_km_price_cents')->default(0);

            $table->unsignedSmallInteger('min_rental_days')->default(1);
            $table->unsignedSmallInteger('max_rental_days')->default(30);
            $table->unsignedTinyInteger('min_driver_age')->default(21);
            $table->unsignedTinyInteger('min_license_years')->default(2);
            $table->boolean('instant_booking')->default(false);

            $table->string('address_line')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country_code', 2)->default('BE');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->boolean('delivery_enabled')->default(false);
            $table->unsignedSmallInteger('delivery_radius_km')->default(0);
            $table->unsignedInteger('delivery_price_cents')->default(0);

            // COQUILLE BRANCHABLE — aucun partenaire telematique n'est contractualise.
            $table->string('telematics_provider', 40)->nullable();
            $table->string('telematics_device_id', 80)->nullable();

            $table->string('cancellation_policy', 20)->default('moderee');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'city'], 'peer_vehicles_status_city_idx');
            $table->index(['owner_id', 'status'], 'peer_vehicles_owner_status_idx');
            $table->index(['lat', 'lng'], 'peer_vehicles_position_idx');
        });

        Schema::create('peer_vehicle_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_vehicle_id')->constrained('peer_vehicles')->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->string('sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['peer_vehicle_id', 'sort_order'], 'peer_media_vehicle_order_idx');
        });

        Schema::create('peer_vehicle_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_vehicle_id')->constrained('peer_vehicles')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            // `blocked` ferme le vehicule, `open` rouvre une periode dans une fermeture.
            $table->string('kind', 12)->default('blocked');
            $table->string('reason', 120)->nullable();
            $table->timestamps();

            $table->index(['peer_vehicle_id', 'starts_on', 'ends_on'], 'peer_dispo_vehicle_dates_idx');
        });

        Schema::create('peer_vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_vehicle_id')->constrained('peer_vehicles')->cascadeOnDelete();
            // carte grise, assurance, controle technique.
            $table->string('document_type', 40);
            $table->string('status', 24)->default('pending_review');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['peer_vehicle_id', 'document_type'], 'peer_docs_vehicle_type_idx');
            $table->index(['status', 'expires_at'], 'peer_docs_status_expiry_idx');
        });

        Schema::create('peer_rentals', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();

            $table->foreignId('peer_vehicle_id')->constrained('peer_vehicles')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 30)->default('pending_owner');

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('days');

            $table->boolean('delivery_requested')->default(false);
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();

            // L'INSTANTANE DE PRIX. Le bareme du vehicule peut changer apres la reservation :
            // ce qui a ete accepte est fige ici, et c'est lui qui fixe le montant preleve.
            $table->unsignedInteger('daily_price_cents');
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('delivery_cents')->default(0);
            $table->unsignedInteger('insurance_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->string('currency', 3)->default('EUR');

            $table->unsignedInteger('platform_fee_cents')->default(0);
            $table->unsignedInteger('owner_payout_cents')->default(0);
            $table->decimal('commission_rate', 5, 4)->default(0);

            $table->unsignedInteger('deposit_cents')->default(0);
            $table->unsignedSmallInteger('included_km')->default(0);
            $table->unsignedInteger('extra_km_price_cents')->default(0);
            $table->unsignedInteger('extra_charges_cents')->default(0);

            // L'ARGENT. Une autorisation Stripe expire au bout de sept jours : `payment_authorized_until`
            // dit quand elle tombe, et la re-autorisation avant la remise s'appuie dessus.
            $table->string('payment_status', 24)->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('payment_authorized_at')->nullable();
            $table->timestamp('payment_authorized_until')->nullable();
            $table->timestamp('payment_captured_at')->nullable();
            $table->unsignedTinyInteger('reauthorized_count')->default(0);

            $table->string('deposit_payment_intent_id')->nullable();
            $table->timestamp('deposit_authorized_at')->nullable();
            $table->unsignedInteger('deposit_captured_cents')->default(0);
            $table->timestamp('deposit_released_at')->nullable();

            // LA REMISE ET LE RETOUR EXIGENT LES DEUX SIGNATURES. Une seule ne declenche rien.
            $table->timestamp('handover_owner_confirmed_at')->nullable();
            $table->timestamp('handover_renter_confirmed_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('return_owner_confirmed_at')->nullable();
            $table->timestamp('return_renter_confirmed_at')->nullable();
            $table->timestamp('returned_at')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->unsignedInteger('cancellation_fee_cents')->default(0);
            $table->text('cancellation_reason')->nullable();

            $table->string('insurance_plan_key', 60)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['peer_vehicle_id', 'starts_at', 'ends_at'], 'peer_rentals_vehicle_dates_idx');
            $table->index(['renter_id', 'status'], 'peer_rentals_renter_status_idx');
            $table->index(['owner_id', 'status'], 'peer_rentals_owner_status_idx');
            $table->index(['payment_status', 'payment_authorized_until'], 'peer_rentals_paiement_idx');
        });

        Schema::create('peer_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_rental_id')->constrained('peer_rentals')->cascadeOnDelete();
            // `handover` a la remise des cles, `return` au retour.
            $table->string('phase', 12);
            // Le code n'est JAMAIS stocke en clair : seul son empreinte l'est.
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['peer_rental_id', 'phase'], 'peer_codes_rental_phase_idx');
        });

        Schema::create('peer_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_rental_id')->constrained('peer_rentals')->cascadeOnDelete();
            // `departure` a la remise, `return` au retour.
            $table->string('phase', 12);
            $table->unsignedInteger('mileage_km')->nullable();
            // Huitiemes de reservoir, comme sur un contrat de location papier.
            $table->unsignedTinyInteger('fuel_eighths')->nullable();
            $table->string('cleanliness', 20)->nullable();
            $table->boolean('license_verified')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('owner_signed_at')->nullable();
            $table->timestamp('renter_signed_at')->nullable();
            $table->timestamps();

            $table->unique(['peer_rental_id', 'phase'], 'peer_inspections_rental_phase_uq');
        });

        Schema::create('peer_inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_inspection_id')->constrained('peer_inspections')->cascadeOnDelete();
            $table->string('path');
            // face, arriere, gauche, droite, tableau de bord, interieur, dommage.
            $table->string('angle', 30);
            $table->string('caption')->nullable();
            // La preuve horodatee : empreinte du fichier et position, comme sur les missions.
            $table->string('sha256', 64)->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['peer_inspection_id', 'angle'], 'peer_photos_inspection_idx');
        });

        Schema::create('peer_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_rental_id')->constrained('peer_rentals')->cascadeOnDelete();
            $table->unsignedBigInteger('opened_by');
            // dommage, retard, carburant, kilometrage, nettoyage.
            $table->string('kind', 30);
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('status', 24)->default('open');
            $table->text('description')->nullable();
            $table->json('evidence')->nullable();
            $table->unsignedInteger('deposit_captured_cents')->default(0);
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['peer_rental_id', 'status'], 'peer_claims_rental_status_idx');
        });

        Schema::create('peer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peer_rental_id')->constrained('peer_rentals')->cascadeOnDelete();
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('target_id');
            // `owner` note le locataire, `renter` note le proprietaire et le vehicule.
            $table->string('author_role', 12);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            // REVELATION A L'AVEUGLE : les deux avis n'apparaissent qu'une fois les deux
            // deposes, ou passe le delai. Sans quoi le second se calque sur le premier.
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();

            $table->unique(['peer_rental_id', 'author_id'], 'peer_reviews_rental_author_uq');
            $table->index(['target_id', 'revealed_at'], 'peer_reviews_target_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'peer_reviews',
            'peer_claims',
            'peer_inspection_photos',
            'peer_inspections',
            'peer_codes',
            'peer_rentals',
            'peer_vehicle_documents',
            'peer_vehicle_availability',
            'peer_vehicle_media',
            'peer_vehicles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
