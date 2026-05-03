<section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
    @if($documentType !== 'invoices')
        <x-app-card padding="p-6" :title="__('Mes devis')" :subtitle="__('Derniers devis générés pour vos prestations.')">
            <div class="space-y-4">
                @forelse($quotes as $quote)
                    @include('livewire.client.finance.quote-card', ['quote' => $quote])
                @empty
                    <x-empty-state
                        :title="__('Aucun devis')"
                        :message="__('Vos devis apparaîtront ici dès qu’un rendez-vous sera chiffré.')"
                        icon="🧾"
                    />
                @endforelse
            </div>
        </x-app-card>
    @endif

    @if($documentType !== 'quotes')
        <x-app-card padding="p-6" :title="__('Mes factures')" :subtitle="__('Suivi des factures, échéances et montants restants.')">
            <div class="space-y-4">
                @forelse($invoices as $invoice)
                    @include('livewire.client.finance.invoice-card', ['invoice' => $invoice])
                @empty
                    <x-empty-state
                        :title="__('Aucune facture')"
                        :message="__('Vos factures apparaîtront ici dès qu’une prestation sera confirmée ou terminée.')"
                        icon="📄"
                    />
                @endforelse
            </div>
        </x-app-card>
    @endif
</section>
