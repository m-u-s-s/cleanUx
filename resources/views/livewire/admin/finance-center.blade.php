<div class="space-y-6">
    @include('livewire.admin.finance.hero')
    @include('livewire.admin.finance.flash-messages')
    @include('livewire.admin.finance.kpis')

    <div class="grid gap-4 lg:grid-cols-4">
        @include('livewire.admin.finance.pipeline')
        @include('livewire.admin.finance.workspace')
    </div>
</div>
