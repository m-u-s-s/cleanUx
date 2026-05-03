<x-app-card title="Workspace finance" subtitle="Vue rapide du rendez-vous sélectionné et des actions disponibles.">
    <h2 class="text-lg font-semibold text-slate-800">Document sélectionné</h2>

    @if($selectedRendezVous)
        <div class="mt-4 space-y-3 text-sm text-slate-600">
            @include('livewire.admin.finance.workspace-reference')
            @include('livewire.admin.finance.workspace-actions')
            @include('livewire.admin.finance.workspace-amounts')
            @include('livewire.admin.finance.workspace-documents')
            @include('livewire.admin.finance.workspace-manual-payment')
            @include('livewire.admin.finance.workspace-corporate-context')
            @include('livewire.admin.finance.workspace-downloads')
        </div>
    @else
        <div class="mt-4 text-sm text-slate-400">Sélectionne un rendez-vous pour générer un devis, suivre l’encaissement et piloter la marge.</div>
    @endif
</x-app-card>
