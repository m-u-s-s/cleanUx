<?php

namespace Tests\Feature\Missions\OnSite;

use App\Models\MissionExtra;
use App\Services\Missions\OnSite\MissionExtraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * LE SUPPLÉMENT ACCEPTÉ DOIT ÊTRE ENCAISSÉ — ou rester une créance, jamais les deux.
 *
 * Le défaut réparé ici : `prelever()` créait une intention `confirm: false`, sans moyen de
 * paiement et sans `off_session`. Aucun euro ne bougeait — puis l'extra était marqué `charged`
 * quoi qu'il arrive. Tout supplément accepté depuis la mise en service de ce mécanisme était
 * enregistré comme encaissé sans l'être, et rien ne pouvait le rattraper : ni la comptabilité, ni
 * le portefeuille du prestataire, ni le webhook, qui ne connaît pas `mission_extra_id`.
 *
 * Ces tests ne parlent pas à Stripe. Ils vérifient la seule chose qui compte et qui ne dépend pas
 * du réseau : **un extra n'est déclaré encaissé que si l'encaissement a réussi**.
 */
class PrelevementDesSupplementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_prelevement_impossible_laisse_la_creance_ouverte(): void
    {
        $extra = $this->extraApprouve();

        // Ni compte Connect utilisable, ni client Stripe : le prélèvement ne peut pas partir.
        app(MissionExtraService::class)->reprendreLePrelevement($extra);

        $extra->refresh();

        $this->assertSame(
            MissionExtra::STATUS_APPROVED,
            $extra->status,
            'Un prélèvement impossible ne doit JAMAIS produire un « encaissé ».',
        );
        $this->assertNull($extra->charged_at);
    }

    /**
     * LA TRACE DE L'ÉCHEC — sans elle, un administrateur voyant un extra `approved` depuis trois
     * jours n'a aucun moyen de savoir si le prélèvement a seulement été tenté.
     */
    public function test_lechec_est_date_et_motive(): void
    {
        $extra = $this->extraApprouve();

        app(MissionExtraService::class)->reprendreLePrelevement($extra);

        $metadata = (array) $extra->refresh()->metadata;

        $this->assertArrayHasKey('derniere_tentative_de_prelevement', $metadata);
        $this->assertArrayHasKey('motif_du_dernier_echec', $metadata);
        $this->assertNotSame('', (string) $metadata['motif_du_dernier_echec']);
    }

    /**
     * LA REPRISE NE TOUCHE QUE LES CRÉANCES.
     *
     * Rejouer un `proposed` facturerait quelqu'un qui n'a rien accepté ; rejouer un `declined`
     * facturerait un refus. C'est le genre d'erreur qui ne se voit qu'au relevé bancaire.
     */
    public function test_la_reprise_ignore_ce_qui_nest_pas_accepte(): void
    {
        // Les trois statuts releves ensemble : une garde de rejeu trop laxiste laisse passer
        // PLUSIEURS statuts, et chacun est un prelevement effectue deux fois sur un client.
        $rejoues = [];

        foreach ([MissionExtra::STATUS_PROPOSED, MissionExtra::STATUS_DECLINED, MissionExtra::STATUS_CHARGED] as $statut) {
            $extra = $this->extraApprouve();
            $extra->forceFill(['status' => $statut])->save();

            app(MissionExtraService::class)->reprendreLePrelevement($extra);

            $apres = $extra->refresh()->status;

            if ($apres !== $statut) {
                $rejoues[] = "« {$statut} » est devenu « {$apres} »";
            }
        }

        $this->assertSame([], $rejoues, 'Ces extras ont ete rejoues : le client serait preleve deux fois.');
    }

    public function test_la_commande_de_reprise_compte_les_creances(): void
    {
        $this->extraApprouve()->forceFill(['approved_at' => now()->subDay()])->save();

        $this->artisan('extras:reprendre-les-prelevements')
            ->expectsOutputToContain('Suppléments en souffrance : 1')
            ->assertSuccessful();
    }

    /**
     * TÉMOIN : sans créance, la commande ne fabrique rien.
     */
    public function test_sans_creance_la_commande_ne_fait_rien(): void
    {
        $this->artisan('extras:reprendre-les-prelevements')
            ->expectsOutputToContain('Suppléments en souffrance : 0')
            ->assertSuccessful();
    }

    /**
     * On laisse retomber un échec passager avant de réessayer : une créance acceptée il y a dix
     * secondes n'est pas encore en souffrance, elle est en cours.
     */
    public function test_une_creance_toute_fraiche_nest_pas_reprise(): void
    {
        $this->extraApprouve()->forceFill(['approved_at' => now()])->save();

        $this->artisan('extras:reprendre-les-prelevements')
            ->expectsOutputToContain('Suppléments en souffrance : 0')
            ->assertSuccessful();
    }

    /**
     * Une carte refusée trois fois ne deviendra pas valide à la quatrième, et chaque tentative
     * laisse une trace chez Stripe. Au-delà, l'affaire appartient à un humain.
     */
    public function test_au_dela_du_plafond_le_dossier_passe_a_un_humain(): void
    {
        $extra = $this->extraApprouve();
        $extra->forceFill([
            'approved_at' => now()->subDay(),
            'metadata' => ['tentatives_de_prelevement' => 3],
        ])->save();

        $this->artisan('extras:reprendre-les-prelevements --tentatives=3')
            ->expectsOutputToContain('à traiter à la main : 1')
            ->assertSuccessful();
    }

    // ─────────────────────────────────────────────────────────────────────

    private function extraApprouve(): MissionExtra
    {
        // `make()` construit, `build()` peuple : sans le second, les propriétés typées du scénario
        // ne sont jamais initialisées.
        $scenario = SpineScenario::make()->build();

        return MissionExtra::create([
            'mission_id' => $scenario->mission->id,
            'proposed_by_user_id' => $scenario->provider->id,
            'label' => 'Produits fournis',
            'price_cents' => 1500,
            'currency' => 'EUR',
            'status' => MissionExtra::STATUS_APPROVED,
            'approved_at' => now()->subDay(),
        ]);
    }
}
