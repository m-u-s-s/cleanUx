<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Programme fidélité</p>
                <h1 class="text-2xl font-black text-slate-900">Centre de fidélité</h1>
                <p class="text-sm text-slate-500">Tiers, points, ajustements et distribution.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Membres totaux</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['total_members']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Actifs 30j</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['active_30d']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Points en circulation</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['total_points_circulating']) }}</p>
            </div>
        </div>

        {{-- Distribution par tier --}}
        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="text-sm font-bold uppercase text-slate-500 mb-3">Distribution par niveau</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($distribution as $row)
                    <button wire:click="$set('filterTierId', {{ $row['tier']->id }})"
                            class="rounded-xl border p-4 text-left hover:bg-slate-50 {{ $filterTierId === $row['tier']->id ? 'bg-indigo-50 border-indigo-300' : '' }}">
                        <p class="text-2xl">{{ $row['tier']->icon }}</p>
                        <p class="font-bold mt-1">{{ $row['tier']->name }}</p>
                        <p class="text-3xl font-black mt-2" style="color: {{ $row['tier']->color }};">{{ number_format($row['count']) }}</p>
                        <p class="text-xs text-slate-500">membres</p>
                    </button>
                @endforeach
            </div>
            @if($filterTierId)
                <button wire:click="$set('filterTierId', null)" class="text-xs text-red-600 hover:underline mt-2">
                    ✕ Effacer filtre
                </button>
            @endif
        </div>

        <div class="grid grid-cols-1 {{ $selected ? 'lg:grid-cols-2' : '' }} gap-6">
            {{-- Liste membres --}}
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-4 border-b">
                    <input type="text" wire:model.live.debounce.300ms="search"
                           placeholder="Rechercher nom/email..."
                           class="w-full rounded-xl border-gray-300 text-sm" />
                </div>
                <div class="divide-y">
                    @forelse($members as $m)
                        <button wire:click="selectUser({{ $m->user_id }})"
                                class="w-full text-left p-4 hover:bg-slate-50 {{ $selectedUserId === $m->user_id ? 'bg-indigo-50' : '' }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex-1">
                                    <p class="font-bold text-slate-900">{{ $m->user?->name ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ $m->user?->email }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-sm" style="color: {{ $m->currentTier?->color ?? '#64748b' }};">
                                        {{ $m->currentTier?->icon }} {{ $m->currentTier?->name ?? '—' }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ number_format($m->lifetime_points) }} pts
                                    </p>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="p-10 text-center text-slate-400">Aucun membre.</div>
                    @endforelse
                </div>
                <div class="p-3">{{ $members->links() }}</div>
            </div>

            @if($selected)
                <div class="rounded-2xl border bg-white shadow-sm">
                    <div class="p-4 border-b flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-black">{{ $selected->user?->name }}</h2>
                            <p class="text-xs text-slate-500">{{ $selected->user?->email }}</p>
                            <p class="text-sm mt-2" style="color: {{ $selected->currentTier?->color }};">
                                <span class="font-bold">{{ $selected->currentTier?->icon }} {{ $selected->currentTier?->name }}</span>
                                · {{ number_format($selected->lifetime_points) }} pts cumulés
                                · {{ number_format($selected->period_points) }} pts période
                            </p>
                        </div>
                        <button wire:click="closeDetail" class="text-slate-400 hover:text-slate-700">✕</button>
                    </div>

                    <div class="p-4 border-b">
                        <h3 class="text-sm font-bold mb-2">Ajustement manuel</h3>
                        <div class="space-y-2">
                            <input type="number" wire:model="adjustPoints"
                                   placeholder="Delta points (+/-)"
                                   class="w-full rounded-xl border-gray-300 text-sm" />
                            @error('adjustPoints') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <textarea wire:model="adjustReason" rows="2" maxlength="500"
                                      placeholder="Raison de l'ajustement..."
                                      class="w-full rounded-xl border-gray-300 text-sm"></textarea>
                            @error('adjustReason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <button wire:click="adjust"
                                    wire:confirm="Appliquer cet ajustement ?"
                                    class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                                Appliquer
                            </button>
                        </div>
                    </div>

                    <div class="p-4">
                        <h3 class="text-sm font-bold mb-2">Historique récent</h3>
                        <div class="space-y-1 max-h-80 overflow-auto">
                            @foreach($selectedTransactions as $tx)
                                <div class="flex items-center justify-between text-sm border-b py-1.5">
                                    <div>
                                        <p class="font-mono text-xs">{{ $tx->type }}</p>
                                        <p class="text-xs text-slate-500">{{ $tx->reason ?? '—' }} · {{ $tx->occurred_at?->format('d/m H:i') }}</p>
                                    </div>
                                    <span class="font-bold {{ $tx->direction === 'credit' ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $tx->direction === 'credit' ? '+' : '-' }}{{ number_format($tx->points) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
