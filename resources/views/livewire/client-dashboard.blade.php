@php
    $useV2 = \Laravel\Pennant\Feature::for(auth()->user())->active('client-mobile-v2');
    $v2Props = $useV2 ? $this->getV2Props() : null;
@endphp

<div>
    @if($useV2)
        @vite(['resources/css/app.css', 'resources/js/islands/client-home.ts'])
        <div id="client-home-island" data-props="{{ json_encode($v2Props) }}"></div>
    @else
        {{-- legacy below --}}
        <div class="space-y-6">
            @include('livewire.client.dashboard.header')

            @include('livewire.client.dashboard.kpis')

            @include('livewire.client.dashboard.loading-state')

            @include('livewire.client.dashboard.main-content')

            @include('livewire.client.dashboard.security-sessions')

            <a href="{{ route('client.analytics.dashboard') }}"
                class="@if (request()->routeIs('client.analytics.*')) text-blue-600 font-semibold @endif">
                📊 Analytics
            </a>
        </div>
    @endif
</div>