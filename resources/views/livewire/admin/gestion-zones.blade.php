<div class="space-y-6">
    @include('livewire.admin.zones.hero')

    @php
        $statusPill = fn ($status) => match ($status) {
            'active' => 'bg-emerald-100 text-emerald-700',
            'paused' => 'bg-amber-100 text-amber-700',
            'archived' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    @include('livewire.admin.zones.kpis')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        @include('livewire.admin.zones.sidebar')
        @include('livewire.admin.zones.workspace')
    </div>
</div>
