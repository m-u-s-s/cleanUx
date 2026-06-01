<div class="space-y-3">
    @forelse ($companies as $org)
        <div class="rounded-2xl border bg-white p-4 shadow-sm hover:shadow-md transition-shadow flex flex-col">
            <div class="flex items-start gap-3 {{ $selectionMode ? 'cursor-pointer' : '' }}"
                 @if($selectionMode) wire:click="selectCompany({{ $org->id }})" @endif>
                <div class="h-14 w-14 rounded-2xl bg-indigo-100 flex items-center justify-center text-indigo-700 text-lg font-bold">
                    {{ strtoupper(substr($org->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-slate-900 truncate">{{ $org->name }}</p>
                    @if($org->rating_avg !== null)
                        <p class="text-sm mt-1">
                            <span class="text-amber-400">★</span>
                            <span class="font-bold">{{ number_format((float) $org->rating_avg, 1, ',', ' ') }}</span>
                            <span class="text-xs text-slate-500">({{ (int) $org->rating_count }} avis)</span>
                        </p>
                    @else
                        <p class="text-xs text-slate-400 mt-1">Aucun avis</p>
                    @endif
                </div>
            </div>

            @if($selectionMode)
                <button type="button"
                        wire:click="selectCompany({{ $org->id }})"
                        class="mt-4 w-full rounded-xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Choisir cette société
                </button>
            @endif
        </div>
    @empty
        <div class="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center text-sm text-slate-400">
            Aucune société prestataire disponible pour le moment.
        </div>
    @endforelse
</div>
