<div class="space-y-6">
    @include('livewire.client.dashboard.header')

    <div wire:loading.remove>
        @include('livewire.client.dashboard.kpis')
    </div>

    @include('livewire.client.dashboard.loading-state')

    @include('livewire.client.dashboard.main-content')

    <div wire:loading.remove>
        @include('livewire.client.dashboard.security-sessions')
    </div>
</div>
