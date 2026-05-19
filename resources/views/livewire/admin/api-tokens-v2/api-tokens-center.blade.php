<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">API Tokens v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Tokens & Scopes</h1>
                <p class="text-sm text-slate-500">Sanctum étendu — scopes granulaires + rate limit + audit usage</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Tokens totaux</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['tokens_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Actifs</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['tokens_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Suspendus</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['tokens_suspended']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Calls 24h</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['usages_24h']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['tokens' => 'Tokens', 'scopes' => 'Scopes catalog', 'usages' => 'Usages'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'tokens')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Créé</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Rôle</th>
                            <th class="px-4 py-2 text-left">Scopes</th>
                            <th class="px-4 py-2 text-left">Rate</th>
                            <th class="px-4 py-2 text-left">Usage</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $t)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($t->created_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $t->display_name ?: $t->name }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $t->owner_role ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach((array) ($t->abilities ?? []) as $scope)
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px]">{{ $scope }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $t->effectiveRateLimit() }}/min</td>
                                <td class="px-4 py-2 text-xs">{{ number_format($t->usage_count) }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if($t->isSuspended())
                                        <span class="text-amber-600">⊘ suspendu</span>
                                    @elseif($t->isExpired())
                                        <span class="text-red-600">✗ expiré</span>
                                    @elseif($t->isRotatedExpired())
                                        <span class="text-slate-500">↻ rotated</span>
                                    @else
                                        <span class="text-emerald-600">✓ actif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-xs space-x-2">
                                    @if($t->isSuspended())
                                        <button wire:click="unsuspend({{ $t->id }})" class="text-emerald-600 hover:underline">Réactiver</button>
                                    @else
                                        <button wire:click="suspend({{ $t->id }})" class="text-amber-600 hover:underline">Suspendre</button>
                                    @endif
                                    <button wire:click="revoke({{ $t->id }})" class="text-red-600 hover:underline"
                                        onclick="return confirm('Révoquer définitivement ce token ?')">Révoquer</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucun token.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'scopes')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Catégorie</th>
                            <th class="px-4 py-2 text-left">Required role</th>
                            <th class="px-4 py-2 text-left">Dangerous</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $s)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $s->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->category }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->required_role ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->is_dangerous ? '⚠️' : '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun scope.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Token #</th>
                            <th class="px-4 py-2 text-left">Method</th>
                            <th class="px-4 py-2 text-left">Path</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Latency</th>
                            <th class="px-4 py-2 text-left">Size</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $u)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($u->occurred_at)->format('d/m H:i:s') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">#{{ $u->token_id }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $u->method }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ \Illuminate\Support\Str::limit($u->route_path, 60) }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $u->response_status >= 200 && $u->response_status < 300,
                                        'bg-amber-100 text-amber-800' => $u->response_status >= 400 && $u->response_status < 500,
                                        'bg-red-100 text-red-800' => $u->response_status >= 500,
                                        'bg-slate-100 text-slate-800' => $u->response_status >= 300 && $u->response_status < 400,
                                    ])>{{ $u->response_status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $u->latency_ms ? $u->latency_ms . 'ms' : '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $u->response_size_bytes ? number_format($u->response_size_bytes) . 'B' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun usage.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
