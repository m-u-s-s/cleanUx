<?php

namespace Tests\Feature\Dispatch;

use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\MissionAssignment;
use App\Models\ProviderPresence;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use App\Services\Dispatch\MissionDispatchService;
use Database\Seeders\DispatchDemoSeeder;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE SCÉNARIO DE DÉMONSTRATION EXISTE VRAIMENT.
 *
 * C'est le piège classique de ce dépôt : un module complet dont personne ne crée les lignes. Le
 * dispatch immédiat exige QUATRE choses simultanément — un métier ouvert en immédiat dans la zone,
 * des prestataires vérifiés, déclarés sur ce métier, et en ligne avec une position fraîche. Il
 * suffit qu'une seule manque pour que la recherche s'épuise en silence, et rien à l'écran ne dit
 * laquelle.
 *
 * Ce test vérifie que les quatre sont posées par le seeder, et que la chaîne se déroule réellement :
 * offre au plus proche → refus → escalade au suivant → acceptation.
 */
class ScenarioDeDemonstrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->seed(DispatchDemoSeeder::class);
    }

    #[Test]
    public function le_seeder_pose_les_quatre_conditions_du_dispatch_immediat(): void
    {
        $prestataires = User::query()->where('email', 'like', 'demo.%@brio.test')->get();

        $this->assertCount(3, $prestataires, 'Trois prestataires échelonnés : c’est ce qui rend l’escalade visible.');

        // TOUS les prestataires non demontrables d'un coup : un semis incomplet en laisse
        // plusieurs de cote, et la demonstration echoue alors sans qu'on sache combien.
        $defauts = [];

        foreach ($prestataires as $prestataire) {
            $nom = $prestataire->name ?? ('#'.$prestataire->id);

            if ($prestataire->providerProfile->verification_status !== 'verified') {
                $defauts[] = "{$nom} : profil « {$prestataire->providerProfile->verification_status} »";
            }

            if (! $prestataire->trades()->exists()) {
                $defauts[] = "{$nom} : aucun metier declare, donc aucune offre";
            }

            $presence = ProviderPresence::query()->where('provider_user_id', $prestataire->id)->first();

            if ($presence === null) {
                $defauts[] = "{$nom} : aucune ligne de presence";

                continue;
            }

            if ($presence->status !== 'online') {
                $defauts[] = "{$nom} : presence « {$presence->status} »";
            }

            if ($presence->current_lat === null) {
                $defauts[] = "{$nom} : aucune position";
            } elseif ($presence->heartbeat_at->diffInMinutes(now()) >= 5) {
                $defauts[] = "{$nom} : position trop ancienne";
            }
        }

        $this->assertSame([], $defauts, 'Ces prestataires ne sont pas en etat de recevoir une offre.');

        $this->assertTrue(
            TradeZonePricing::query()->where('asap_enabled', true)->where('is_active', true)->exists(),
            'Au moins un métier doit accepter l’immédiat, sinon rien n’est démontrable.',
        );
    }

    #[Test]
    public function la_chaine_se_deroule_du_plus_proche_au_suivant(): void
    {
        $ligne = TradeZonePricing::query()
            ->where('asap_enabled', true)
            ->where('is_active', true)
            ->firstOrFail();

        $metier = Trade::findOrFail($ligne->trade_id);

        $booking = Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'employe_id' => null,
            'assigned_employee_id' => null,
            'service_zone_id' => $ligne->service_zone_id,
            'trade_id' => $metier->id,
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            // La Grand-Place : c'est autour d'elle que le seeder échelonne ses positions.
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
            'date' => now()->toDateString(),
            'heure' => now()->format('H:i'),
        ]);

        $search = app(DispatchEngine::class)->openImmediate($booking);

        $this->assertNotNull($search);

        $premiere = MissionAssignment::query()->where('mission_id', $search->mission_id)->firstOrFail();
        $this->assertSame('demo.proche@brio.test', $premiere->user->email, 'La proximité prime.');

        app(MissionDispatchService::class)->decline($premiere, 'Démonstration');

        $seconde = MissionAssignment::query()
            ->where('mission_id', $search->mission_id)
            ->where('assignment_status', 'assigned')
            ->firstOrFail();

        $this->assertSame('demo.moyen@brio.test', $seconde->user->email, 'Puis le suivant, par distance.');

        app(MissionDispatchService::class)->accept($seconde);

        $this->assertSame('accepted', $seconde->fresh()->assignment_status);
        $this->assertSame('accepted', AsapDispatchRequest::findOrFail($search->id)->status);
        $this->assertSame($seconde->user_id, $booking->fresh()->employe_id);
    }
}
