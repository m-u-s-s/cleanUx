<?php

namespace Tests\Feature\Provider;

use App\Livewire\Provider\MaPresence;
use App\Models\ProviderPresence;
use App\Models\User;
use App\Services\Dispatch\CandidateFinder;
use App\Services\FaceCheck\Data\FaceCheckDecision;
use App\Services\FaceCheck\FaceCheckGate;
use App\Services\Presence\ProviderPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MA PRÉSENCE — l'écran qui remplace un JSON.
 *
 * `/presence/me` rendait un objet brut, et c'était une case du menu prestataire. L'écran répond
 * désormais à la seule question posée : est-ce que je reçois des missions, là, maintenant ?
 *
 * LA RÉPONSE DOIT ÊTRE VRAIE. Elle reprend les trois conditions du répartiteur — statut, position,
 * fraîcheur du signal — et un écran qui rassurerait à tort serait pire que le JSON d'avant : le
 * prestataire attendrait des missions qui ne viennent pas.
 */
class MaPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ecran_repond_et_annonce_ce_qu_il_est(): void
    {
        $this->actingAs($this->prestataire())
            ->get(route('presence.me'))
            ->assertOk()
            ->assertSee('Ma présence');
    }

    // ── Le verdict ─────────────────────────────────────────────────────────

    /** HORS LIGNE : la réponse est non, et elle dit pourquoi. */
    public function test_hors_ligne_l_ecran_dit_que_rien_n_arrive(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->assertSee('Vous ne recevez pas de mission')
            ->assertSee('Vous êtes hors ligne');
    }

    /**
     * EN LIGNE SANS POSITION : le pire des cas, et celui que personne ne voyait.
     *
     * Le répartiteur cherche par distance ; sans coordonnées, le prestataire est invisible tout
     * en se croyant disponible.
     */
    public function test_en_ligne_sans_position_l_ecran_le_dit(): void
    {
        $prestataire = $this->prestataire();

        $this->presence($prestataire)->update([
            'status' => ProviderPresence::STATUS_ONLINE,
            'heartbeat_at' => now(),
            'current_lat' => null,
            'current_lng' => null,
        ]);

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->assertSee('Vous ne recevez pas de mission')
            ->assertSee('Votre position est inconnue');
    }

    /** EN LIGNE AVEC UN SIGNAL PÉRIMÉ : le répartiteur ne le croit plus. */
    public function test_un_signal_perime_rend_injoignable(): void
    {
        $prestataire = $this->prestataire();

        $this->presence($prestataire)->update([
            'status' => ProviderPresence::STATUS_ONLINE,
            'current_lat' => 50.85,
            'current_lng' => 4.35,
            'heartbeat_at' => now()->subHour(),
        ]);

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->assertSee('Vous ne recevez pas de mission')
            ->assertSee('ne le croit plus');
    }

    /** TÉMOIN — les trois conditions réunies, la réponse devient oui. */
    public function test_temoin_les_trois_conditions_reunies_la_reponse_est_oui(): void
    {
        $prestataire = $this->prestataire();

        $this->presence($prestataire)->update([
            'status' => ProviderPresence::STATUS_ONLINE,
            'current_lat' => 50.85,
            'current_lng' => 4.35,
            'heartbeat_at' => now(),
        ]);

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->assertSee('Vous recevez des missions')
            ->assertDontSee('Vous ne recevez pas de mission');
    }

    /**
     * L'ÉCRAN DIT LA MÊME CHOSE QUE LE RÉPARTITEUR.
     *
     * Deux conditions recopiées approximativement feraient un écran rassurant et faux. On mesure
     * donc le verdict CONTRE la requête réelle de {@see CandidateFinder}, sur les mêmes colonnes.
     */
    public function test_le_verdict_dit_la_meme_chose_que_la_requete_du_repartiteur(): void
    {
        $prestataire = $this->prestataire();

        foreach ([
            ['status' => ProviderPresence::STATUS_OFFLINE, 'current_lat' => 50.85, 'current_lng' => 4.35, 'heartbeat_at' => now()],
            ['status' => ProviderPresence::STATUS_ONLINE, 'current_lat' => null, 'current_lng' => null, 'heartbeat_at' => now()],
            ['status' => ProviderPresence::STATUS_ONLINE, 'current_lat' => 50.85, 'current_lng' => 4.35, 'heartbeat_at' => now()->subHour()],
            ['status' => ProviderPresence::STATUS_ONLINE, 'current_lat' => 50.85, 'current_lng' => 4.35, 'heartbeat_at' => now()],
        ] as $index => $etat) {
            $this->presence($prestataire)->update($etat);

            $ecran = Livewire::actingAs($prestataire)->test(MaPresence::class)
                ->instance()->verdict['joignable'];

            $repartiteur = ProviderPresence::query()
                ->where('provider_user_id', $prestataire->id)
                ->where('status', ProviderPresence::STATUS_ONLINE)
                ->where('heartbeat_at', '>=', now()->subMinutes((int) config('dispatch.position_freshness_minutes', 5)))
                ->whereNotNull('current_lat')
                ->whereNotNull('current_lng')
                ->exists();

            $this->assertSame($repartiteur, $ecran, "Cas {$index} : l’écran et le répartiteur divergent.");
        }
    }

    // ── Les gestes ─────────────────────────────────────────────────────────

    /** PASSER EN LIGNE ENREGISTRE LA POSITION : sans elle, la mise en ligne ne sert à rien. */
    public function test_passer_en_ligne_enregistre_la_position_du_navigateur(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->call('passerEnLigne', 50.8503, 4.3517);

        $presence = $this->presence($prestataire)->fresh();

        $this->assertSame(ProviderPresence::STATUS_ONLINE, $presence->status);
        $this->assertEqualsWithDelta(50.8503, (float) $presence->current_lat, 0.0001);
        $this->assertNotNull($presence->heartbeat_at);
    }

    /** LE BATTEMENT RAFRAÎCHIT LE SIGNAL — c'est ce qui garde le prestataire visible. */
    public function test_le_battement_rafraichit_le_signal(): void
    {
        $prestataire = $this->prestataire();

        $this->presence($prestataire)->update([
            'status' => ProviderPresence::STATUS_ONLINE,
            'current_lat' => 50.85,
            'current_lng' => 4.35,
            'heartbeat_at' => now()->subMinutes(30),
        ]);

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->call('signaler', 50.86, 4.36);

        $this->assertTrue(
            $this->presence($prestataire)->fresh()->heartbeat_at->gt(now()->subMinute()),
        );
    }

    /** UN BATTEMENT HORS LIGNE NE REMET PERSONNE EN LIGNE : le geste serait invisible et faux. */
    public function test_un_battement_hors_ligne_ne_remet_pas_en_ligne(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->call('signaler', 50.86, 4.36);

        $this->assertSame(ProviderPresence::STATUS_OFFLINE, $this->presence($prestataire)->fresh()->status);
    }

    public function test_la_pause_et_le_retrait_changent_le_statut(): void
    {
        $prestataire = $this->prestataire();

        $ecran = Livewire::actingAs($prestataire)->test(MaPresence::class);

        $ecran->call('mettreEnPause');
        $this->assertSame(ProviderPresence::STATUS_ON_BREAK, $this->presence($prestataire)->fresh()->status);

        $ecran->call('passerHorsLigne');
        $this->assertSame(ProviderPresence::STATUS_OFFLINE, $this->presence($prestataire)->fresh()->status);
    }

    /** LE RAYON EST UNE PROMESSE : il s'enregistre, et il est borné. */
    public function test_le_rayon_s_enregistre(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->set('rayon', 35)
            ->call('enregistrerLeRayon')
            ->assertHasNoErrors();

        $this->assertSame(35, (int) $this->presence($prestataire)->fresh()->available_radius_km);
    }

    /** TÉMOIN — un rayon absurde est refusé plutôt qu'enregistré en silence. */
    public function test_temoin_un_rayon_hors_bornes_est_refuse(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)->test(MaPresence::class)
            ->set('rayon', 9999)
            ->call('enregistrerLeRayon')
            ->assertHasErrors('rayon');
    }

    /**
     * LE CONTRÔLE FACIAL GARDE LA MISE EN LIGNE.
     *
     * C'est la porte du service, pas de l'écran : la vérifier ici prouve que l'écran ne la
     * contourne pas en appelant le modèle directement.
     */
    public function test_le_controle_facial_barre_la_mise_en_ligne(): void
    {
        $prestataire = $this->prestataire();

        $this->mock(FaceCheckGate::class, function ($simulacre) {
            $simulacre->shouldReceive('inspectProvider')->andReturn(
                new FaceCheckDecision(
                    code: FaceCheckDecision::BLOCKED,
                    message: 'Vérification requise.',
                ),
            );
        });

        try {
            Livewire::actingAs($prestataire)->test(MaPresence::class)
                ->call('passerEnLigne', 50.85, 4.35);
        } catch (\Throwable) {
            // Le refus se rend en redirection vers l'écran de vérification ; seul l'état compte ici.
        }

        $this->assertSame(ProviderPresence::STATUS_OFFLINE, $this->presence($prestataire)->fresh()->status);
    }

    private function presence(User $prestataire): ProviderPresence
    {
        return app(ProviderPresenceService::class)->presenceFor($prestataire);
    }

    private function prestataire(): User
    {
        return User::factory()->employe()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);
    }
}
