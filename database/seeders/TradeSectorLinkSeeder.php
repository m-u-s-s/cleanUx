<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Database\Seeder;

/**
 * RATTACHE CHAQUE MÉTIER À SON SECTEUR — SANS QUOI IL EST INCOMMANDABLE.
 *
 * `OrderJourney` liste les métiers par `where('sector_id', $this->sectorId)`. Un métier dont
 * `sector_id` est nul n'apparaît dans AUCUN écran de commande : seul un lien direct par slug
 * l'atteint encore. Et rien ne le signale — le métier existe, il est actif, sa grille de prix est
 * complète. Il est simplement invisible.
 *
 * ── CE QUI MANQUAIT ──────────────────────────────────────────────────────────────────────────
 *
 * `TradeSeeder` crée douze métiers et ne pose jamais de secteur. Six recevaient le leur plus tard,
 * de `OrderEngineCatalogSeeder` et `CourseCatalogSeeder`, qui les reconnaissent par slug. Six
 * restaient orphelins avec six zones tarifées chacun :
 *
 *   Batiment / Gros oeuvre · Renovation · Levage / Lift · Demenagement
 *   Garde d'enfants · Securite / Gardiennage
 *
 * Deux d'entre eux — la garde d'enfants et le déménagement — sont des verticales annoncées du
 * produit. Elles étaient tarifées et introuvables.
 *
 * ── POURQUOI UN SEMEUR À PART, ET EN DERNIER ─────────────────────────────────────────────────
 *
 * Le rattachement ne peut pas vivre dans `TradeSeeder` : celui-ci s'exécute en TROISIÈME position
 * de `ReferencePlatformSeeder`, avant que le moindre secteur existe. Il n'aurait rien trouvé à
 * rattacher, et fabriquer les secteurs lui-même reviendrait à définir deux fois ce que
 * `OrderEngineCatalogSeeder` et `CourseCatalogSeeder` définissent déjà.
 *
 * Ce semeur passe donc APRÈS eux : chaque secteur est alors posé par son propriétaire, et il ne
 * reste ici qu'à combler les trous.
 *
 * ── DEUX SECTEURS QUE PERSONNE NE POSSÉDAIT ──────────────────────────────────────────────────
 *
 * La garde d'enfants et le gardiennage n'entrent dans aucun des quatre secteurs existants — ni
 * bâtiment, ni nettoyage, ni espaces verts, ni mobilité. Les y ranger de force donnerait un
 * catalogue FAUX plutôt qu'un catalogue incomplet. Ce fichier les crée donc, et c'est le seul
 * endroit où ils sont définis.
 */
class TradeSectorLinkSeeder extends Seeder
{
    /**
     * Slug du métier => slug du secteur.
     *
     * Les six premiers sont déjà rattachés par les catalogues au moment où ce semeur passe : ils
     * figurent ici pour que la carte soit LISIBLE EN ENTIER, et parce qu'un catalogue qui
     * changerait d'avis demain laisserait sinon un trou silencieux.
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
     * `sort_order` à 80 et 81 : après les trois premiers (0 à 2) et avant `mobilite`, qui s'était
     * déjà réservé le 90. Les icônes sont prises dans `<x-ui.icon>` — un nom inconnu y retombe
     * silencieusement sur un cercle.
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
            /*
             * `updateOrCreate` et non `create` : ce semeur est idempotent, et un exploitant qui
             * aurait renommé un de ces deux secteurs verra son intitulé rétabli — c'est le prix
             * d'un référentiel semé, et c'est le comportement des quatre autres.
             */
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

            /*
             * `forceFill` : `sector_id` n'est pas assignable en masse sur `Trade`, et c'est voulu —
             * une charge utile d'administration ne doit pas pouvoir déplacer un métier de secteur
             * par accident. Ici l'intention est explicite.
             */
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
