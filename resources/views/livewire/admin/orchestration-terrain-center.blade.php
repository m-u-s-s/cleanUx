<div class="space-y-6 p-6">
    @include('livewire.admin.orchestration.hero')

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        @include('livewire.admin.orchestration.batch-form')
        @include('livewire.admin.orchestration.quick-panel')
    </div>

    @include('livewire.admin.orchestration.batches-table')
</div>
