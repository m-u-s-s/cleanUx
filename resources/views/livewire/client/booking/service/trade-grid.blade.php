@if(!empty($availableTrades ?? []))
    <div>
        <p class="text-sm font-semibold text-slate-700 mb-3">Sélectionnez un métier</p>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            @foreach($availableTrades as $trade)
                <button
                    type="button"
                    wire:click="selectTrade({{ $trade['id'] }})"
                    class="group relative flex flex-col items-center gap-2 rounded-2xl border p-4 text-center transition cursor-pointer {{ ($selectedTradeId ?? null) === $trade['id'] ? 'border-brand-400 bg-brand-50 shadow-soft ring-2 ring-brand-300' : 'border-slate-200 bg-white hover:border-brand-200 hover:bg-brand-50/40 hover:shadow-soft-sm' }}"
                    aria-pressed="{{ ($selectedTradeId ?? null) === $trade['id'] ? 'true' : 'false' }}"
                    title="{{ $trade['name'] }}"
                >
                    <span class="grid h-10 w-10 place-items-center rounded-xl transition {{ ($selectedTradeId ?? null) === $trade['id'] ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-brand-100 group-hover:text-brand-700' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-5 h-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z"/>
                        </svg>
                    </span>

                    <span class="text-xs font-semibold leading-tight {{ ($selectedTradeId ?? null) === $trade['id'] ? 'text-brand-900' : 'text-slate-700' }}">
                        {{ $trade['name'] }}
                    </span>

                    @if(!empty($trade['short_description']))
                        <span class="text-[10px] leading-tight line-clamp-2 {{ ($selectedTradeId ?? null) === $trade['id'] ? 'text-brand-700' : 'text-slate-400' }}">
                            {{ $trade['short_description'] }}
                        </span>
                    @endif

                    @if(($selectedTradeId ?? null) === $trade['id'])
                        <span class="absolute top-2 right-2 grid h-4 w-4 place-items-center rounded-full bg-brand-600 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-2.5 h-2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        @if($selectedTradeId ?? null)
            <div class="mt-2 flex items-center justify-between">
                <p class="text-xs text-slate-500">
                    Métier sélectionné.
                    @if(collect($availableTrades)->firstWhere('id', $selectedTradeId))
                        <span class="font-semibold text-brand-700">{{ collect($availableTrades)->firstWhere('id', $selectedTradeId)['name'] }}</span>
                        — choisissez maintenant la prestation ci-dessous.
                    @endif
                </p>
                <button
                    type="button"
                    wire:click="clearTrade"
                    class="text-xs text-slate-400 hover:text-slate-600 underline transition"
                >
                    Voir tous les métiers
                </button>
            </div>
        @endif
    </div>
@endif
