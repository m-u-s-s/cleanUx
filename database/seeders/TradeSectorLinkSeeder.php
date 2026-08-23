<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Database\Seeder;

/** RATTACHE CHAQUE MÉTIER À SON SECTEUR — SANS QUOI IL EST INCOMMANDABLE. */
class TradeSectorLinkSeeder extends Seeder
{
    /**
     * Slug du métier => slug du secteur.
     *
     * @var array<string, string>
     */
    private const SECTEUR_DE = [
        'nettoyage' => 'nettoyage',
        'peinture' => 'batiment-renovation',
        'plumbing' => 'batiment-renovation',
        'electrical' => 'batiment-renovation',
        'jardinage' => 'espaces-verts',
        'roofing' => 'batiment-renovation',

        // Les six qui n'étaient rattachés par personne.
        'batiment' => 'batiment-renovation',
        'renovation' => 'batiment-renovation',
        'levage' => 'batiment-renovation',
        'moving' => 'mobilite',
        'childcare' => 'services-a-la-personne',
        'security' => 'securite',
    ];

    /**
     * Les deux secteurs dont ce fichier est propriétaire.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SECTEURS_PROPRES = [
        'services-a-la-personne' => [
            'name' => 'Services à la personne',
            'tagline' => 'Garder, accompagner, soulager le quotidien',
            'icon' => 'users',
            'accent_color' => '#BE185D',
            'sort_order' => 80,
        ],
        'securite' => [
            'name' => 'Sécurité',
            'tagline' => 'Surveiller, protéger, sécuriser un site',
            'icon' => 'shield-check',
            'accent_color' => '#1D4ED8',
            'sort_order' => 81,
        ],
    ];

    public function run(): void
    {
        foreach (self::SECTEURS_PROPRES as $slug => $attributs) {
            // `updateOrCreate` et non `create` : ce semeur est idempotent, et un exploitant qui aurait renommé un de ces deux secteurs verra son intitulé rétabli — c'est le prix d'un référentiel semé, et c'est le comportement des quatre autres.
            Sector::updateOrCreate(
                ['slug' => $slug],
                $attributs + ['is_active' => true, 'published_at' => now()],
            );
        }

        $secteurs = Sector::query()->pluck('id', 'slug');
        $rattaches = 0;

        foreach (Trade::query()->whereNull('sector_id')->get() as $metier) {
            $slug = self::SECTEUR_DE[$metier->slug] ?? null;

            if ($slug === null || ! isset($secteurs[$slug])) {
                continue;
            }

            // `forceFill` : `sector_id` n'est pas assignable en masse sur `Trade`, et c'est voulu — une charge utile d'administration ne doit pas pouvoir déplacer un métier de secteur par accident.
            $metier->forceFill(['sector_id' => $secteurs[$slug]])->save();
            $rattaches++;
        }

        $orphelins = Trade::query()->whereNull('sector_id')->count();

        $this->command?->info(sprintf(
            '✅ Métiers rattachés à un secteur : %d. Restant sans secteur : %d.',
            $rattaches,
            $orphelins,
        ));
    }
}
