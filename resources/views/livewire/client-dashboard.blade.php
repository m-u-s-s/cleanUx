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