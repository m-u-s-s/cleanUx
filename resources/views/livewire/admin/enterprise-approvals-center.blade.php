<x-page-shell
    title="🏢 Approbations entreprises"
    subtitle="Validez les demandes B2B avant confirmation définitive du rendez-vous.">

    {{-- La coquille de page n'espace pas ses enfants : le groupe porte l'ecart. --}}
    <div class="space-y-6">
        @include('livewire.admin.enterprise.approvals.filters')

        @include('livewire.admin.enterprise.approvals.list')

        @include('livewire.admin.enterprise.approvals.pagination')
    </div>

    @include('livewire.admin.enterprise.approvals.reject-modal')
</x-page-shell>
