<div class="space-y-6">
    @include('livewire.admin.b2b.operations.hero')

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @include('livewire.admin.b2b.operations.contract-form')

        @include('livewire.admin.b2b.operations.work-order-form')
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @include('livewire.admin.b2b.operations.recent-contracts')

        @include('livewire.admin.b2b.operations.recent-work-orders')
    </div>
</div>
