{{-- Onglet « Usage produit » de /admin/analytics/exploration : la page porte le titre,
     cette vue ne pose que ses cartes. --}}
<div class="space-y-6">

    <x-app-card muted padding="p-4 md:p-5">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <p class="brio-section-subtitle">
                Période : <code class="font-mono">{{ $from->format('d/m H:i') }} → {{ $to->format('d/m H:i') }}</code>
            </p>
            <select wire:model.live="rangeKey" class="rounded-xl text-sm">
                <option value="24h">Dernières 24h</option>
                <option value="7d">7 derniers jours</option>
                <option value="30d">30 derniers jours</option>
            </select>
        </div>
    </x-app-card>

    <div class="grid gap-4 grid-cols-2 xl:grid-cols-4">
        <x-kpi-card title="Événements" :value="number_format($kpis['events'], 0, ',', ' ')" tone="slate" icon="📡" />
        <x-kpi-card title="Utilisateurs uniques" :value="number_format($kpis['unique_users'], 0, ',', ' ')" tone="blue" icon="👥" />
        <x-kpi-card title="Sessions" :value="number_format($kpis['sessions'], 0, ',', ' ')" tone="green" icon="🧭" />
        <x-kpi-card title="Revenu attribué" :value="locale_currency($kpis['revenue_cents'] / 100)" tone="amber" icon="💶" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-table-shell title="Entonnoir" subtitle="Où les visiteurs s'arrêtent, étape par étape.">
            <div class="flex justify-end px-5 pt-4 md:px-6">
                <select wire:model.live="funnelType" class="rounded-lg text-xs">
                    <option value="booking">Parcours de réservation</option>
                    <option value="registration">Inscription → 1re réservation</option>
                </select>
            </div>

            <table class="min-w-full brio-table">
                <thead>
                    <tr>
                        <th class="text-left">Étape</th>
                        <th class="text-right">Personnes</th>
                        <th class="text-right">% vs départ</th>
                        <th class="text-right">% vs précédente</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($funnel as $step)
                        <tr>
                            <td class="font-mono text-xs">{{ $step['step'] }}</td>
                            <td class="text-right tabular-nums">{{ number_format($step['count'], 0, ',', ' ') }}</td>
                            <td class="text-right tabular-nums">{{ number_format($step['rate_from_start'] * 100, 1, ',', ' ') }}%</td>
                            <td class="text-right tabular-nums">{{ number_format($step['rate_from_prev'] * 100, 1, ',', ' ') }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state title="Aucun entonnoir" message="Aucun événement sur cette période." icon="🧭" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-table-shell>

        <x-table-shell title="Événements les plus fréquents" subtitle="Ce que les visiteurs déclenchent le plus.">
            <table class="min-w-full brio-table">
                <thead>
                    <tr>
                        <th class="text-left">Événement</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topEvents as $row)
                        <tr>
                            <td class="font-mono text-xs">{{ $row->event_name }}</td>
                            <td class="text-right tabular-nums">{{ number_format($row->total, 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">
                                <x-empty-state title="Aucun événement" message="Rien n'a été enregistré sur cette période." icon="📡" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-table-shell>
    </div>
</div>
