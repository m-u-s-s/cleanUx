<x-page-shell
    title="🏢 Approbations entreprises"
    subtitle="Validez les demandes B2B avant confirmation définitive du rendez-vous.">

    @include('livewire.admin.enterprise.approvals.filters')

    @include('livewire.admin.enterprise.approvals.list')

    @include('livewire.admin.enterprise.approvals.pagination')

    @include('livewire.admin.enterprise.approvals.reject-modal')
</x-page-shell>
