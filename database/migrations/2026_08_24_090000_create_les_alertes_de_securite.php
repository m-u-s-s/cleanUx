<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LE MODE SÉCURITÉ / SOS (E33). CE QUI EXISTE ET CE QUI MANQUE. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('safety_alerts')) {
            Schema::create('safety_alerts', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('user_id');
                // La mission en cours, quand il y en a une. Nullable : le trajet vers le lieu et le
                // retour comptent autant, et une alerte refusée faute de mission serait absurde.
                $table->unsignedBigInteger('mission_id')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();

                // `check_in` | `emergency`
                $table->string('level', 20)->default('emergency');
                // `open` | `acknowledged` | `resolved` | `false_alarm`
                $table->string('status', 20)->default('open');

                $table->text('message')->nullable();

                // La position au DÉCLENCHEMENT — celle qui compte pour partir.
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->unsignedInteger('accuracy_m')->nullable();

                $table->unsignedBigInteger('acknowledged_by_user_id')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_note')->nullable();

                // Le contact d'urgence prévenu, et s'il l'a été. Une alerte qui ne prévient personne
                // hors de la plateforme laisse la personne seule si le support dort.
                $table->string('emergency_contact_name', 120)->nullable();
                $table->string('emergency_contact_phone', 40)->nullable();
                $table->timestamp('contact_notified_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                // Noms courts : MySQL refuse un index au-delà de 64 caractères.
                $table->index(['status', 'created_at'], 'safety_alerts_status_created_idx');
                $table->index(['user_id', 'status'], 'safety_alerts_user_status_idx');

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // LES POSITIONS SUIVIES PENDANT L'ALERTE.
        if (! Schema::hasTable('safety_alert_pings')) {
            Schema::create('safety_alert_pings', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('safety_alert_id');
                $table->decimal('lat', 10, 7);
                $table->decimal('lng', 10, 7);
                $table->unsignedInteger('accuracy_m')->nullable();
                $table->timestamp('pinged_at');

                $table->index(['safety_alert_id', 'pinged_at'], 'safety_pings_alert_time_idx');

                $table->foreign('safety_alert_id')->references('id')->on('safety_alerts')->cascadeOnDelete();
            });
        }

        // LE CONTACT D'URGENCE, sur le profil du prestataire.
        if (Schema::hasTable('provider_profiles') && ! Schema::hasColumn('provider_profiles', 'emergency_contact_name')) {
            Schema::table('provider_profiles', function (Blueprint $table) {
                $table->string('emergency_contact_name', 120)->nullable();
                $table->string('emergency_contact_phone', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_alert_pings');
        Schema::dropIfExists('safety_alerts');

        if (Schema::hasTable('provider_profiles') && Schema::hasColumn('provider_profiles', 'emergency_contact_name')) {
            Schema::table('provider_profiles', function (Blueprint $table) {
                $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone']);
            });
        }
    }
};
