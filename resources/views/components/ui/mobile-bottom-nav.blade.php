@props([
    'role' => null,
])

@php
    $role = $role ?: (auth()->check()
        ? (auth()->user()->isClient() ? 'client' : (auth()->user()->isEmploye() ? 'employe' : 'admin'))
        : null);

    $items = [];

    if ($role === 'client') {
        $items = array_filter([
            Route::has('client.dashboard') ? ['label' => 'Accueil', 'icon' => '🏠', 'route' => 'client.dashboard', 'active' => 'client.dashboard'] : null,
            Route::has('client.rendezvous.create') ? ['label' => 'Demande', 'icon' => '➕', 'route' => 'client.rendezvous.create', 'active' => 'client.rendezvous.create'] : null,
            Route::has('client.rendezvous.index') ? ['label' => 'RDV', 'icon' => '📅', 'route' => 'client.rendezvous.index', 'active' => 'client.rendezvous.*'] : null,
            Route::has('client.finance') ? ['label' => 'Finance', 'icon' => '💳', 'route' => 'client.finance', 'active' => 'client.finance*'] : null,
            Route::has('profile.show') ? ['label' => 'Profil', 'icon' => '👤', 'route' => 'profile.show', 'active' => 'profile.show'] : null,
        ]);
    }

    if ($role === 'employe') {
        $items = array_filter([
            Route::has('employe.dashboard') ? ['label' => 'Jour', 'icon' => '🏠', 'route' => 'employe.dashboard', 'active' => 'employe.dashboard'] : null,
            Route::has('employe.missions') ? ['label' => 'Missions', 'icon' => '📋', 'route' => 'employe.missions', 'active' => 'employe.missions*'] : null,
            Route::has('employe.planning') ? ['label' => 'Planning', 'icon' => '📅', 'route' => 'employe.planning', 'active' => 'employe.planning'] : null,
            Route::has('employe.incident') ? ['label' => 'Incident', 'icon' => '⚠️', 'route' => 'employe.incident', 'active' => 'employe.incident'] : null,
            Route::has('employe.historique') ? ['label' => 'Historique', 'icon' => '🕘', 'route' => 'employe.historique', 'active' => 'employe.historique'] : null,
        ]);
    }

    $count = max(1, count($items));
@endphp

@if(count($items))
    <nav {{ $attributes->merge(['class' => 'sm:hidden fixed bottom-0 inset-x-0 z-50 border-t border-slate-200 bg-white/95 backdrop-blur shadow-[0_-10px_30px_rgba(15,23,42,0.08)]']) }} aria-label="Navigation mobile">
        <div class="grid h-16" style="grid-template-columns: repeat({{ $count }}, minmax(0, 1fr));">
            @foreach($items as $item)
                @php($active = request()->routeIs($item['active']))
                <a href="{{ route($item['route']) }}"
                    class="flex flex-col items-center justify-center gap-0.5 text-[11px] transition {{ $active ? 'font-black text-sky-700' : 'font-semibold text-slate-500 hover:text-slate-900' }}">
                    <span class="text-lg leading-none">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>
@endif
