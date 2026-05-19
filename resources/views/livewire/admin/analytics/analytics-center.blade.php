<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Analytics v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Analytics produit</h1>
                <p class="text-sm text-slate-500">
                    Période : <code class="font-mono">{{ $from->format('d/m H:i') }} → {{ $to->format('d/m H:i') }}</code>
                </p>
            </div>
            <div class="flex gap-2">
                <select wire:model.live="rangeKey" class="rounded-xl border-gray-300 text-sm">
                    <option value="24h">Dernières 24h</option>
                    <option value="7d">7 derniers jours</option>
                    <option value="30d">30 derniers jours</option>
                </select>
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Events</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['events']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Users uniques</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['unique_users']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Sessions</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['sessions']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Revenu attribué (€)</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['revenue_cents'] / 100, 2, ',', ' ') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h2 class="font-bold text-slate-900">Funnel</h2>
                    <select wire:model.live="funnelType" class="rounded-lg border-gray-300 text-xs">
                        <option value="booking">Booking flow</option>
                        <option value="registration">Registration → first booking</option>
                    </select>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Step</th>
                            <th class="px-4 py-2 text-right">Users</th>
                            <th class="px-4 py-2 text-right">% vs start</th>
                            <th class="px-4 py-2 text-right">% vs prev</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($funnel as $step)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $step['step'] }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($step['count']) }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($step['rate_from_start'] * 100, 1) }}%</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($step['rate_from_prev'] * 100, 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Aucune donnée funnel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="px-4 py-3 border-b">
                    <h2 class="font-bold text-slate-900">Top events</h2>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Event</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($topEvents as $row)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $row->event_name }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($row->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-6 text-center text-slate-400">Aucun event.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
