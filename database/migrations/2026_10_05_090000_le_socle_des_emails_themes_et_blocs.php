<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE SOCLE DU STUDIO D'E-MAILS : UN THÈME, DES BLOCS.
 *
 * `email_templates` existait avec ses colonnes de rendu et ZÉRO ligne : le moteur
 * `EmailService::renderFromTemplate()` la lisait, et personne ne l'appelait. L'écran affichait
 * six gabarits écrits en dur dans un `match` PHP.
 *
 * Deux ajouts suffisent à ouvrir la porte. Les BLOCS remplacent le HTML brut : un document se
 * compose, se réordonne, et l'administrateur n'écrit jamais de balise. Le THÈME porte tout ce qui
 * ne relève pas du contenu — logo, couleurs, fond, typographie — et se choisit par FENÊTRE DE
 * DATES : Black Friday l'emporte du 24 au 30 novembre sans qu'un seul gabarit soit touché.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_themes', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();

            // LE THÈME PAR DÉFAUT EST LE SOCLE : il s'applique hors de toute fenêtre saisonnière.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // ── La saison ────────────────────────────────────────────────
            // Deux dates nulles = thème permanent. `recurs_yearly` ignore l'année : Noël se règle
            // une fois, Black Friday et Pâques se posent chaque année car ils se déplacent.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('recurs_yearly')->default(false);

            // À fenêtres qui se chevauchent, la priorité tranche.
            $table->unsignedSmallInteger('priority')->default(0);

            // ── La matière ───────────────────────────────────────────────
            $table->string('logo_url')->nullable();
            $table->string('header_image_url')->nullable();
            $table->string('background_image_url')->nullable();

            $table->string('color_accent', 32)->default('#ffb648');
            $table->string('color_accent_contrast', 32)->default('#0f172a');
            $table->string('color_page_background', 32)->default('#f8fafc');
            $table->string('color_card_background', 32)->default('#ffffff');
            $table->string('color_text', 32)->default('#0f172a');
            $table->string('color_text_muted', 32)->default('#475569');
            $table->string('color_border', 32)->default('#e2e8f0');
            $table->string('color_banner_from', 32)->default('#0f172a');
            $table->string('color_banner_to', 32)->default('#1e293b');

            $table->string('font_stack')->default('Arial, Helvetica, sans-serif');
            $table->unsignedSmallInteger('corner_radius')->default(20);
            $table->text('footer_text')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index(['starts_on', 'ends_on']);
        });

        Schema::table('email_templates', function (Blueprint $table) {
            // LE DOCUMENT EN BLOCS. `body_html_pattern` reste : c'est le rendu compilé, ce qui
            // permet aux gabarits déjà écrits en HTML de continuer à vivre sans blocs.
            $table->json('blocks')->nullable()->after('body_text_pattern');

            // Un thème IMPOSÉ pour ce gabarit ; sans lui, la saison décide.
            $table->foreignId('email_theme_id')->nullable()->after('blocks')
                ->constrained('email_themes')->nullOnDelete();

            // La ligne grise qu'affichent Gmail et Outlook sous l'objet.
            $table->string('preheader')->nullable()->after('subject_pattern');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_theme_id');
            $table->dropColumn(['blocks', 'preheader']);
        });

        Schema::dropIfExists('email_themes');
    }
};
