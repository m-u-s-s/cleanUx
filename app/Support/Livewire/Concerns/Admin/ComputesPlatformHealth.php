<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Models\ProviderPresence;
use App\Models\WebhookDelivery;
use App\Services\Admin\AdminAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * La santé de la plateforme : l'argent, les intégrations et le SAV.
 *
 * Ces chiffres sont GLOBAUX, jamais restreints à la zone de l'administrateur, parce qu'ils
 * décrivent la plateforme et non le terrain. C'est le comportement qu'avait `/admin/home`.
 *
 * @property-read array<string, mixed> $santePlateforme
 */
trait ComputesPlatformHealth
{
    /** @return array<string, mixed> */
    public function getSantePlateformeProperty(): array
    {
        // TRENTE SECONDES, LA CADENCE DE L'ANCIENNE PAGE. Le tableau de bord se sonde toutes les
        // dix : sans ce cache, la fusion triplerait ces neuf requetes au lieu de les reprendre.
        return Cache::remember('admin.sante_plateforme', now()->addSeconds(30), fn () => $this->mesurerLaSante());
    }

    /** @return array<string, mixed> */
    private function mesurerLaSante(): array
    {
        $aujourdhui = now()->startOfDay();

        // LES CINQ TOTAUX VENAIENT DE L'ONGLET « Vue d'ensemble ». Le service reste la seule
        // source : la marge de la plateforme n'etait lue que la, et ses tests la gardent.
        $apercu = app(AdminAnalyticsService::class)->overview();

        return [
            'ca_total' => (float) $apercu['total_revenue'],
            'marge_plateforme' => (float) $apercu['total_margin'],
            'missions_total' => (int) $apercu['missions_count'],
            'missions_terminees' => (int) $apercu['completed_missions'],
            'note_moyenne' => (float) $apercu['average_rating'],
            'reservations_du_jour' => Booking::whereDate('created_at', $aujourdhui)->count(),
            'missions_actives' => Mission::whereIn('status', ['planned', 'en_route', 'started'])->count(),
            'prestataires_en_ligne' => ProviderPresence::where('status', 'online')->count(),
            'ca_du_jour' => $this->chiffreDAffairesDuJour($aujourdhui),
            'versements_en_attente' => ProviderPayout::where('status', ProviderPayout::STATUS_PENDING)->count(),
            'webhooks_echoues_24h' => WebhookDelivery::whereIn('status', [
                WebhookDelivery::STATUS_FAILED,
                WebhookDelivery::STATUS_DEAD,
            ])->where('updated_at', '>=', now()->subHours(24))->count(),
            'litiges_en_cours' => ComplaintCase::whereIn('status', ['open', 'assigned', 'investigating'])
                ->latest()
                ->take(5)
                ->get(),
            'dernieres_reservations' => Booking::with('serviceCatalog:id,name')
                ->latest()
                ->take(10)
                ->get(['id', 'booking_reference', 'status', 'service_catalog_id', 'created_at', 'scheduled_date']),
            'tendance_7_jours' => $this->tendanceDesReservations(),
        ];
    }

    /**
     * Le nombre de réservations par jour sur sept jours, pour la courbe.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function tendanceDesReservations(): array
    {
        // Une requete groupee, pas sept comptages : le meme motif que statsMensuelles.
        $debut = now()->subDays(6)->startOfDay();

        $comptes = Booking::query()
            ->where('created_at', '>=', $debut)
            ->selectRaw('DATE(created_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        return collect(range(6, 0))->map(function (int $joursAvant) use ($comptes) {
            $date = now()->subDays($joursAvant);

            return [
                'date' => $date->format('d/m'),
                'count' => (int) ($comptes[$date->format('Y-m-d')] ?? 0),
            ];
        })->all();
    }

    private function chiffreDAffairesDuJour(Carbon $jour): float
    {
        return (float) Booking::whereDate('created_at', $jour)
            ->whereNotNull('estimated_price')
            ->whereIn('status', ['confirme', 'completed', 'termine', 'sur_place', 'on_site'])
            ->sum('estimated_price');
    }
}
