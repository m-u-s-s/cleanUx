<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** NOS LOCATIONS — LA PLATEFORME LOUE SES PROPRES VÉHICULES À SES CLIENTS. */
return new class extends Migration
{
    public function up(): void
    {
        // LES AGENCES DE RETRAIT.
        if (! Schema::hasTable('rental_pickup_points')) {
            Schema::create('rental_pickup_points', function (Blueprint $table) {
                $table->id();

                $table->string('name');
                $table->string('address');
                $table->string('postal_code', 16)->nullable();
                $table->string('city')->nullable();
                $table->string('country_code', 2)->default('BE');

                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();

                // Horaires d'ouverture et consignes d'accès : du texte libre par jour, que
                // l'administrateur remplit comme il l'entend. Les figer en colonnes obligerait a
                // une migration a chaque agence qui ouvre le samedi.
                $table->json('opening_hours')->nullable();
                $table->text('instructions')->nullable();

                $table->string('phone', 32)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);

                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'rental_pickup_actif_ordre_index');
            });
        }

        if (! Schema::hasTable('rental_vehicles')) {
            Schema::create('rental_vehicles', function (Blueprint $table) {
                $table->id();

                $table->string('code', 24)->unique();
                $table->string('plate', 16)->nullable();

                $table->string('brand');
                $table->string('model');
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('color')->nullable();

                // Les axes de tri du catalogue client. Chaines libres validees par la
                // configuration : ajouter « cabriolet » ne doit pas demander de migration.
                $table->string('category', 32)->index();
                $table->string('transmission', 16);
                $table->string('fuel', 16);

                $table->unsignedTinyInteger('seats')->default(5);
                $table->unsignedTinyInteger('doors')->default(5);
                $table->unsignedTinyInteger('luggage')->default(2);

                // Equipements coches par l'administrateur (climatisation, GPS, siege enfant...).
                $table->json('features')->nullable();

                // L'ARGENT EST EN CENTIMES, ENTIER, comme partout ailleurs dans ce depot.
                $table->unsignedInteger('daily_price_cents');
                $table->string('currency', 3)->default('EUR');

                // Sans garantie : caution pleine. Avec garantie : un supplement par jour, et une
                // caution reduite. Ce sont les deux chiffres que la confirmation doit montrer.
                $table->unsignedInteger('deposit_cents')->default(0);
                $table->unsignedInteger('waiver_daily_price_cents')->default(0);
                $table->unsignedInteger('waiver_deposit_cents')->default(0);

                $table->unsignedInteger('included_km_per_day')->nullable();
                $table->unsignedInteger('extra_km_price_cents')->default(0);

                $table->unsignedSmallInteger('min_rental_days')->default(1);
                $table->unsignedSmallInteger('max_rental_days')->nullable();

                // Les conditions de permis, exigees par toutes les agences et verifiees au
                // formulaire : age minimum du conducteur, anciennete du permis en annees.
                $table->unsignedTinyInteger('min_driver_age')->default(21);
                $table->unsignedTinyInteger('min_license_years')->default(1);

                $table->foreignId('pickup_point_id')->nullable()
                    ->constrained('rental_pickup_points')->nullOnDelete();

                // `is_active` DECIDE SEUL DE LA PRESENCE AU CATALOGUE.
                $table->boolean('is_active')->default(false);
                $table->unsignedInteger('sort_order')->default(0);

                $table->text('description')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'sort_order'], 'rental_vehicles_actif_ordre_index');
                $table->index(['category', 'transmission'], 'rental_vehicles_categorie_boite_index');
            });
        }

        if (! Schema::hasTable('rental_vehicle_media')) {
            Schema::create('rental_vehicle_media', function (Blueprint $table) {
                $table->id();

                $table->foreignId('rental_vehicle_id')->constrained()->cascadeOnDelete();

                // TROIS NATURES D'IMAGE, ET C'EST LE TYPE QUI LES SEPARE.
                $table->string('type', 16);
                $table->string('path');
                $table->unsignedSmallInteger('position')->default(0);
                $table->string('alt')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['rental_vehicle_id', 'type', 'position'], 'rental_media_vehicule_type_index');
            });
        }

        if (! Schema::hasTable('rental_bookings')) {
            Schema::create('rental_bookings', function (Blueprint $table) {
                $table->id();

                $table->string('reference', 24)->unique();

                $table->foreignId('rental_vehicle_id')->constrained()->restrictOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();

                // Le panier vit avant l'identite, comme dans le parcours de commande : on ne
                // demande pas de compte pour voir un prix.
                $table->string('session_token', 64)->nullable()->index();

                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->unsignedSmallInteger('days');

                $table->string('driver_first_name')->nullable();
                $table->string('driver_last_name')->nullable();
                $table->date('driver_birthdate')->nullable();
                $table->string('driver_email')->nullable();
                $table->string('driver_phone', 32)->nullable();

                $table->string('license_number')->nullable();
                $table->string('license_country', 2)->nullable();
                $table->date('license_issued_at')->nullable();

                // `none` ou `waiver` : sans ou avec garantie. Le client tranche au formulaire, et
                // la confirmation lui montre les deux totaux pour qu'il compare.
                $table->string('protection', 16)->default('none');

                // LE PRIX EST FIGE ICI, ET CE N'EST PAS UNE DUPLICATION.
                $table->unsignedInteger('daily_price_cents');
                $table->unsignedInteger('subtotal_cents');
                $table->unsignedInteger('waiver_total_cents')->default(0);
                $table->unsignedInteger('total_cents');
                $table->unsignedInteger('deposit_cents')->default(0);
                $table->string('currency', 3)->default('EUR');

                // L'adresse promise au client, copiee au moment de la reservation : l'agence peut
                // demenager, la promesse ne bouge pas.
                $table->string('pickup_label')->nullable();
                $table->string('pickup_address')->nullable();
                $table->decimal('pickup_lat', 10, 7)->nullable();
                $table->decimal('pickup_lng', 10, 7)->nullable();

                $table->string('status', 24)->default('draft');

                // L'empreinte bancaire, posee a la reservation ; le reglement se fait a l'agence.
                $table->string('stripe_payment_intent_id')->nullable()->index();
                $table->timestamp('imprint_authorized_at')->nullable();

                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                // L'INDEX QUI PORTE LA DISPONIBILITE. « Ce vehicule est-il libre du 3 au 7 ?
                $table->index(['rental_vehicle_id', 'status', 'starts_at', 'ends_at'], 'rental_bookings_dispo_index');
                $table->index(['status', 'starts_at'], 'rental_bookings_statut_debut_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_bookings');
        Schema::dropIfExists('rental_vehicle_media');
        Schema::dropIfExists('rental_vehicles');
        Schema::dropIfExists('rental_pickup_points');
    }
};
