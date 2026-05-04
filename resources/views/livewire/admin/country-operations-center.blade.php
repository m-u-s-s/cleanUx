<div class="space-y-6">
    @include('livewire.admin.countries.hero')

    @php
        $statusPill = fn (bool $active) => $active
            ? 'bg-emerald-100 text-emerald-700'
            : 'bg-rose-100 text-rose-700';
    @endphp

    @include('livewire.admin.countries.kpis')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        @include('livewire.admin.countries.sidebar')
        @include('livewire.admin.countries.workspace')
    </div>
</div>
