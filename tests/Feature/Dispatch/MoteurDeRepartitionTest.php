<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Jobs\Dispatch\EscalateMissionAssignmentJob;
use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\MissionAssignment;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use App\Services\Dispatch\MissionDispatchService;
use App\Support\Domain\AsapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE MOTEUR DE RÉPARTITION, BOUT EN BOUT — consignes 1, 3, 4, 5, 6, 10.
 *
 * Le déroulé qu'on vérifie ici est celui des plateformes VTC, adapté :
 *
 *   offre séquentielle 20 s → refus ou silence → suivant du MÊME MÉTIER → vague épuisée →
 *   rayon élargi → rayon maximal → broadcast premier-accepte-gagne → échéance globale → sortie.
 *
 * Chaque test correspond à une façon dont la chaîne pouvait casser, et chacune a existé : deux
 * moteurs qui s'ignoraient, un job d'escalade qui rejouait sans garde, une acceptation qui
 * n'annulait pas les autres offres.
 */
class MoteurDeRepartitionTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    private ServiceZone $zone;

    private Trade $plomberie;

    private Trade $peinture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::create([
            'name' => 'Zone moteur', 'slug' => 'zone-moteur', 'code' => 'MOT',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->plomberie = Trade::create([
            'slug' => 'plomberie-moteur', 'code' => 'PLB-M', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_asap' => true,
        ]);

        $this->peinture = Trade::create([
            'slug' => 'peinture-moteur', 'code' => 'PNT-M', 'name' => 'Peinture',
            'is_active' => true, 'sort_order' => 2,
        ]);

        Config::set('dispatch.waves.initial_radius_m', 5000);
        Config::set('dispatch.waves.step_m', 5000);
        Config::set('dispatch.waves.max_radius_m', 20000);
        Config::set('dispatch.search_deadline_seconds', 300);
        Config::set('dispatch.default_timeout', 20);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function prestataire(Trade $trade, float $lat, float $lng): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $this->zone->id,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
            'current_lat' => $lat,
            'current_lng' => $lng,
        ]);

        ProviderPresence::create([
            'provider_user_id' => $user->id,
            'status' => 'online',
            'current_lat' => $lat,
            'current_lng' => $lng,
            'heartbeat_at' => now(),
        ]);

        $user->trades()->syncWithoutDetaching([$trade->id]);

        return $user;
    }

    private function reservationImmediate(?Trade $trade = null): Booking
    {
        return Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            /*
             * PERSONNE N'EST ENCORE DÉSIGNÉ, et c'est le point de départ de tout le moteur.
             *
             * La fabrique pose un `employe_id` par défaut ; laissé tel quel, la mission naîtrait
             * déjà `assigned` avec son chef, et la recherche n'aurait plus rien à chercher. C'est
             * exactement ce que le moteur remplace : l'assignation d'office silencieuse.
             */
            'employe_id' => null,
            'assigned_employee_id' => null,
            'service_zone_id' => $this->zone->id,
            'trade_id' => ($trade ?? $this->plomberie)->id,
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            'destination_lat' => self::LAT,
            'destination_lng' => self::LNG,
            'date' => now()->toDateString(),
            'heure' => now()->format('H:i'),
        ]);
    }

    private function moteur(): DispatchEngine
    {
        return app(DispatchEngine::class);
    }

    // ─── La chaîne séquentielle ──────────────────────────────────────────────────────────────

    #[Test]
    public function la_confirmation_ouvre_la_recherche_et_offre_au_plus_proche(): void
    {
        $loin = $this->prestataire($this->plomberie, 50.8700, 4.3900);   // ~4 km
        $proche = $this->prestataire($this->plomberie, 50.8480, 4.3540); // ~200 m

        $booking = $this->reservationImmediate();
        $search = $this->moteur()->openImmediate($booking);

        $this->assertNotNull($search);
        $this->assertSame(AsapStatus::SEARCHING, $search->status);
        $this->assertSame(5000, (int) $search->radius_m);

        $offres = MissionAssignment::query()->where('mission_id', $search->mission_id)->get();

        // UNE SEULE offre vivante : c'est le patron séquentiel. Prévenir tout le monde d'emblée
        // ferait vibrer dix téléphones pour une course qu'un seul obtiendra.
        $this->assertCount(1, $offres);
        $this->assertSame($proche->id, (int) $offres->first()->user_id);
        $this->assertNotSame($loin->id, (int) $offres->first()->user_id);
    }

    #[Test]
    public function l_offre_expire_en_vingt_secondes(): void
    {
        $this->prestataire($this->plomberie, 50.8480, 4.3540);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());
        $offre = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();

        $this->assertSame(
            20,
            (int) round($offre->expires_at->diffInSeconds($offre->assigned_at, true)),
            'Vingt secondes : la fenêtre des plateformes VTC, réglable par métier.',
        );
    }

    #[Test]
    public function un_refus_passe_au_suivant_du_meme_metier(): void
    {
        $premier = $this->prestataire($this->plomberie, 50.8480, 4.3540);
        $second = $this->prestataire($this->plomberie, 50.8600, 4.3700);
        $peintre = $this->prestataire($this->peinture, 50.8470, 4.3530);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());
        $offre = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();
        $this->assertSame($premier->id, (int) $offre->user_id);

        app(MissionDispatchService::class)->decline($offre, 'Trop loin');

        $suivante = MissionAssignment::query()
            ->where('mission_id', $search->mission_id)
            ->where('assignment_status', 'assigned')
            ->firstOrFail();

        $this->assertSame($second->id, (int) $suivante->user_id);
        $this->assertNotSame($peintre->id, (int) $suivante->user_id, 'TOUJOURS le même métier.');
    }

    #[Test]
    public function le_meme_prestataire_n_est_jamais_retente(): void
    {
        $seul = $this->prestataire($this->plomberie, 50.8480, 4.3540);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());
        $offre = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();

        app(MissionDispatchService::class)->decline($offre, 'Non');

        $vivantes = MissionAssignment::query()
            ->where('mission_id', $search->mission_id)
            ->where('assignment_status', 'assigned')
            ->count();

        $this->assertSame(0, $vivantes, 'Le seul candidat a refusé : on ne le rappelle pas.');
        $this->assertSame(AsapStatus::EXPIRED, $search->fresh()->status);
        $this->assertSame($seul->id, (int) $offre->fresh()->user_id);
    }

    #[Test]
    public function l_expiration_escalade_au_suivant(): void
    {
        $premier = $this->prestataire($this->plomberie, 50.8480, 4.3540);
        $second = $this->prestataire($this->plomberie, 50.8600, 4.3700);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());
        $offre = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();

        // Le serveur fait autorité : on force l'échéance plutôt que d'attendre vingt secondes.
        $offre->update(['expires_at' => now()->subSecond()]);

        app(MissionDispatchService::class)->expireAndEscalate($offre->fresh());

        $this->assertSame('expired', $offre->fresh()->assignment_status);

        $suivante = MissionAssignment::query()
            ->where('mission_id', $search->mission_id)
            ->where('assignment_status', 'assigned')
            ->firstOrFail();

        $this->assertSame($second->id, (int) $suivante->user_id);
        $this->assertNotSame($premier->id, (int) $suivante->user_id);
    }

    #[Test]
    public function le_job_d_escalade_est_idempotent(): void
    {
        $this->prestataire($this->plomberie, 50.8480, 4.3540);
        $this->prestataire($this->plomberie, 50.8600, 4.3700);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());
        $offre = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();
        $offre->update(['expires_at' => now()->subSecond()]);

        $job = new EscalateMissionAssignmentJob($offre->id);

        // Rejouer le job — redémarrage de file, doublon de message — ne doit pas produire une
        // deuxième escalade : sans garde, chaque rejeu brûlerait un candidat de plus.
        $job->handle(app(MissionDispatchService::class));
        $job->handle(app(MissionDispatchService::class));
        $job->handle(app(MissionDispatchService::class));

        $this->assertSame(
            2,
            MissionAssignment::query()->where('mission_id', $search->mission_id)->count(),
            'Une offre expirée, une offre suivante — et rien de plus.',
        );
    }

    #[Test]
    public function un_job_declenche_trop_tot_ne_fait_rien(): void
    {
        $this->prestataire($this->plomberie, 50.8480, 4.3540);
        $this->prestataire($this->plomberie, 50.8600, 4.3700);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());
        $offre = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();

        // L'offre a encore quinze secondes : le job ne doit pas la tuer.
        (new EscalateMissionAssignmentJob($offre->id))->handle(app(MissionDispatchService::class));

        $this->assertSame('assigned', $offre->fresh()->assignment_status);
        $this->assertSame(1, MissionAssignment::query()->where('mission_id', $search->mission_id)->count());
    }

    // ─── Les vagues ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function la_vague_epuisee_elargit_le_rayon(): void
    {
        // Personne dans les 5 km, quelqu'un à ~8 km : la première vague est vide, la deuxième non.
        $lointain = $this->prestataire($this->plomberie, 50.9150, 4.3525);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());

        $this->assertGreaterThan(5000, (int) $search->radius_m, 'Le rayon doit avoir grandi.');
        $this->assertGreaterThan(1, (int) $search->wave, 'La vague doit être numérotée.');

        $offre = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();
        $this->assertSame($lointain->id, (int) $offre->user_id);
    }

    #[Test]
    public function la_derniere_vague_diffuse_a_tout_le_monde(): void
    {
        // Trois prestataires au-delà de 15 km : la recherche atteint son plafond avant de les
        // trouver, et bascule donc en broadcast.
        $a = $this->prestataire($this->plomberie, 51.0000, 4.3525);
        $b = $this->prestataire($this->plomberie, 51.0010, 4.3530);
        $c = $this->prestataire($this->plomberie, 51.0020, 4.3535);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());

        $this->assertSame(20000, (int) $search->radius_m);
        $this->assertNotNull($search->broadcast_at, 'La dernière vague doit être marquée.');

        $offres = MissionAssignment::query()->where('mission_id', $search->mission_id)->pluck('user_id')
            ->map(fn ($id) => (int) $id)->all();

        $this->assertContains($a->id, $offres);
        $this->assertContains($b->id, $offres);
        $this->assertContains($c->id, $offres);
    }

    #[Test]
    public function deux_acceptations_simultanees_ne_font_qu_un_gagnant(): void
    {
        $a = $this->prestataire($this->plomberie, 51.0000, 4.3525);
        $b = $this->prestataire($this->plomberie, 51.0010, 4.3530);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());

        $offreA = MissionAssignment::query()->where('mission_id', $search->mission_id)
            ->where('user_id', $a->id)->firstOrFail();
        $offreB = MissionAssignment::query()->where('mission_id', $search->mission_id)
            ->where('user_id', $b->id)->firstOrFail();

        app(MissionDispatchService::class)->accept($offreA);

        // Le second reçoit un refus EXPLICITE, pas une erreur technique : « une erreur est
        // survenue » se lit comme un bug de l'application, pas comme une course déjà prise.
        $this->expectException(\DomainException::class);
        app(MissionDispatchService::class)->accept($offreB->fresh());
    }

    #[Test]
    public function l_acceptation_ferme_la_recherche_et_annule_les_autres_offres(): void
    {
        $a = $this->prestataire($this->plomberie, 51.0000, 4.3525);
        $this->prestataire($this->plomberie, 51.0010, 4.3530);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());
        $offreA = MissionAssignment::query()->where('mission_id', $search->mission_id)
            ->where('user_id', $a->id)->firstOrFail();

        app(MissionDispatchService::class)->accept($offreA);

        $this->assertSame(AsapStatus::ACCEPTED, $search->fresh()->status);
        $this->assertSame($a->id, (int) $search->fresh()->accepted_by_user_id);

        $this->assertSame(
            0,
            MissionAssignment::query()
                ->where('mission_id', $search->mission_id)
                ->where('assignment_status', 'assigned')
                ->count(),
            'Les modales des perdants doivent se fermer : plus aucune offre vivante.',
        );

        $booking = $search->fresh()->booking;
        $this->assertSame($a->id, (int) $booking->employe_id);
        $this->assertSame('confirme', $booking->status);
    }

    // ─── Aucun candidat ──────────────────────────────────────────────────────────────────────

    #[Test]
    public function sans_candidat_la_recherche_s_epuise_proprement(): void
    {
        $search = $this->moteur()->openImmediate($this->reservationImmediate());

        $this->assertNotNull($search);
        $this->assertSame(AsapStatus::EXPIRED, $search->status);
        $this->assertSame(0, MissionAssignment::query()->where('mission_id', $search->mission_id)->count());
    }

    #[Test]
    public function l_echeance_globale_arrete_la_recherche(): void
    {
        $this->prestataire($this->plomberie, 50.8480, 4.3540);

        $search = $this->moteur()->openImmediate($this->reservationImmediate());
        $offre = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();

        // Cinq minutes plus tard : l'échéance de RECHERCHE est passée. Le client doit se voir
        // proposer une suite, pas attendre indéfiniment.
        $search->update(['deadline_at' => now()->subMinute()]);
        $offre->update(['expires_at' => now()->subSecond()]);

        app(MissionDispatchService::class)->expireAndEscalate($offre->fresh());

        $this->assertSame(AsapStatus::EXPIRED, $search->fresh()->status);
    }

    // ─── Rejeu ───────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function rouvrir_ne_relance_pas_une_deuxieme_recherche(): void
    {
        $this->prestataire($this->plomberie, 50.8480, 4.3540);

        $booking = $this->reservationImmediate();

        $premiere = $this->moteur()->openImmediate($booking);
        $seconde = $this->moteur()->openImmediate($booking->fresh());

        $this->assertSame($premiere->id, $seconde->id);
        $this->assertSame(1, AsapDispatchRequest::query()->where('booking_id', $booking->id)->count());
    }

    // ─── Le planifié ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function le_planifie_propose_avant_d_assigner(): void
    {
        $prestataire = $this->prestataire($this->plomberie, 50.8480, 4.3540);

        $booking = $this->reservationImmediate();
        $booking->update(['booking_mode' => 'scheduled']);

        $offre = $this->moteur()->openScheduled($booking->fresh());

        $this->assertNotNull($offre);
        $this->assertSame($prestataire->id, (int) $offre->user_id);
        $this->assertSame('assigned', $offre->assignment_status);

        // TTL long : personne n'attend derrière la porte, et vingt secondes produiraient des refus
        // de gens simplement occupés.
        $this->assertGreaterThan(
            600,
            (int) round($offre->expires_at->diffInSeconds($offre->assigned_at, true)),
        );
    }

    #[Test]
    public function le_planifie_retombe_sur_l_assignation_d_office(): void
    {
        // Deux prestataires : le premier refuse, la chaîne atteint sa profondeur maximale, et le
        // rendez-vous doit malgré tout avoir quelqu'un — le second.
        $premier = $this->prestataire($this->plomberie, 50.8480, 4.3540);
        $prestataire = $this->prestataire($this->plomberie, 50.8600, 4.3700);

        $booking = $this->reservationImmediate();
        $booking->update(['booking_mode' => 'scheduled']);

        Config::set('dispatch.max_escalation_depth', 1);

        $offre = $this->moteur()->openScheduled($booking->fresh());
        $this->assertSame($premier->id, (int) $offre->user_id);

        app(MissionDispatchService::class)->decline($offre, 'Occupé');

        // La chaîne est épuisée : le rendez-vous doit malgré tout avoir quelqu'un à la date dite.
        // L'offre est la voie courtoise, l'assignation la garantie.
        $assignee = MissionAssignment::query()
            ->where('mission_id', $offre->mission_id)
            ->where('assignment_status', 'accepted')
            ->first();

        $this->assertNotNull($assignee, 'Le repli d’office doit avoir désigné quelqu’un.');
        $this->assertSame($prestataire->id, (int) $assignee->user_id);
    }
}
