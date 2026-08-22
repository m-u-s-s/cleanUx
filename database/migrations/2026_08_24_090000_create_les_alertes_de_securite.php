<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE MODE SÉCURITÉ / SOS (E33).
 *
 * CE QUI EXISTE ET CE QUI MANQUE. Le centre de sécurité de l'administration traite les
 * SIGNALEMENTS — quelqu'un rapporte un comportement, un administrateur arbitre, des jours plus tard.
 * C'est un outil de modération. Il n'existe rien pour l'URGENCE : quelqu'un seul chez un inconnu,
 * qui a besoin qu'on sache où il est MAINTENANT.
 *
 * JAMAIS DERRIÈRE UN DRAPEAU. Un bouton d'urgence qu'on peut désactiver par configuration est un
 * bouton dont personne ne peut garantir qu'il répondra. C'est la seule fonctionnalité de ce
 * programme dont l'indisponibilité se compte en intégrité physique, et non en chiffre d'affaires.
 *
 * LA POSITION EST HORODATÉE ET CONSERVÉE, pas seulement la dernière connue. Une alerte se relit
 * après coup — par le support, par la personne elle-même, parfois par une autorité. Ne garder que
 * la position courante effacerait le trajet au moment où il compte le plus.
 *
 * DEUX NIVEAUX, ET C'EST ASSEZ. `check_in` — « je ne me sens pas à l'aise, gardez un œil » — et
 * `emergency` — « venez ». Les distinguer permet de ne pas noyer la seconde dans la première ; en
 * inventer six ferait hésiter au moment de choisir, c'est-à-dire au pire moment.
 */
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

        /*
         * LES POSITIONS SUIVIES PENDANT L'ALERTE.
         *
         * Une table séparée parce qu'elles sont NOMBREUSES et que l'alerte, elle, est unique : les
         * empiler dans un JSON sur la ligne d'alerte ferait grossir sans fin une ligne qu'on relit
         * en urgence, et rendrait impossible de retrouver « où était-il à 14 h 12 ».
         */
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

        /*
         * LE CONTACT D'URGENCE, sur le profil du prestataire.
         *
         * Il vit sur `provider_profiles` et non sur l'alerte : on le renseigne une fois, à froid.
         * Le demander au moment du déclenchement reviendrait à ne l'avoir jamais.
         */
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
