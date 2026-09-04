@if($visibleDashboardSections['plateforme'] ?? true)
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">
                Santé de la plateforme
            </p>
            <h2 class="text-2xl font-black text-slate-900">
                L’argent, les intégrations et le SAV
            </h2>
            <p class="text-sm text-slate-500">
                Chiffres globaux, jamais restreints à une zone : ils décrivent la plateforme, pas le terrain.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
            <x-kpi-card title="Réservations aujourd'hui" :value="$santePlateforme['reservations_du_jour']" tone="blue" icon="🗓️" />
            <x-kpi-card title="Missions actives" :value="$santePlateforme['missions_actives']" tone="slate" icon="🚚" />
            <x-kpi-card title="Prestataires en ligne" :value="$santePlateforme['prestataires_en_ligne']" tone="green" icon="🟢" />
            <x-kpi-card title="CA aujourd'hui" :value="locale_currency($santePlateforme['ca_du_jour'])" tone="green" icon="💶" />
            <x-kpi-card title="Versements en attente" :value="$santePlateforme['versements_en_attente']"
                        :tone="$santePlateforme['versements_en_attente'] > 0 ? 'amber' : 'slate'" icon="🏦" />
            <x-kpi-card title="Webhooks échoués (24h)" :value="$santePlateforme['webhooks_echoues_24h']"
                        :tone="$santePlateforme['webhooks_echoues_24h'] > 0 ? 'red' : 'slate'" icon="🔌" />
        </div>

        {{-- Un montant ne se coupe pas en deux, et les titres reservent deux lignes pour que les
             cinq valeurs partagent la meme ligne de base. --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5
                    [&_.brio-kpi-label]:min-h-[2.6em]
                    [&_.brio-kpi-value]:whitespace-nowrap [&_.brio-kpi-value]:!text-2xl">
            <x-kpi-card title="CA total" :value="locale_currency($santePlateforme['ca_total'])" tone="blue" icon="📊" />
            <x-kpi-card title="Marge plateforme" :value="locale_currency($santePlateforme['marge_plateforme'])"
                        hint="La commission encaissée" tone="green" icon="🏛️" />
            <x-kpi-card title="Missions" :value="number_format($santePlateforme['missions_total'], 0, ',', ' ')" tone="slate" icon="📋" />
            <x-kpi-card title="Terminées" :value="number_format($santePlateforme['missions_terminees'], 0, ',', ' ')" tone="green" icon="✅" />
            <x-kpi-card title="Note moyenne" :value="$santePlateforme['note_moyenne'].'/5'" tone="amber" icon="⭐" />
        </div>

        <x-app-card title="Tendance 7 jours" subtitle="Réservations créées, jour par jour.">
            <div id="tendance-reservations" wire:ignore class="min-h-[240px]"></div>
        </x-app-card>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-table-shell title="Litiges en cours" subtitle="Les cinq dossiers ouverts les plus récents.">
                <div class="px-5 pb-4 md:px-6">
                    @forelse($santePlateforme['litiges_en_cours'] as $litige)
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-0 dark:border-slate-700">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $litige->reference }}</p>
                                <p class="brio-section-subtitle truncate">{{ $litige->subject }}</p>
                            </div>
                            <x-ui.badge :tone="match($litige->status) {
                                'open' => 'warning',
                                'assigned' => 'info',
                                default => 'brand',
                            }">{{ $litige->status }}</x-ui.badge>
                        </div>
                    @empty
                        <x-empty-state title="Aucun litige" message="Aucun dossier n'est ouvert en ce moment." icon="🕊️" />
                    @endforelse
                </div>
            </x-table-shell>

            <x-table-shell title="Dernières réservations" subtitle="Les dix dernières entrées, tous statuts confondus.">
                <table class="brio-table w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Référence</th>
                            <th class="text-left">Service</th>
                            <th class="text-left">Statut</th>
                            <th class="text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($santePlateforme['dernieres_reservations'] as $reservation)
                            <tr>
                                {{-- `reference` n'existe pas sur bookings : la colonne sortait vide. --}}
                                <td class="font-mono text-xs">{{ $reservation->booking_reference ?: '—' }}</td>
                                <td>{{ $reservation->serviceCatalog?->name ?? '—' }}</td>
                                <td>
                                    <x-ui.badge :tone="match($reservation->status) {
                                        'confirme' => 'info',
                                        'completed', 'termine' => 'success',
                                        'annule', 'refuse' => 'danger',
                                        default => 'neutral',
                                    }">{{ $reservation->status }}</x-ui.badge>
                                </td>
                                <td class="text-xs">
                                    {{ $reservation->scheduled_date
                                        ? \Carbon\Carbon::parse($reservation->scheduled_date)->format('d/m')
                                        : $reservation->created_at?->format('d/m') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state title="Aucune réservation" message="Rien n'a encore été réservé." icon="🗓️" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-table-shell>
        </div>
    </section>

    @push('scripts')
        <script>
            (function () {
                function dessinerTendance() {
                    var el = document.getElementById('tendance-reservations');
                    if (!el || el.dataset.dessine === '1' || typeof ApexCharts === 'undefined') return;
                    el.dataset.dessine = '1';

                    var data = @json($santePlateforme['tendance_7_jours']);

                    new ApexCharts(el, {
                        chart: { type: 'area', height: 240, toolbar: { show: false }, fontFamily: 'inherit' },
                        series: [{ name: 'Réservations', data: data.map(function (d) { return d.count; }) }],
                        xaxis: { categories: data.map(function (d) { return d.date; }) },
                        yaxis: { min: 0, tickAmount: 4 },
                        colors: [window.brioJeton('--brio-accent', '#6366f1')],
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
                        stroke: { curve: 'smooth', width: 2 },
                        dataLabels: { enabled: false },
                        grid: { borderColor: window.brioJeton('--brio-border', 'currentColor'), strokeDashArray: 3 },
                        tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
                    }).render();
                }

                document.addEventListener('livewire:navigated', dessinerTendance);
                document.addEventListener('livewire:initialized', dessinerTendance);
                if (document.readyState !== 'loading') { dessinerTendance(); }
                else { document.addEventListener('DOMContentLoaded', dessinerTendance); }
            })();
        </script>
    @endpush
@endif
