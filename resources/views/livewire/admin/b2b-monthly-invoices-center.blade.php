<x-page-shell
    title="💼 Facturation B2B centralisée"
    subtitle="Générez des factures mensuelles groupées par entreprise, site et centre de coût.">

    {{-- La coquille n'espace pas ses enfants : les deux cartes se touchaient. --}}
    <div class="space-y-6">
        @include('livewire.admin.b2b.invoices.generator')

        @include('livewire.admin.b2b.invoices.table')
    </div>
</x-page-shell>
