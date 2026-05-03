@php
    $steps = [
        'assigned' => ['label' => 'Assignée', 'icon' => '📌'],
        'en_route' => ['label' => 'En route', 'icon' => '🚗'],
        'arrived' => ['label' => 'Sur place', 'icon' => '📍'],
        'started' => ['label' => 'En cours', 'icon' => '🧽'],
        'completed' => ['label' => 'Terminée', 'icon' => '✅'],
    ];

    $currentIndex = array_search($mission->status, array_keys($steps), true);
    $currentIndex = $currentIndex === false
        ? ($mission->status === 'planned' ? -1 : ($mission->status === 'cancelled' ? -1 : 0))
        : $currentIndex;
@endphp

<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Progression</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Timeline terrain</h2>
        </div>

        @if($mission->status === 'cancelled')
            <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-700 ring-1 ring-rose-100">Mission annulée</span>
        @endif
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-5">
        @foreach($steps as $status => $step)
            @php
                $index = $loop->index;
                $isDone = $currentIndex >= $index || ($mission->status === 'completed' && $status !== 'cancelled');
                $isCurrent = $mission->status === $status || ($mission->status === 'paused' && $status === 'started');
            @endphp

            <div class="rounded-2xl border p-4 {{ $isCurrent ? 'border-blue-300 bg-blue-50' : ($isDone ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50') }}">
                <div class="text-2xl">{{ $step['icon'] }}</div>
                <p class="mt-2 text-sm font-black {{ $isCurrent ? 'text-blue-800' : ($isDone ? 'text-emerald-800' : 'text-slate-500') }}">
                    {{ $step['label'] }}
                </p>
            </div>
        @endforeach
    </div>

    @if($mission->status === 'paused')
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
            Mission en pause : reprenez l’intervention dès que la situation est débloquée.
        </div>
    @endif
</section>
