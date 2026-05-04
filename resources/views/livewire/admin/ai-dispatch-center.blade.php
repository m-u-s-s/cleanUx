<x-page-shell
    title="🤖 IA Dispatch"
    subtitle="Affectation intelligente selon zone, disponibilité, charge, qualité, favoris et urgence.">

    @include('livewire.admin.ai-dispatch.filters')

    @include('livewire.admin.ai-dispatch.table')

    @include('livewire.admin.ai-dispatch.preview-modal')
</x-page-shell>
