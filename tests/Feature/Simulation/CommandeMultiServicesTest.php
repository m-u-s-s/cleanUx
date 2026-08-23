<?php

namespace Tests\Feature\Simulation;

use App\Models\Booking;
use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UN CHANTIER À PLUSIEURS MÉTIERS, DE LA COMMANDE À L'INTERVENTION. */
class CommandeMultiServicesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: MultiTradeBundle, 1: list<User>} */
    private function chantierQuoteEtAccepte(int $nombreDeMetiers = 3): array
    {
        $client = User::factory()->client()->create();

        $metiers = [];
        $prestataires = [];
        $lignes = [];

        for ($i = 0; $i < $nombreDeMetiers; $i++) {
            $metiers[$i] = Trade::factory()->create();
            $prestataires[$i] = User::factory()->employe()->create();
            $lignes[] = [
                'trade_id' => $metiers[$i]->id,
                'label' => 'Lot '.($i + 1),
                'estimated_price_cents' => 10000 * ($i + 1),
            ];
        }

        $service = app(MultiTradeBundleService::class);
        $chantier = $service->createDraft($client, 'Rénovation complète', $lignes);

        foreach ($chantier->items as $index => $item) {
            $item->update([
                'assigned_provider_user_id' => $prestataires[$index]->id,
                'quoted_price_cents' => 10000 * ($index + 1),
                'status' => MultiTradeBundleItem::STATUS_QUOTED,
            ]);
        }

        return [$service->accept($chantier->fresh('items')), $prestataires];
    }

    /** UN MÉTIER, UN RENDEZ-VOUS — et chacun au nom du prestataire qui l'a remporté. */
    public function test_un_chantier_accepte_produit_un_rendez_vous_par_metier(): void
    {
        [$chantier, $prestataires] = $this->chantierQuoteEtAccepte();

        $reservations = Booking::query()
            ->whereIn('id', $chantier->items->pluck('booking_id')->filter())
            ->get();

        $this->assertCount(3, $reservations, 'Un métier du chantier n’a produit aucun rendez-vous.');

        // C'est le prestataire RETENU qui doit être nommé, pas un autre : le devis engage une
        // personne précise, et le client l'a choisie en comparant.
        $attendus = collect($prestataires)->pluck('id')->sort()->values()->all();
        $obtenus = $reservations->pluck('employe_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame($attendus, $obtenus);
    }

    /** L'ORDRE D'EXÉCUTION SURVIT À L'ACCEPTATION. */
    public function test_l_ordre_d_execution_est_inscrit_sur_les_rendez_vous(): void
    {
        [$chantier] = $this->chantierQuoteEtAccepte();

        $ordres = Booking::query()
            ->whereIn('id', $chantier->items->pluck('booking_id')->filter())
            ->get()
            ->map(fn (Booking $r) => (int) data_get($r->matching_snapshot, 'execution_order'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([1, 2, 3], $ordres, 'Les rendez-vous du chantier ne portent pas d’ordre d’exécution distinct.');
    }

    /** ET LE CHANTIER RESTE COHÉRENT AVEC CE QU'IL A PRODUIT. */
    public function test_un_chantier_accepte_n_a_aucun_metier_sans_rendez_vous(): void
    {
        [$chantier] = $this->chantierQuoteEtAccepte(4);

        $this->assertSame(MultiTradeBundle::STATUS_ACCEPTED, $chantier->status);

        $sansReservation = $chantier->items
            ->filter(fn (MultiTradeBundleItem $item) => $item->assigned_provider_user_id && ! $item->booking_id)
            ->pluck('label')
            ->all();

        $this->assertSame(
            [],
            $sansReservation,
            'Chantier accepté alors que ces lots n’ont pas de rendez-vous : '.implode(', ', $sansReservation),
        );
    }

    /** CHAQUE RENDEZ-VOUS DU CHANTIER DOIT POUVOIR ÊTRE EXÉCUTÉ. */
    public function test_un_rendez_vous_de_chantier_confirme_obtient_sa_mission(): void
    {
        [$chantier, $prestataires] = $this->chantierQuoteEtAccepte(2);

        $premier = $chantier->items->firstWhere('booking_id', '!=', null);
        $this->assertNotNull($premier);

        $reservation = Booking::query()->findOrFail($premier->booking_id);
        $reservation->forceFill([
            'status' => 'confirme',
            'date' => now()->addWeek()->toDateString(),
            'heure' => '09:00:00',
        ])->save();

        $mission = $reservation->fresh()->missions()->latest('id')->first();

        $this->assertNotNull($mission, 'Un rendez-vous de chantier confirmé n’obtient aucune mission.');

        $this->assertSame(
            (int) $premier->assigned_provider_user_id,
            $reservation->fresh()->intervenantId(),
            'La mission du chantier ne désigne pas le prestataire qui a remporté le lot.',
        );

        $this->assertContains($reservation->fresh()->intervenantId(), collect($prestataires)->pluck('id')->all());
    }
}
