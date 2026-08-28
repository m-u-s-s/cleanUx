<div data-component-root="client-finance-documents">
    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto max-w-7xl space-y-8 px-4 pb-10 pt-6 sm:px-6 lg:px-8">
            @include('livewire.client.finance.hero')
            @include('livewire.client.finance.kpis')
            @include('livewire.client.finance.controls-and-subscription')
            @include('livewire.client.finance.documents')
            @include('livewire.client.finance.supporting-panels')

            {{-- LES DEPENSES ET LES DOCUMENTS SONT LE MEME ARGENT : deux moities jamais reunies. --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <livewire:client.home-budget />
            </div>
        </div>
    </div>
</div>
