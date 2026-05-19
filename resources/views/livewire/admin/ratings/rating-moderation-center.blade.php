<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Trust & safety</p>
                <h1 class="text-2xl font-black text-slate-900">Modération des avis</h1>
                <p class="text-sm text-slate-500">Examinez les signalements et gérez la visibilité des avis publics.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}"
               class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Signalements en attente</p>
                <p class="text-2xl font-black text-red-600">{{ $kpis['pending_reports'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Avis masqués</p>
                <p class="text-2xl font-black text-amber-600">{{ $kpis['hidden_total'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Avis publiés</p>
                <p class="text-2xl font-black text-emerald-600">{{ $kpis['published_total'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Auto-masqués</p>
                <p class="text-2xl font-black text-slate-900">{{ $kpis['auto_hidden'] }}</p>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-2 border-b">
            @foreach([
                'pending_reports' => 'Signalements',
                'hidden' => 'Masqués',
                'all' => 'Tous les avis',
            ] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="px-4 py-2 text-sm font-semibold border-b-2 {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($currentView === 'all')
            <div class="flex">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Rechercher avis…"
                       class="rounded-xl border-slate-300 text-sm" />
            </div>
        @endif

        {{-- Liste --}}
        <div class="space-y-3">
            @if($currentView === 'pending')
                @forelse($items as $report)
                    <div class="rounded-2xl bg-white border border-red-200 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-800">
                                        {{ $report->reason }}
                                    </span>
                                    <span class="text-xs text-slate-500">
                                        Signalé par {{ $report->reporter?->name ?? 'utilisateur supprimé' }} ·
                                        {{ $report->created_at->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                                @if($report->details)
                                    <p class="text-sm text-slate-700 mt-2 italic">"{{ $report->details }}"</p>
                                @endif
                            </div>
                        </div>

                        @if($report->feedback)
                            <div class="mt-4 rounded-xl bg-slate-50 border p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-amber-400">
                                        {{ str_repeat('★', (int) ($report->feedback->rating ?? $report->feedback->note)) }}
                                    </span>
                                    <span class="text-sm font-semibold">{{ $report->feedback->client?->name ?? 'Client' }}</span>
                                    <span class="text-xs text-slate-500">→ {{ $report->feedback->provider?->name ?? 'Provider' }}</span>
                                </div>
                                @if($report->feedback->effectiveComment())
                                    <p class="text-sm text-slate-700">{{ $report->feedback->effectiveComment() }}</p>
                                @endif
                                @if($report->feedback->reports_count > 1)
                                    <p class="text-xs text-red-600 mt-2 font-semibold">
                                        ⚠ Cet avis a été signalé {{ $report->feedback->reports_count }} fois
                                    </p>
                                @endif
                            </div>

                            <div class="flex gap-2 mt-4 justify-end">
                                <button wire:click="dismissReport({{ $report->id }})"
                                        class="rounded-xl border px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Rejeter signalement
                                </button>
                                <button wire:click="keep({{ $report->feedback->id }})"
                                        class="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Conserver l'avis
                                </button>
                                <button wire:click="hide({{ $report->feedback->id }})"
                                        wire:confirm="Masquer cet avis ?"
                                        class="rounded-xl bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                    Masquer l'avis
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl bg-white border border-dashed p-10 text-center text-slate-400">
                        Aucun signalement en attente.
                    </div>
                @endforelse
            @elseif($currentView === 'hidden')
                @forelse($items as $f)
                    <div class="rounded-2xl bg-white border p-5 shadow-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-amber-400">{{ str_repeat('★', (int) ($f->rating ?? $f->note)) }}</span>
                            <span class="text-sm font-semibold">{{ $f->client?->name ?? '—' }}</span>
                            <span class="text-xs text-slate-500">→ {{ $f->provider?->name ?? '—' }}</span>
                            <span class="text-xs text-red-600">Masqué : {{ $f->hidden_reason }}</span>
                        </div>
                        @if($f->effectiveComment())
                            <p class="text-sm text-slate-700">{{ $f->effectiveComment() }}</p>
                        @endif
                        <div class="flex justify-end mt-3">
                            <button wire:click="restore({{ $f->id }})"
                                    class="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                Restaurer
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl bg-white border border-dashed p-10 text-center text-slate-400">
                        Aucun avis masqué.
                    </div>
                @endforelse
            @else
                @forelse($items as $f)
                    <div class="rounded-2xl bg-white border p-5 shadow-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-amber-400">{{ str_repeat('★', (int) ($f->rating ?? $f->note)) }}</span>
                            <span class="text-sm font-semibold">{{ $f->client?->name ?? '—' }}</span>
                            <span class="text-xs text-slate-500">→ {{ $f->provider?->name ?? '—' }}</span>
                            <span class="text-xs text-slate-400">
                                {{ optional($f->published_at)->format('d/m/Y') }}
                            </span>
                            @if($f->reports_count > 0)
                                <span class="text-xs text-red-600">⚠ {{ $f->reports_count }} signalement(s)</span>
                            @endif
                        </div>
                        @if($f->effectiveComment())
                            <p class="text-sm text-slate-700">{{ $f->effectiveComment() }}</p>
                        @endif
                        <div class="flex justify-end mt-3">
                            <button wire:click="hide({{ $f->id }})"
                                    wire:confirm="Masquer cet avis ?"
                                    class="rounded-xl bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                Masquer
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl bg-white border border-dashed p-10 text-center text-slate-400">
                        Aucun avis publié.
                    </div>
                @endforelse
            @endif
        </div>

        <div>{{ $items->links() }}</div>
    </div>
</div>
