<div class="py-8 max-w-5xl mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Récompenses</p>
            <h1 class="text-2xl font-black text-slate-900">Mes badges</h1>
            <p class="text-sm text-slate-500">{{ count($earnedBadgeIds) }} / {{ $allBadges->count() }} débloqués</p>
        </div>
        <button wire:click="refresh" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">
            Actualiser
        </button>
    </div>

    @if ($earnedAwards->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-xs uppercase font-bold text-slate-500 mb-3">Récemment débloqués</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($earnedAwards->take(8) as $award)
                    <div class="rounded-xl border bg-gradient-to-br from-amber-50 to-yellow-50 p-4 text-center shadow-sm">
                        <p class="text-4xl">{{ $award->badge->icon ?? '🏆' }}</p>
                        <p class="text-sm font-bold text-slate-900 mt-1">{{ $award->badge->name }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $award->awarded_at?->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <h2 class="text-xs uppercase font-bold text-slate-500 mb-3">Tous les badges</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach ($allBadges as $badge)
                @php $earned = in_array($badge->id, $earnedBadgeIds); @endphp
                @php $tierColor = ['bronze'=>'amber','silver'=>'slate','gold'=>'yellow','platinum'=>'indigo'][$badge->tier] ?? 'slate'; @endphp
                <div class="rounded-2xl border {{ $earned ? 'bg-white shadow-sm' : 'bg-slate-50 opacity-60' }} p-5">
                    <div class="flex items-start gap-3">
                        <div class="text-5xl {{ $earned ? '' : 'grayscale' }}">{{ $badge->icon ?? '🏆' }}</div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-slate-900">{{ $badge->name }}</p>
                                <span class="text-xs rounded-full bg-{{ $tierColor }}-100 text-{{ $tierColor }}-700 px-2 py-0.5 font-semibold">{{ $badge->tier }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">{{ $badge->description }}</p>
                            @if ($earned)
                                <p class="text-xs font-bold text-emerald-600 mt-2">✓ Débloqué</p>
                            @else
                                <p class="text-xs text-slate-400 mt-2">🔒 À débloquer</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
