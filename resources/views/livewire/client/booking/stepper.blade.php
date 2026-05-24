<div class="mt-6">
    @php
        $steps = [
            1 => ['Service',          'briefcase'],
            2 => ['Détails',          'cog'],
            3 => ['Coordonnées',      'user'],
            4 => [$this->isPremiumClient() ? 'Employé & créneau' : 'Créneau', 'calendar'],
            5 => ['Confirmation',     'check-circle'],
        ];
    @endphp

    <ol class="grid grid-cols-2 md:grid-cols-5 gap-2">
        @foreach($steps as $number => [$label, $icon])
            @php
                $isCurrent = $step === $number;
                $isDone    = $step > $number;
                $isFuture  = $step < $number;
            @endphp
            <li class="rounded-xl border px-3 py-2.5 transition
                {{ $isCurrent ? 'border-brand-300 bg-brand-50 shadow-soft-sm' : '' }}
                {{ $isDone ? 'border-emerald-200 bg-emerald-50/50' : '' }}
                {{ $isFuture ? 'border-slate-200 bg-white' : '' }}">
                <div class="flex items-center gap-2">
                    <div class="grid h-6 w-6 place-items-center rounded-full text-[10px] font-bold shrink-0
                        {{ $isCurrent ? 'bg-brand-600 text-white' : '' }}
                        {{ $isDone ? 'bg-emerald-500 text-white' : '' }}
                        {{ $isFuture ? 'bg-slate-100 text-slate-500' : '' }}">
                        @if($isDone)
                            <x-ui.icon name="check" class="w-3 h-3" />
                        @else
                            {{ $number }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide
                            {{ $isCurrent ? 'text-brand-700' : '' }}
                            {{ $isDone ? 'text-emerald-700' : '' }}
                            {{ $isFuture ? 'text-slate-400' : '' }}">
                            Étape {{ $number }}
                        </p>
                        <p class="text-xs font-semibold truncate
                            {{ $isCurrent ? 'text-brand-900' : '' }}
                            {{ $isDone ? 'text-emerald-800' : '' }}
                            {{ $isFuture ? 'text-slate-600' : '' }}">
                            {{ $label }}
                        </p>
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
</div>
