<x-page-shell
    title="⚠️ Centre de litiges"
    subtitle="Signalez un problème, ajoutez des preuves et suivez le traitement de votre demande.">

    <div class="space-y-6">
        @include('livewire.client.litiges.kpis')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @include('livewire.client.litiges.create-form')
            @include('livewire.client.litiges.list')
        </div>
    </div>
</x-page-shell>
