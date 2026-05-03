<x-filter-panel title="Pipeline finance" subtitle="Recherche, période, marché, zone, service et état de paiement." class="lg:col-span-3">
    @include('livewire.admin.finance.pipeline-filters')
    @include('livewire.admin.finance.pipeline-table')
    @include('livewire.admin.finance.pipeline-pagination')
</x-filter-panel>
