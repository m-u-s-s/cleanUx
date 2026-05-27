{{-- Phase F4 — Trade selection grid.
     Shows clickable cards for every active trade. Clicking a trade sets
     $selectedTradeId on the component and narrows the service dropdown to
     services belonging to that trade.
     The grid is hidden when there are no trades in DB (graceful fallback). --}}

@php
    $trades = $availableTrades ?? [];
@endphp

@if(!empty($trades))
    <div>
        <p class="text-sm font-semibold text-slate-700 mb-3">Sélectionnez un métier</p>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            @foreach($trades as $trade)
                @php
                    $isSelected = ($selectedTradeId ?? null) === $trade['id'];
                @endphp
                <button
                    type="button"
                    wire:click="selectTrade({{ $trade['id'] }})"
                    class="group relative flex flex-col items-center gap-2 rounded-2xl border p-4 text-center transition cursor-pointer
                        {{ $isSelected
                            ? 'border-brand-400 bg-brand-50 shadow-soft ring-2 ring-brand-300'
                            : 'border-slate-200 bg-white hover:border-brand-200 hover:bg-brand-50/40 hover:shadow-soft-sm'
                        }}"
                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                    title="{{ $trade['name'] }}"
                >
                    {{-- Icon --}}
                    <span class="grid h-10 w-10 place-items-center rounded-xl transition
                        {{ $isSelected ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-brand-100 group-hover:text-brand-700' }}">
                        <x-ui.icon :name="$trade['icon']" class="w-5 h-5" />
                    </span>

                    {{-- Name --}}
                    <span class="text-xs font-semibold leading-tight
                        {{ $isSelected ? 'text-brand-900' : 'text-slate-700' }}">
                        {{ $trade['name'] }}
                    </span>

                    {{-- Short description — only shown when hovered or selected --}}
                    @if(!empty($trade['short_description']))
                        <span class="text-[10px] leading-tight line-clamp-2
                            {{ $isSelected ? 'text-brand-700' : 'text-slate-400' }}">
                            {{ $trade['short_description'] }}
                        </span>
                    @endif

                    {{-- Selected checkmark badge --}}
                    @if($isSelected)
                        <span class="absolute top-2 right-2 grid h-4 w-4 place-items-center rounded-full bg-brand-600 text-white">
                            <x-ui.icon name="check" class="w-2.5 h-2.5" />
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        @if(($selectedTradeId ?? null))
            <div class="mt-2 flex items-center justify-between">
                <p class="text-xs text-slate-500">
                    Métier sélectionné.
                    @php
                        $selectedTrade = collect($trades)->firstWhere('id', $selectedTradeId);
                    @endphp
                    @if($selectedTrade)
                        <span class="font-semibold text-brand-700">{{ $selectedTrade['name'] }}</span> —
                        choisissez maintenant la prestation ci-dessous.
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
