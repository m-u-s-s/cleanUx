<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QUAND UN E-MAIL PART, ET COMBIEN DE FOIS.
 *
 * Un gabarit dit CE QU'ON ÉCRIT ; une règle dit QUAND ça part. Les deux se séparent parce qu'un
 * même gabarit peut avoir plusieurs déclencheurs — un rappel à J-1 et un autre à H-2 sont deux
 * règles, pas deux gabarits.
 *
 * QUATRE PORTES. L'ÉVÉNEMENT branche la règle sur le moteur d'automatisation. La FRÉQUENCE la
 * fait partir à heure fixe. Le RAPPEL se cale sur un jalon, avant ou après. Le MANUEL n'attend
 * qu'une main.
 *
 * DEUX FREINS, et ils ne sont pas décoratifs. Le PLAFOND borne ce qu'un même destinataire reçoit
 * d'un même gabarit sur une fenêtre glissante — sans lui, une règle mal réglée transforme la
 * plateforme en source de courrier indésirable, et l'adresse d'expédition avec elle.
 * L'OPT-OUT s'applique au marketing et JAMAIS à une alerte de fraude : refuser une publicité
 * n'est pas renoncer à être prévenu qu'on vous vole.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_send_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();

            $table->string('name');
            $table->boolean('is_active')->default(false);

            // manual | event | schedule | reminder
            $table->string('trigger_type')->default('manual');

            // Pour `event` : la clé de l'événement. Pour `reminder` : le jalon de référence.
            $table->string('trigger_key')->nullable();

            // ── Rappel ───────────────────────────────────────────────────
            // Négatif = AVANT le jalon. Le signe porte l'intention : « −1440 » se lit « la veille ».
            $table->integer('offset_minutes')->default(0);

            // ── Fréquence ────────────────────────────────────────────────
            $table->string('frequency')->nullable();          // daily | weekly | monthly
            $table->unsignedTinyInteger('hour')->default(9);
            $table->unsignedTinyInteger('weekday')->nullable();   // 1 = lundi
            $table->unsignedTinyInteger('monthday')->nullable();

            // ── Freins ───────────────────────────────────────────────────
            // Zéro = pas de plafond. Toute autre valeur borne le même destinataire sur la fenêtre.
            $table->unsignedSmallInteger('cap_per_recipient')->default(0);
            $table->unsignedSmallInteger('cap_window_hours')->default(24);

            // Le marketing se refuse, une alerte de fraude ne se refuse pas.
            $table->boolean('respects_opt_out')->default(true);

            $table->timestamp('last_ran_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'trigger_type']);
            $table->index(['trigger_type', 'trigger_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_send_rules');
    }
};
