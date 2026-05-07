@php
    /** @var array<int,array{icon:string,label:string,route:string,match?:string}> $items */
    $items = $items ?? [
        [
            'icon'  => '🏠',
            'label' => __('app.nav.home'),
            'route' => 'dashboard',
            'match' => 'dashboard',
        ],
        [
            'icon'  => '📅',
            'label' => __('app.nav.calendar'),
            'route' => 'client.calendar.index',
            'match' => 'client.calendar.*',
        ],
        [
            'icon'  => '➕',
            'label' => __('app.nav.new_booking'),
            'route' => 'client.rendezvous.create',
            'match' => 'client.rendezvous.create',
        ],
        [
            'icon'  => '📊',
            'label' => __('app.nav.analytics'),
            'route' => 'client.analytics.dashboard',
            'match' => 'client.analytics.*',
        ],
        [
            'icon'  => '👤',
            'label' => __('app.nav.profile'),
            'route' => 'profile.show',
            'match' => 'profile.*',
        ],
    ];
@endphp

{{-- Bottom navigation visible uniquement sur mobile (sm: cache à partir de 640px) --}}
<nav class="fixed bottom-0 left-0 right-0 z-40 border-t border-slate-200 bg-white sm:hidden"
     style="padding-bottom: env(safe-area-inset-bottom);">
    <ul class="grid grid-cols-{{ count($items) }}">
        @foreach ($items as $item)
            @php
                $url = \Illuminate\Support\Facades\Route::has($item['route'])
                    ? route($item['route'])
                    : '#';
                $active = isset($item['match']) && request()->routeIs($item['match']);
                $isPrimary = ($item['icon'] ?? '') === '➕';
            @endphp
            <li class="relative">
                <a href="{{ $url }}"
                   class="flex flex-col items-center justify-center gap-0.5 py-2 px-1
                       {{ $active ? 'text-blue-600' : 'text-slate-500 hover:text-slate-700' }}
                       {{ $isPrimary ? '!text-white' : '' }}"
                   {{ $isPrimary ? 'aria-current="step"' : '' }}>

                    @if ($isPrimary)
                        {{-- Bouton primary (action principale) en bulle bleue surélevée --}}
                        <span class="absolute -top-3 flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 shadow-lg shadow-blue-200">
                            <span class="text-2xl leading-none">{{ $item['icon'] }}</span>
                        </span>
                        <span class="mt-9 text-[10px] font-medium text-slate-600">{{ $item['label'] }}</span>
                    @else
                        <span class="text-xl leading-none">{{ $item['icon'] }}</span>
                        <span class="text-[10px] font-medium {{ $active ? 'font-semibold' : '' }}">{{ $item['label'] }}</span>
                    @endif
                </a>

                {{-- Indicateur point bleu si actif (sauf primary) --}}
                @if ($active && ! $isPrimary)
                    <span class="absolute top-1 left-1/2 -translate-x-1/2 h-1 w-1 rounded-full bg-blue-600"></span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
