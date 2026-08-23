<?php

namespace Tests\Feature\Catalogue;

use App\Models\Sector;
use App\Models\Trade;
use Database\Seeders\ReferencePlatformSeeder;
use Database\Seeders\TradeSectorLinkSeeder;
use Database\Seeders\TradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UN MÉTIER SANS SECTEUR EST INCOMMANDABLE — MÊME ENTIÈREMENT TARIFÉ.
 *
 * `OrderJourney::metiersDuSecteur()` liste par `where('sector_id', $this->sectorId)`. Un métier
 * dont `sector_id` est nul n'apparaît dans aucun écran de commande, et rien ne le signale : le
 * métier existe, il est actif, sa grille de prix est complète. Il est simplement invisible.
 *
 * Six des seize métiers semés étaient dans ce cas, dont `Garde d'enfants` et `Demenagement` —
 * deux verticales annoncées du produit, tarifées sur six zones chacune, et introuvables.
 *
 * ── POURQUOI CE TEST N'EXISTAIT PAS ──────────────────────────────────────────────────────────
 *
 * `TradeSeeder` crée douze métiers sans jamais poser de secteur ; les catalogues en rattachent six
 * plus tard, par slug. Chaque semeur pris isolément est correct. Le trou n'apparaît qu'à la
 * jointure des trois, et aucun test ne regardait le référentiel COMPLET une fois tout semé.
 */
class ChaqueMetierAppartientAUnSecteurTest extends TestCase
{
    use RefreshDatabase;

    /**
     * LA RÈGLE : une fois le référentiel semé, plus aucun métier actif n'est sans secteur.
     */
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

    /**
     * TÉMOIN POSITIF — le référentiel n'est pas vide, et il porte bien les six anciens orphelins.
     *
     * Sans ce contrôle, le test ci-dessus passerait au vert sur une base où le semeur aurait
     * échoué et n'aurait créé aucun métier du tout : zéro orphelin sur zéro métier.
     */
    public function test_temoin_les_six_anciens_orphelins_sont_rattaches(): void
    {
        $this->seed(ReferencePlatformSeeder::class);

        $this->assertGreaterThanOrEqual(16, Trade::query()->count());

        /*
         * ON COLLECTE, PUIS ON AFFIRME UNE FOIS.
         *
         * Une assertion par tour de boucle interrompt la méthode au PREMIER manque : si quatre des
         * six métiers étaient mal rattachés, la sortie n'en nommerait qu'un, et il faudrait quatre
         * exécutions pour les voir tous.
         */
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

    /**
     * LES DEUX SECTEURS CRÉÉS POUR L'OCCASION EXISTENT ET SONT PUBLIÉS.
     *
     * La garde d'enfants et le gardiennage n'entraient dans aucun des quatre secteurs d'origine.
     * Un secteur inactif ou non publié laisserait ses métiers aussi invisibles qu'avant.
     */
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

    /**
     * TÉMOIN NÉGATIF — SANS LE RATTACHEMENT, LE TROU EST BIEN LÀ.
     *
     * `TradeSeeder` seul laisse ses douze métiers sans secteur. Ce test mesure donc que la règle
     * ci-dessus interroge quelque chose de réel : sans lui, `test_aucun_metier_actif...` pourrait
     * passer au vert parce que le rattachement est trivialement vrai, et non parce qu'un semeur
     * l'assure.
     */
    public function test_le_semeur_de_metiers_seul_laisse_bien_des_orphelins(): void
    {
        $this->seed(TradeSeeder::class);

        $this->assertGreaterThan(
            0,
            Trade::query()->whereNull('sector_id')->count(),
            'Si `TradeSeeder` posait déjà les secteurs, `TradeSectorLinkSeeder` n’aurait rien à faire.',
        );

        $this->seed(TradeSectorLinkSeeder::class);

        /*
         * IL NE RATTACHE QUE CE QU'IL PEUT, ET N'INVENTE RIEN.
         *
         * Seuls `services-a-la-personne` et `securite` lui appartiennent : il les crée quel que
         * soit l'ordre des semeurs. Les quatre autres secteurs sont posés par les catalogues, et
         * tant qu'ils ne sont pas passés leurs métiers restent sans rattachement.
         *
         * C'est délibéré. Fabriquer ici un `batiment-renovation` ou un `mobilite` de secours
         * définirait un secteur à DEUX endroits — et le catalogue dépendrait alors de qui parle en
         * dernier. Mieux vaut un trou visible qu'une seconde source de vérité.
         */
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
