<?php

namespace Tests\Feature\Catalogue;

use App\Models\Sector;
use App\Models\Trade;
use Database\Seeders\ReferencePlatformSeeder;
use Database\Seeders\TradeSectorLinkSeeder;
use Database\Seeders\TradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UN MÉTIER SANS SECTEUR EST INCOMMANDABLE — MÊME ENTIÈREMENT TARIFÉ. */
class ChaqueMetierAppartientAUnSecteurTest extends TestCase
{
    use RefreshDatabase;

    /** LA RÈGLE : une fois le référentiel semé, plus aucun métier actif n'est sans secteur. */
    public function test_aucun_metier_actif_ne_reste_sans_secteur(): void
    {
        $this->seed(ReferencePlatformSeeder::class);

        $orphelins = Trade::query()
            ->whereNull('sector_id')
            ->where('is_active', true)
            ->pluck('slug')
            ->all();

        $this->assertSame([], $orphelins, 'Ces métiers actifs ne sont proposés sur aucun écran de commande.');
    }

    /** TÉMOIN POSITIF — le référentiel n'est pas vide, et il porte bien les six anciens orphelins. */
    public function test_temoin_les_six_anciens_orphelins_sont_rattaches(): void
    {
        $this->seed(ReferencePlatformSeeder::class);

        $this->assertGreaterThanOrEqual(16, Trade::query()->count());

        // ON COLLECTE, PUIS ON AFFIRME UNE FOIS.
        $ecarts = [];

        foreach ([
            'batiment' => 'batiment-renovation',
            'renovation' => 'batiment-renovation',
            'levage' => 'batiment-renovation',
            'moving' => 'mobilite',
            'childcare' => 'services-a-la-personne',
            'security' => 'securite',
        ] as $metier => $secteurAttendu) {
            $trade = Trade::query()->where('slug', $metier)->first();

            if ($trade === null) {
                $ecarts[] = "{$metier} → pas semé";

                continue;
            }

            $obtenu = $trade->sector_id
                ? Sector::query()->whereKey($trade->sector_id)->value('slug')
                : 'AUCUN SECTEUR';

            if ($obtenu !== $secteurAttendu) {
                $ecarts[] = "{$metier} → attendu `{$secteurAttendu}`, obtenu `{$obtenu}`";
            }
        }

        $this->assertSame([], $ecarts, 'Ces métiers ne sont pas dans le secteur attendu.');
    }

    /** LES DEUX SECTEURS CRÉÉS POUR L'OCCASION EXISTENT ET SONT PUBLIÉS. */
    public function test_les_deux_nouveaux_secteurs_sont_ouverts(): void
    {
        $this->seed(ReferencePlatformSeeder::class);

        $fermes = [];

        foreach (['services-a-la-personne', 'securite'] as $slug) {
            $secteur = Sector::query()->where('slug', $slug)->first();

            if ($secteur === null) {
                $fermes[] = "{$slug} → absent";
            } elseif (! $secteur->is_active) {
                $fermes[] = "{$slug} → inactif";
            } elseif ($secteur->published_at === null) {
                $fermes[] = "{$slug} → non publié";
            }
        }

        $this->assertSame([], $fermes, 'Ces secteurs laisseraient leurs métiers aussi invisibles qu’avant.');
    }

    /** TÉMOIN NÉGATIF — SANS LE RATTACHEMENT, LE TROU EST BIEN LÀ. */
    public function test_le_semeur_de_metiers_seul_laisse_bien_des_orphelins(): void
    {
        $this->seed(TradeSeeder::class);

        $this->assertGreaterThan(
            0,
            Trade::query()->whereNull('sector_id')->count(),
            'Si `TradeSeeder` posait déjà les secteurs, `TradeSectorLinkSeeder` n’aurait rien à faire.',
        );

        $this->seed(TradeSectorLinkSeeder::class);

        // IL NE RATTACHE QUE CE QU'IL PEUT, ET N'INVENTE RIEN.
        $restants = Trade::query()->whereNull('sector_id')->pluck('slug')->sort()->values()->all();

        $this->assertSame([
            'batiment', 'electrical', 'jardinage', 'levage', 'moving',
            'nettoyage', 'peinture', 'plumbing', 'renovation', 'roofing',
        ], $restants);

        // Mais les deux qu'il possède sont rattachés, seul et sans aide.
        $orphelins = collect(['childcare', 'security'])
            ->filter(fn (string $slug) => Trade::query()->where('slug', $slug)->value('sector_id') === null)
            ->values()
            ->all();

        $this->assertSame([], $orphelins, 'Ces métiers dépendent d’un secteur que ce semeur possède : il doit les rattacher seul.');
    }
}
