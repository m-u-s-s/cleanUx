<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Multi-trade orchestration</p>
                <h1 class="text-2xl font-black text-slate-900">Bundles chantiers</h1>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Total</p>
                <p class="text-2xl font-black text-slate-900">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Drafts</p>
                <p class="text-2xl font-black text-slate-500">{{ $stats['draft'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En quoting</p>
                <p class="text-2xl font-black text-amber-600">{{ $stats['quoting'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Quotés</p>
                <p class="text-2xl font-black text-indigo-600">{{ $stats['quoted'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En cours</p>
                <p class="text-2xl font-black text-purple-600">{{ $stats['in_progress'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">CA accepté</p>
                <p class="text-2xl font-black text-emerald-700"><x-money :amount="(float) ($stats['total_value_cents'] / 100)" :decimals="0" /></p>
            </div>
        </div>

        <div class="rounded-2xl border bg-white shadow-sm">
            <div class="p-4 flex gap-3 border-b">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche code/nom/client..." class="rounded-lg border-slate-300 text-sm flex-1">
                <select wire:model.live="statusFilter" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="draft">Draft</option>
                    <option value="quoting">Quoting</option>
                    <option value="quoted">Quoted</option>
                    <option value="accepted">Accepted</option>
                    <option value="in_progress">In progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="brio-table-cadre"><table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Nom</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2">Items</th>
                        <th class="px-3 py-2">Statut</th>
                        <th class="px-3 py-2">Valeur</th>
                        <th class="px-3 py-2">Créé</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bundles as $b)
                        @php $color = ['draft'=>'slate','quoting'=>'amber','quoted'=>'indigo','accepted'=>'emerald','in_progress'=>'purple','completed'=>'emerald','cancelled'=>'rose'][$b->status] ?? 'slate'; @endphp
                        <tr class="border-t hover:bg-slate-50">
                            <td class="px-3 py-2 font-mono text-xs">{{ Str::limit($b->code, 14) }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $b->name }}</td>
                            <td class="px-3 py-2 text-xs">{{ $b->client?->name }}<br><span class="text-slate-400">{{ $b->client?->email }}</span></td>
                            <td class="px-3 py-2 text-center font-bold">{{ $b->items->count() }}</td>
                            <td class="px-3 py-2"><span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 text-xs font-semibold">{{ $b->status }}</span></td>
                            <td class="px-3 py-2 font-bold"><x-money :amount="(float) (($b->total_quoted_cents ?: $b->total_estimated_cents) / 100)" :decimals="0" /></td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $b->created_at?->diffForHumans() }}</td>
                            <td class="px-3 py-2 text-right">
                                <button wire:click="openBundle({{ $b->id }})" class="text-indigo-600 hover:underline text-xs font-semibold">Détail</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-slate-400">Aucun bundle.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="p-3">{{ $bundles->links() }}</div>
        </div>

        @if ($selectedBundle)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6 border-b flex justify-between items-start">
                        <div>
                            <p class="text-xs font-mono text-slate-400">{{ $selectedBundle->code }}</p>
                            <h2 class="text-lg font-bold text-slate-900">{{ $selectedBundle->name }}</h2>
                            <p class="text-xs text-slate-500">Client: {{ $selectedBundle->client?->name }} ({{ $selectedBundle->client?->email }})</p>
                        </div>
                        <button wire:click="closeBundle" class="text-slate-400 hover:text-slate-700 text-2xl">×</button>
                    </div>

                    <div class="p-6 space-y-3">
                        @foreach ($selectedBundle->items as $item)
                            @php $itemColor = ['draft'=>'slate','quoted'=>'indigo','accepted'=>'emerald','in_progress'=>'purple','completed'=>'emerald','cancelled'=>'rose'][$item->status] ?? 'slate'; @endphp
                            <div class="rounded-lg border p-4 {{ $assigningItemId === $item->id ? 'bg-indigo-50 border-indigo-300' : '' }}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-xs font-bold text-indigo-600">{{ $item->trade?->name }}</p>
                                        <p class="font-semibold">{{ $item->label }}</p>
                                        @if ($item->description)
                                            <p class="text-xs text-slate-500 mt-1">{{ $item->description }}</p>
                                        @endif
                                        <div class="flex gap-3 text-xs mt-2">
                                            <span class="inline-block rounded-full bg-{{ $itemColor }}-100 text-{{ $itemColor }}-700 px-2 py-0.5 font-semibold">{{ $item->status }}</span>
                                            @if ($item->duration_minutes)
                                                <span class="text-slate-500">⏱ {{ $item->duration_minutes }}min</span>
                                            @endif
                                            @if ($item->provider)
                                                <span class="text-emerald-600">👤 {{ $item->provider->name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-slate-500">Estimé : <x-money :amount="(float) ($item->estimated_price_cents / 100)" :decimals="0" /></p>
                                        @if ($item->quoted_price_cents > 0)
                                            <p class="text-lg font-bold text-indigo-700"><x-money :amount="(float) ($item->quoted_price_cents / 100)" :decimals="0" /></p>
                                        @endif
                                        @if ($item->status === 'draft' || $item->status === 'quoted')
                                            <button wire:click="openAssign({{ $item->id }})" class="text-xs text-indigo-600 hover:underline font-semibold mt-1">
                                                {{ $item->provider_id ? 'Re-assigner' : 'Assigner provider' }}
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @if ($assigningItemId === $item->id)
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <select wire:model="assignProviderId" class="rounded-lg border-slate-300 text-sm">
                                            <option value="">-- Choisir provider --</option>
                                            @foreach ($availableProviders as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->email }})</option>
                                            @endforeach
                                        </select>
                                        <input wire:model="assignQuotedEur" type="number" step="10" placeholder="{{ __('Prix quoté') }} {{ app(\App\Services\Localization\Money::class)->symbol(\App\View\Components\Money::deviseDuContexte()) }}" class="rounded-lg border-slate-300 text-sm">
                                        <button wire:click="assignProvider" class="col-span-2 rounded-lg bg-indigo-600 text-white py-2 text-xs font-bold hover:bg-indigo-500">
                                            Valider l'assignation + quote
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="p-6 border-t flex justify-between items-center bg-slate-50 rounded-b-2xl">
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">Total bundle</p>
                            <p class="text-2xl font-black text-indigo-700"><x-money :amount="(float) (($selectedBundle->total_quoted_cents ?: $selectedBundle->total_estimated_cents) / 100)" /></p>
                            @if ($selectedBundle->bundle_discount_cents > 0)
                                <p class="text-xs text-emerald-600">Discount groupage : -<x-money :amount="(float) ($selectedBundle->bundle_discount_cents / 100)" /></p>
                            @endif
                        </div>
                        @if (!in_array($selectedBundle->status, ['completed', 'cancelled']))
                            <button wire:click="forceCancel({{ $selectedBundle->id }})" wire:confirm="Force-cancel ce bundle (admin override) ?" class="rounded-lg bg-rose-100 text-rose-700 px-4 py-2 text-sm font-semibold hover:bg-rose-200">
                                Force cancel
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
