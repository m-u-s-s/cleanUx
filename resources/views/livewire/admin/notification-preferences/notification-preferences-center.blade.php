<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Notif Prefs v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Préférences Notifications</h1>
                <p class="text-sm text-slate-500">Matrice unifiée channel × category, audit RGPD versionné</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Users configurés</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['users_with_prefs']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Opt-outs total</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['opt_outs_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Changements 24h</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['audits_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Total audits</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['audits_total']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            <button wire:click="$set('tab', 'audits')"
                    @class([
                        'px-4 py-2 text-sm font-semibold',
                        'border-b-2 border-indigo-600 text-indigo-700' => $tab === 'audits',
                        'text-slate-500 hover:text-slate-900' => $tab !== 'audits',
                    ])>Audits</button>
            <button wire:click="$set('tab', 'matrix-by-channel')"
                    @class([
                        'px-4 py-2 text-sm font-semibold',
                        'border-b-2 border-indigo-600 text-indigo-700' => $tab === 'matrix-by-channel',
                        'text-slate-500 hover:text-slate-900' => $tab !== 'matrix-by-channel',
                    ])>Preferences actuelles</button>
        </div>

        <div class="flex flex-wrap gap-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="User email / name..."
                   class="flex-1 rounded-xl border-gray-300 text-sm" />
            <select wire:model.live="filterChannel" class="rounded-xl border-gray-300 text-sm">
                <option value="">Tous channels</option>
                <option value="email">Email</option>
                <option value="sms">SMS</option>
                <option value="push">Push</option>
                <option value="inapp">In-app</option>
                <option value="webhook">Webhook</option>
            </select>
            <select wire:model.live="filterCategory" class="rounded-xl border-gray-300 text-sm">
                <option value="">Toutes catégories</option>
                <option value="transactional">Transactional</option>
                <option value="verification">Verification</option>
                <option value="reminder">Reminder</option>
                <option value="marketing">Marketing</option>
                <option value="support">Support</option>
                <option value="security">Security</option>
                <option value="product">Product</option>
            </select>
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'audits')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Channel</th>
                            <th class="px-4 py-2 text-left">Category</th>
                            <th class="px-4 py-2 text-left">Old → New</th>
                            <th class="px-4 py-2 text-left">Source</th>
                            <th class="px-4 py-2 text-left">Actor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $a)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $a->changed_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $a->user?->email }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $a->channel }}</td>
                                <td class="px-4 py-2 text-xs">{{ $a->category }}</td>
                                <td class="px-4 py-2 text-xs font-mono">
                                    {{ $a->old_value === null ? '—' : ($a->old_value ? '✓' : '✗') }}
                                    →
                                    {{ $a->new_value ? '✓' : '✗' }}
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $a->source }}</td>
                                <td class="px-4 py-2 text-xs">{{ $a->actor?->email ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun audit.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Channel</th>
                            <th class="px-4 py-2 text-left">Category</th>
                            <th class="px-4 py-2 text-left">Allowed</th>
                            <th class="px-4 py-2 text-right">Version</th>
                            <th class="px-4 py-2 text-left">Source</th>
                            <th class="px-4 py-2 text-left">Modifié</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $p)
                            <tr>
                                <td class="px-4 py-2 text-xs">{{ $p->user?->email }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $p->channel }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->category }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->is_allowed ? '✓' : '✗' }}</td>
                                <td class="px-4 py-2 text-right text-xs font-mono">v{{ $p->version }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->source }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $p->last_changed_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune preference.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
