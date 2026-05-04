<div class="space-y-6">
    @include('livewire.admin.automation.hero')

    <div class="grid gap-6 xl:grid-cols-2">
        @include('livewire.admin.automation.work-orders-table')
        @include('livewire.admin.automation.batches-table')
    </div>

    @include('livewire.admin.automation.load-snapshots')
</div>
