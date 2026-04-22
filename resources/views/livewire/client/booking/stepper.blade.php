<div class="mt-6">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @php
            $steps = [
                1 => 'Service',
                2 => 'Détails',
                3 => 'Coordonnées',
                4 => $this->isPremiumClient() ? 'Employé & créneau' : 'Créneau',
                5 => 'Confirmation',
            ];
        @endphp

        @foreach($steps as $number => $label)
        <div class="rounded-2xl border px-4 py-3 text-sm transition
            {{ $step === $number ? 'border-sky-500 bg-sky-50 text-sky-700' : ($step > $number ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500') }}">
            <div class="font-semibold">Étape {{ $number }}</div>
            <div class="text-xs mt-1">{{ $label }}</div>
        </div>
        @endforeach
    </div>
</div>
