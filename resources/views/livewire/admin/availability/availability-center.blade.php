<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Availability v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Calendrier providers</h1>
                <p class="text-sm text-slate-500">TZ par défaut : <code class="font-mono">{{ config('availability.default_timezone') }}</code></p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Slots actifs</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['active_slots']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Providers configurés</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['providers_with_slots']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Exceptions 30j</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['exceptions_30d']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Holds actifs</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['active_holds']) }}</p>
            </div>
        </div>

        <div class="flex gap-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Nom / email provider..."
                   class="flex-1 rounded-xl border-gray-300 text-sm" />
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Provider</th>
                        <th class="px-4 py-2 text-left">Email</th>
                        <th class="px-4 py-2 text-right">Slots actifs</th>
                        <th class="px-4 py-2 text-right">Exceptions 30j</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($providers as $p)
                        <tr>
                            <td class="px-4 py-2 text-xs font-semibold">{{ $p->name }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $p->email }}</td>
                            <td class="px-4 py-2 text-right text-xs">{{ $p->slots_count }}</td>
                            <td class="px-4 py-2 text-right text-xs">
                                {{ $p->availabilityExceptions()->where('date', '>=', now()->subDays(30))->count() }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Aucun provider avec calendrier configuré.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $providers->links() }}</div>
        </div>
    </div>
</div>
