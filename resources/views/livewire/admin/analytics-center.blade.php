<div class="space-y-6">
    <x-page-shell
        title="Centre analytics"
        subtitle="Vue consolidée des performances, du chiffre d'affaires, de la marge estimée et des signaux opérationnels par axe." 
        eyebrow="Pilotage premium"
    >
        <x-slot:actions>
            <span class="cu-inline-stat">{{ $kpis['count'] }} mission(s)</span>
            <span class="cu-inline-stat">€ {{ number_format($kpis['turnover'], 2, ',', ' ') }} HTVA</span>
            <button wire:click="exportAnalyticsCsv" class="cu-btn-primary">Exporter CSV</button>
        </x-slot:actions>
    </x-page-shell>

    <x-filter-panel title="Filtres analytics" subtitle="Affinez les résultats par période, marché, zone, service ou employé.">
        <div class="cu-filter-grid-wide">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche" class="xl:col-span-2">
            <input wire:model.live="dateFrom" type="date">
            <input wire:model.live="dateTo" type="date">
            <select wire:model.live="status">
                <option value="">Tous statuts</option>
                <option value="en_attente">En attente</option>
                <option value="confirme">Confirmé</option>
                <option value="en_route">En route</option>
                <option value="sur_place">Sur place</option>
                <option value="termine">Terminé</option>
                <option value="annule">Annulé</option>
                <option value="refuse">Refusé</option>
            </select>
            <select wire:model.live="market">
                <option value="">Tous marchés</option>
                <option value="particulier">Particulier</option>
                <option value="entreprise">Entreprise</option>
            </select>
            <select wire:model.live="zoneId">
                <option value="">Toutes zones</option>
                @foreach($this->zones as $zone)
                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="serviceId">
                <option value="">Tous services</option>
                @foreach($this->services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="employeeId">
                <option value="">Tous employés</option>
                @foreach($this->employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
    </x-filter-panel>

    <livewire:admin.mission-quality-analytics />
    
    <div wire:loading.delay class="space-y-4">
        <x-loading-panel message="Mise à jour des indicateurs analytics…" />
    </div>

    <div wire:loading.remove class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            <x-kpi-card title="Missions" :value="$kpis['count']" tone="slate" icon="📅" />
            <x-kpi-card title="CA HTVA" :value="'€ '.number_format($kpis['turnover'], 2, ',', ' ')" tone="blue" icon="💶" />
            <x-kpi-card title="Marge estimée" :value="'€ '.number_format($kpis['margin_estimate'], 2, ',', ' ')" tone="green" icon="📈" />
            <x-kpi-card title="Part entreprise" :value="number_format($kpis['entreprise_share'], 1, ',', ' ').'%'" tone="amber" icon="🏢" />
            <x-kpi-card title="Couverture feedback" :value="number_format($kpis['feedback_coverage'], 1, ',', ' ').'%'" tone="orange" icon="⭐" />
            <x-kpi-card title="Retards finance" :value="$kpis['overdue_count']" :hint="'€ '.number_format($kpis['outstanding_balance'], 2, ',', ' ')" tone="red" icon="⏰" />
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <x-table-shell title="KPIs par zone" subtitle="Top 10 zones par volume, CA et marge.">
                <table class="min-w-full cu-table">
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th>Volume</th>
                            <th>CA</th>
                            <th>Marge</th>
                            <th>Annul.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($zoneAnalytics as $row)
                            <tr>
                                <td class="font-medium text-slate-800">{{ $row['name'] }}</td>
                                <td>{{ $row['count'] }}</td>
                                <td>€ {{ number_format($row['margin'], 2, ',', ' ') }}</td>
                                <td>€ {{ number_format($row['margin'], 2, ',', ' ') }}</td>
                                <td>{{ number_format($row['cancellation_rate'], 1, ',', ' ') }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state title="Aucune donnée" message="Aucune zone ne remonte de données pour ces filtres." icon="🗺️" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-table-shell>

            <x-app-card title="Tendance mensuelle" subtitle="CA et marge par mois.">
                <div class="space-y-3">
                    @forelse($monthTrend as $row)
                        <div class="cu-list-item">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium text-slate-800">{{ $row['month'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['count'] }} missions</div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <div class="text-xs uppercase tracking-wide text-slate-400">CA</div>
                                    <div class="mt-1 font-semibold text-slate-800">€ {{ number_format($row['turnover'], 2, ',', ' ') }}</div>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <div class="text-xs uppercase tracking-wide text-slate-400">Marge</div>
                                    <div class="mt-1 font-semibold text-slate-800">€ {{ number_format($row['margin'], 2, ',', ' ') }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-empty-state title="Aucune tendance" message="Aucune tendance mensuelle disponible avec les filtres actuels." icon="📊" />
                    @endforelse
                </div>
            </x-app-card>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <x-app-card title="Par service" subtitle="Top 10 des services les plus actifs.">
                <div class="space-y-3 text-sm">
                    @forelse($serviceAnalytics as $row)
                        <div class="cu-list-item">
                            <div class="font-medium text-slate-800">{{ $row['name'] }}</div>
                            <div class="mt-1 text-slate-500">{{ $row['count'] }} missions · {{ $row['completed'] }} terminées</div>
                            <div class="mt-2 flex items-center justify-between"><span class="text-slate-500">CA</span><span class="font-semibold text-slate-800">€ {{ number_format($row['turnover'], 2, ',', ' ') }}</span></div>
                            <div class="mt-1 flex items-center justify-between"><span class="text-slate-500">Marge</span><span class="font-semibold text-slate-800">€ {{ number_format($row['margin'], 2, ',', ' ') }}</span></div>
                        </div>
                    @empty
                        <x-empty-state title="Aucun service" message="Aucune donnée service disponible." icon="🧽" />
                    @endforelse
                </div>
            </x-app-card>

            <x-app-card title="Par employé" subtitle="Volume, ponctualité et satisfaction.">
                <div class="space-y-3 text-sm">
                    @forelse($employeeAnalytics as $row)
                        <div class="cu-list-item">
                            <div class="font-medium text-slate-800">{{ $row['name'] }}</div>
                            <div class="mt-1 text-slate-500">{{ $row['count'] }} missions · {{ $row['completed'] }} terminées</div>
                            <div class="mt-2 flex items-center justify-between"><span class="text-slate-500">Marge</span><span class="font-semibold text-slate-800">€ {{ number_format($row['margin'], 2, ',', ' ') }}</span></div>
                            <div class="mt-1 flex items-center justify-between"><span class="text-slate-500">Note moyenne</span><span class="font-semibold text-slate-800">{{ $row['avg_satisfaction'] !== null ? number_format($row['avg_satisfaction'], 1, ',', ' ') : '—' }}/5</span></div>
                            <div class="mt-1 flex items-center justify-between"><span class="text-slate-500">Signaux retard</span><span class="font-semibold text-slate-800">{{ $row['delay_signals'] }}</span></div>
                        </div>
                    @empty
                        <x-empty-state title="Aucun employé" message="Aucune donnée employé disponible." icon="👨‍🔧" />
                    @endforelse
                </div>
            </x-app-card>

            <x-app-card title="Par client" subtitle="Top clients par volume et revenu.">
                <div class="space-y-3 text-sm">
                    @forelse($clientAnalytics as $row)
                        <div class="cu-list-item">
                            <div class="font-medium text-slate-800">{{ $row['name'] }}</div>
                            <div class="mt-1 text-slate-500">{{ $row['count'] }} missions</div>
                            <div class="mt-2 flex items-center justify-between"><span class="text-slate-500">CA</span><span class="font-semibold text-slate-800">€ {{ number_format($row['turnover'], 2, ',', ' ') }}</span></div>
                            <div class="mt-1 flex items-center justify-between"><span class="text-slate-500">Panier moyen</span><span class="font-semibold text-slate-800">€ {{ number_format($row['avg_ticket'], 2, ',', ' ') }}</span></div>
                            <div class="mt-1 flex items-center justify-between"><span class="text-slate-500">Satisfaction</span><span class="font-semibold text-slate-800">{{ $row['avg_satisfaction'] !== null ? number_format($row['avg_satisfaction'], 1, ',', ' ') : '—' }}/5</span></div>
                            <div class="mt-1 flex items-center justify-between"><span class="text-slate-500">Marché</span><span class="font-semibold text-slate-800">{{ $row['market'] }}</span></div>
                        </div>
                    @empty
                        <x-empty-state title="Aucun client" message="Aucune donnée client disponible." icon="👤" />
                    @endforelse
                </div>
            </x-app-card>
        </div>

        <x-table-shell title="Détail des missions" subtitle="Vue opérationnelle détaillée avec coût et marge estimée.">
            <table class="min-w-full cu-table">
                <thead>
                    <tr>
                        <th>Réf.</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Employé</th>
                        <th>Zone</th>
                        <th>Service</th>
                        <th>Statut</th>
                        <th class="text-right">CA</th>
                        <th class="text-right">Marge</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="font-medium text-slate-800">{{ $row->booking_reference ?: 'RDV-'.$row->id }}</td>
                            <td>{{ optional($row->date)->format('d/m/Y') }}</td>
                            <td>{{ $row->organizationAccount?->name ?: $row->client?->name }}</td>
                            <td>{{ $row->employe?->name ?: '—' }}</td>
                            <td>{{ $row->serviceZone?->name ?: '—' }}</td>
                            <td>{{ $row->service_display_name ?: '—' }}</td>
                            <td>{{ $row->status }}</td>
                            <td class="text-right font-semibold text-slate-800">€ {{ number_format((float) (data_get($row->pricing_snapshot, 'devis_estime') ?? $row->devis_estime ?? $row->serviceCatalog?->base_price ?? 0), 2, ',', ' ') }}</td>
                            <td class="text-right font-semibold text-slate-800">€ {{ number_format((float) app(\App\Services\Finance\FinanceDocumentService::class)->amountBreakdownFor($row)['estimated_margin_amount'], 2, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-empty-state title="Aucune mission" message="Aucune mission ne correspond à ces filtres." icon="📋" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-table-shell>
    </div>
</div>
