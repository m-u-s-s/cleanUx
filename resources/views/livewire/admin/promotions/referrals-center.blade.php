<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Promotions</p>
                <h1 class="text-2xl font-black text-slate-900">Programme de parrainage</h1>
                <p class="text-sm text-slate-500">Suivez les parrainages, les conversions et le coût d'acquisition.</p>
            </div>

            <a href="{{ route('admin.dashboard') }}"
               class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Parrainages</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['total_referrals']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Qualifiés</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['qualified']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Récompensés</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['rewarded']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Frauduleux</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['fraud']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Récompenses €</p>
                <p class="text-2xl font-black text-slate-900">
                    {{ number_format((float) $kpis['total_rewards_value'], 2, ',', ' ') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Top parrains --}}
            <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-3">
                <h2 class="text-lg font-bold text-slate-900">Top parrains</h2>
                <div class="space-y-2">
                    @forelse($topReferrers as $r)
                        <div class="flex items-center justify-between py-2 border-b last:border-0">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $r->name }}</p>
                                <p class="text-xs text-slate-500 font-mono">{{ $r->referral_code }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-slate-900">{{ $r->qualified_count }}</p>
                                <p class="text-xs text-emerald-600">
                                    +{{ number_format((float)$r->total_earned, 2, ',', ' ') }} €
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 text-center py-6">Aucun parrain pour le moment.</p>
                    @endforelse
                </div>
            </div>

            {{-- Liste des parrainages --}}
            <div class="lg:col-span-2 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <h2 class="text-lg font-bold text-slate-900">Parrainages récents</h2>

                    <div class="flex gap-2">
                        <input type="text" wire:model.live.debounce.300ms="search"
                               placeholder="Rechercher email/code…"
                               class="rounded-xl border-gray-300 text-sm" />
                        <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                            <option value="">Tous statuts</option>
                            <option value="signed_up">Inscrits</option>
                            <option value="qualified">Qualifiés</option>
                            <option value="rewarded">Récompensés</option>
                            <option value="expired">Expirés</option>
                            <option value="fraud_flagged">Frauduleux</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Parrain</th>
                                <th class="px-3 py-2 text-left">Filleul</th>
                                <th class="px-3 py-2 text-left">Code</th>
                                <th class="px-3 py-2 text-left">Statut</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($referrals as $r)
                                <tr>
                                    <td class="px-3 py-2">{{ $r->referrer?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $r->referee?->name ?? $r->referee_email ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $r->referral_code }}</td>
                                    <td class="px-3 py-2">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800' => in_array($r->status, ['qualified','rewarded']),
                                            'bg-amber-100 text-amber-800' => in_array($r->status, ['signed_up','invited']),
                                            'bg-red-100 text-red-800' => $r->status === 'fraud_flagged',
                                            'bg-slate-100 text-slate-700' => $r->status === 'expired',
                                        ])>{{ $r->status }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500">
                                        {{ optional($r->signed_up_at)->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if($r->status !== 'fraud_flagged')
                                            <button wire:click="flagFraud({{ $r->id }})"
                                                    wire:confirm="Marquer ce parrainage comme frauduleux ?"
                                                    class="text-xs font-semibold text-red-600 hover:underline">
                                                Fraude
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-6 text-center text-slate-400">Aucun parrainage.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>{{ $referrals->links() }}</div>
            </div>
        </div>
    </div>
</div>
