<x-page-shell eyebrow="Finance" title="Centre finance" subtitle="Devis, factures, suivi d’encaissement et marge estimée pilotés depuis les rendez-vous.">
    <x-slot name="actions">
        <button wire:click="syncFilteredDocuments" class="cu-btn-secondary">Sync filtres</button>
        <button wire:click="syncAllDocuments" class="cu-btn-primary">Sync globale</button>
        <button wire:click="exportFinanceCsv" class="cu-btn-secondary">Export CSV</button>
        @if($selectedRendezVous)
            <button wire:click="downloadQuotePdf({{ $selectedRendezVous->id }})" class="cu-btn-secondary">Devis PDF</button>
            <button wire:click="downloadInvoicePdf({{ $selectedRendezVous->id }})" class="cu-btn-secondary">Facture PDF</button>
        @endif
    </x-slot>
</x-page-shell>
