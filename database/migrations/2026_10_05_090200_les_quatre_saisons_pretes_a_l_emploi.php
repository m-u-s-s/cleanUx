<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * QUATRE SAISONS POSÉES, AUCUNE ACTIVÉE.
 *
 * Elles arrivent prêtes — dates, couleurs, priorités — et INACTIVES : un thème qui s'allumerait
 * tout seul repeindrait les e-mails d'une plateforme sans que personne l'ait décidé. Une case à
 * cocher les met en service.
 *
 * LA RÉCURRENCE ANNUELLE N'EST PAS UNIFORME. Noël tombe au même jour chaque année : `recurs_yearly`
 * lui convient. Black Friday, Pâques et le nouvel an chinois SE DÉPLACENT — leurs dates valent pour
 * une année, et se reposent l'année suivante. Les traiter tous pareil produirait, en 2027, un
 * Black Friday le mauvais jour.
 *
 * Idempotente : elle n'écrit que ce qui manque.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->saisons() as $saison) {
            if (DB::table('email_themes')->where('code', $saison['code'])->exists()) {
                continue;
            }

            DB::table('email_themes')->insert($saison + [
                'is_default' => false,
                'is_active' => false,
                'font_stack' => 'Arial, Helvetica, sans-serif',
                'corner_radius' => 20,
                'footer_text' => 'Brio — plateforme de gestion des interventions.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('email_themes')
            ->whereIn('code', ['black-friday', 'noel', 'paques', 'nouvel-an-chinois'])
            ->delete();
    }

    /** @return list<array<string, mixed>> */
    private function saisons(): array
    {
        return [
            [
                'code' => 'black-friday',
                'name' => 'Black Friday',
                'description' => 'Noir profond, accent doré. Dates à reposer chaque année : elles se déplacent.',
                // 2026 : le vendredi 27 novembre.
                'starts_on' => '2026-11-24',
                'ends_on' => '2026-11-30',
                'recurs_yearly' => false,
                'priority' => 90,
                'color_accent' => '#f5c518',
                'color_accent_contrast' => '#0a0a0a',
                'color_page_background' => '#0a0a0a',
                'color_card_background' => '#141414',
                'color_text' => '#f5f5f5',
                'color_text_muted' => '#b3b3b3',
                'color_border' => '#2a2a2a',
                'color_banner_from' => '#000000',
                'color_banner_to' => '#1a1a1a',
            ],
            [
                'code' => 'noel',
                'name' => 'Noël',
                'description' => 'Sapin et or. Mêmes dates chaque année — la fenêtre franchit le passage d’année.',
                'starts_on' => '2026-12-15',
                'ends_on' => '2027-01-02',
                'recurs_yearly' => true,
                'priority' => 80,
                'color_accent' => '#c8102e',
                'color_accent_contrast' => '#ffffff',
                'color_page_background' => '#f3f6f3',
                'color_card_background' => '#ffffff',
                'color_text' => '#14281d',
                'color_text_muted' => '#456352',
                'color_border' => '#d6e2d8',
                'color_banner_from' => '#14532d',
                'color_banner_to' => '#166534',
            ],
            [
                'code' => 'paques',
                'name' => 'Pâques',
                'description' => 'Pastel et lumière. Date mobile : à reposer chaque année.',
                // 2027 : dimanche 28 mars.
                'starts_on' => '2027-03-22',
                'ends_on' => '2027-03-30',
                'recurs_yearly' => false,
                'priority' => 60,
                'color_accent' => '#e8a0bf',
                'color_accent_contrast' => '#3d2438',
                'color_page_background' => '#fdf7fb',
                'color_card_background' => '#ffffff',
                'color_text' => '#3d2438',
                'color_text_muted' => '#7a6072',
                'color_border' => '#f0dfe9',
                'color_banner_from' => '#7c9c6a',
                'color_banner_to' => '#a8c49a',
            ],
            [
                'code' => 'nouvel-an-chinois',
                'name' => 'Nouvel an chinois',
                'description' => 'Rouge et or. Date mobile suivant le calendrier lunaire : à reposer chaque année.',
                // 2027 : le 6 février.
                'starts_on' => '2027-02-01',
                'ends_on' => '2027-02-13',
                'recurs_yearly' => false,
                'priority' => 70,
                'color_accent' => '#ffcc33',
                'color_accent_contrast' => '#5c0a0a',
                'color_page_background' => '#fff5f5',
                'color_card_background' => '#ffffff',
                'color_text' => '#5c0a0a',
                'color_text_muted' => '#8a4141',
                'color_border' => '#f3d5d5',
                'color_banner_from' => '#8b0000',
                'color_banner_to' => '#c81d25',
            ],
        ];
    }
};
